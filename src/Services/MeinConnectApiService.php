<?php

namespace Platform\Integrations\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Platform\Core\Models\User;
use Platform\Integrations\Exceptions\MeinConnectApiException;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Support\MeinConnectPageParser;

/**
 * Liest den Gerätestatus (CoolCooking-Boxen) aus einer MeinConnect-Gastfreigabe.
 *
 * User-zentriert wie die übrigen ApiServices: jede Methode bekommt den User, die
 * Connection wird aufgelöst; forConnection() wählt eine bestimmte Connection.
 *
 * Anders als resolveById() akzeptiert forConnection() hier auch Connections, die dem
 * User nur TEAM-weit freigegeben sind (Abgleich über resolveAllForUser) – sonst wäre
 * eine im Dropdown sichtbare Connection im Abruf nicht nutzbar.
 *
 * Ergebnisse werden je Connection kurz gecacht: viele Bildschirme teilen sich eine
 * Freigabe, die Quelle soll nicht pro Bildschirm abgefragt werden.
 */
class MeinConnectApiService
{
    /** Mindestabstand zwischen zwei Abrufen derselben Freigabe (Sekunden). */
    public const CACHE_SECONDS = 30;

    protected ?int $connectionIdOverride = null;

    public function __construct(
        protected MeinConnectIntegrationService $integrationService,
        protected IntegrationConnectionResolver $resolver,
    ) {
    }

    public function forConnection(?int $connectionId): static
    {
        if ($connectionId === null) {
            return $this;
        }

        $clone = clone $this;
        $clone->connectionIdOverride = $connectionId;

        return $clone;
    }

    /**
     * Alle freigegebenen Geräte mit aktuellem Status.
     *
     * @return array{
     *   connection_id:int, fetched_at:string, cached:bool,
     *   devices: list<array<string,mixed>>
     * }
     */
    public function devices(User $user, bool $fresh = false): array
    {
        $connection = $this->resolveConnection($user);
        $key = 'integrations:meinconnect:devices:' . $connection->id;

        if (!$fresh && ($cached = Cache::get($key))) {
            return ['cached' => true] + $cached;
        }

        // Nur ein Abruf gleichzeitig je Freigabe; Wartende bekommen danach den Cache.
        $lock = Cache::lock($key . ':lock', 30);
        try {
            $lock->block(20);
            if (!$fresh && ($cached = Cache::get($key))) {
                return ['cached' => true] + $cached;
            }

            $result = $this->collect($connection);
            Cache::put($key, $result, self::CACHE_SECONDS);

            return ['cached' => false] + $result;
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            if ($cached = Cache::get($key)) {
                return ['cached' => true] + $cached;
            }
            throw MeinConnectApiException::unreachable(503);
        } finally {
            $lock->release();
        }
    }

    /** @return array{connection_id:int, fetched_at:string, devices:list<array<string,mixed>>} */
    protected function collect(IntegrationConnection $connection): array
    {
        $shareUrl = $this->integrationService->getShareUrl($connection);
        if (!$shareUrl) {
            throw MeinConnectApiException::noConnection();
        }

        try {
            $links = MeinConnectPageParser::deviceLinks(MeinConnectIntegrationService::fetchHtml($shareUrl), $shareUrl);
            if ($links === []) {
                throw MeinConnectApiException::noDevices();
            }
        } catch (MeinConnectApiException $e) {
            // Nur den Fehler merken, den Status NICHT auf 'error' setzen: geteilte Connections
            // werden nur im Status 'active' aufgelöst – ein kurzer Ausfall der Quelle würde
            // sonst alle Bildschirme bis zum nächsten manuellen Test leer lassen.
            $connection->forceFill(['last_error' => $e->getMessage()])->save();
            throw $e;
        }

        // Nacheinander statt parallel: die Quelle begrenzt gleichzeitige Verbindungen.
        $devices = array_map(fn (array $link) => $this->readDevice($link), $links);

        if ($connection->last_error) {
            $connection->forceFill(['last_error' => null])->save();
        }

        return [
            'connection_id' => (int) $connection->id,
            'fetched_at' => now()->toIso8601String(),
            'devices' => $devices,
        ];
    }

    /** @param array{id:string,name:string,url:string} $link */
    protected function readDevice(array $link): array
    {
        try {
            $html = MeinConnectIntegrationService::fetchHtml($link['url']);

            return ['id' => $link['id'], 'error' => null] + MeinConnectPageParser::parseDevice($html, $link['name']);
        } catch (\Throwable $e) {
            Log::info('MeinConnect: Gerät nicht lesbar', ['device' => $link['id'], 'error' => $e->getMessage()]);

            return ['id' => $link['id'], 'name' => $link['name'], 'error' => 'Gerätedaten konnten nicht geladen werden.'];
        }
    }

    protected function resolveConnection(User $user): IntegrationConnection
    {
        if ($this->connectionIdOverride) {
            $connection = $this->resolver
                ->resolveAllForUser(MeinConnectIntegrationService::INTEGRATION_KEY, $user)
                ->first(fn ($c) => (int) $c->id === (int) $this->connectionIdOverride);
        } else {
            $connection = $this->integrationService->getConnectionForUser($user);
        }

        if (!$connection) {
            throw MeinConnectApiException::noConnection();
        }

        return $connection;
    }
}
