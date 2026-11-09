<?php

namespace Platform\Integrations\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Core\Models\User;
use Platform\Integrations\Exceptions\MeinConnectApiException;
use Platform\Integrations\Models\Integration;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Support\MeinConnectPageParser;

/**
 * Helper-Service für MeinConnect-Gastfreigaben (CoolCooking-Boxen).
 *
 * MeinConnect hat keine offizielle API. Ein Kunde kann einzelne Geräte per
 * Gastfreigabe-Link (https://meinconnect.app/freigabe/{token}) öffentlich lesbar machen.
 * Dieser Link ist das Zugangsgeheimnis und liegt verschlüsselt in credentials.share_url.
 *
 * Mehrere Connections pro User sind erlaubt (z. B. getrennte Freigaben für Kita und Küche).
 */
class MeinConnectIntegrationService
{
    public const INTEGRATION_KEY = 'meinconnect';

    public function __construct(protected IntegrationConnectionResolver $resolver)
    {
    }

    public function getConnectionForUser(User $user): ?IntegrationConnection
    {
        return $this->resolver->resolveForUser(self::INTEGRATION_KEY, $user);
    }

    public function getShareUrl(IntegrationConnection $connection): ?string
    {
        $url = ($connection->credentials ?? [])['share_url'] ?? null;

        return is_string($url) && $url !== '' ? $url : null;
    }

    /** Normalisiert und prüft einen Freigabelink; wirft bei ungültigem Format. */
    public static function normalizeShareUrl(string $url): string
    {
        $url = rtrim(trim($url), '/');
        $url = preg_replace('~^http://~i', 'https://', $url) ?? $url;
        $url = preg_replace('~^https://www\.~i', 'https://', $url) ?? $url;
        // Alles hinter dem Token (z. B. /geraet/… oder ?query) abschneiden.
        if (preg_match('~^(https://meinconnect\.app/freigabe/[A-Za-z0-9]+)~', $url, $m)) {
            $url = $m[1];
        }

        if (!preg_match(MeinConnectPageParser::SHARE_URL_PATTERN, $url)) {
            throw MeinConnectApiException::invalidShareUrl();
        }

        return $url;
    }

    /**
     * Erstellt eine neue oder aktualisiert eine bestehende (eigene) Connection.
     */
    public function createOrUpdateConnectionForUser(User $user, string $shareUrl, ?int $connectionId = null, ?string $name = null): IntegrationConnection
    {
        $shareUrl = self::normalizeShareUrl($shareUrl);

        $integration = Integration::firstOrCreate(
            ['key' => self::INTEGRATION_KEY],
            [
                'name' => 'MeinConnect',
                'is_enabled' => true,
                'supported_auth_schemes' => ['api_key'],
                'meta' => [
                    'description' => 'MeinConnect (CoolCooking): Gerätestatus über einen Gastfreigabe-Link.',
                    'icon' => 'heroicon-o-fire',
                ],
            ]
        );

        if ($connectionId) {
            $connection = IntegrationConnection::withTrashed()
                ->where('id', $connectionId)
                ->where('integration_id', $integration->id)
                ->where('owner_user_id', $user->id)
                ->first();

            if (!$connection) {
                throw new \RuntimeException("Connection #{$connectionId} nicht gefunden.");
            }
            if ($connection->trashed()) {
                $connection->restore();
            }
        } else {
            $isFirst = !IntegrationConnection::query()
                ->where('integration_id', $integration->id)
                ->where('owner_user_id', $user->id)
                ->exists();

            $connection = new IntegrationConnection([
                'integration_id' => $integration->id,
                'owner_user_id' => $user->id,
                'name' => IntegrationConnection::generateName($integration->id, $user->id, $integration->name),
                'is_default' => $isFirst,
                'auth_scheme' => 'api_key',
                'status' => 'active',
            ]);
        }

        if ($name !== null && trim($name) !== '') {
            $connection->name = mb_substr(trim($name), 0, 120);
        }

        $credentials = $connection->credentials ?? [];
        $credentials['share_url'] = $shareUrl;
        $connection->credentials = $credentials;
        $connection->auth_scheme = 'api_key';
        $connection->status = 'active';
        $connection->last_error = null;
        $connection->save();

        Log::info('MeinConnect connection created/updated', [
            'connection_id' => $connection->id,
            'user_id' => $user->id,
        ]);

        return $connection;
    }

    /**
     * Ruft die Freigabe-Übersicht ab und zählt die sichtbaren Geräte.
     *
     * @return array{success: bool, message: string, data?: array}
     */
    public function testConnection(IntegrationConnection $connection): array
    {
        $shareUrl = $this->getShareUrl($connection);
        if (!$shareUrl) {
            return ['success' => false, 'message' => 'Kein Freigabelink hinterlegt.'];
        }

        try {
            $html = self::fetchHtml($shareUrl);
            $devices = MeinConnectPageParser::deviceLinks($html, $shareUrl);

            if ($devices === []) {
                throw MeinConnectApiException::noDevices();
            }

            $connection->status = 'active';
            $connection->last_error = null;
            $connection->last_tested_at = now();
            $connection->save();

            return [
                'success' => true,
                'message' => count($devices) . ' Gerät(e) in der Freigabe gefunden.',
                'data' => ['devices' => array_map(fn ($d) => ['id' => $d['id'], 'name' => $d['name']], $devices)],
            ];
        } catch (\Throwable $e) {
            $message = $e instanceof MeinConnectApiException ? $e->getMessage() : 'Verbindungsfehler: ' . $e::class;

            $connection->status = 'error';
            $connection->last_error = $message;
            $connection->last_tested_at = now();
            $connection->save();

            Log::warning('MeinConnect testConnection failed', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $message];
        }
    }

    /**
     * Lädt eine MeinConnect-Seite. Keine Redirects (sonst könnte eine umgeleitete
     * Antwort von einem fremden Host ausgewertet werden).
     */
    public static function fetchHtml(string $url): string
    {
        if (!str_starts_with($url, MeinConnectPageParser::ORIGIN . '/freigabe/')) {
            throw MeinConnectApiException::invalidShareUrl();
        }

        $response = Http::withHeaders([
            'Accept' => 'text/html',
            'User-Agent' => 'Platform-Integrations/1.0 (+signage)',
        ])
            ->withOptions(['allow_redirects' => false])
            ->connectTimeout(5)
            ->timeout(12)
            ->get($url);

        if (!$response->successful()) {
            throw MeinConnectApiException::unreachable($response->status());
        }

        $html = (string) $response->body();
        if (!MeinConnectPageParser::looksLikeMeinConnect($html)) {
            throw MeinConnectApiException::unrecognized();
        }

        return $html;
    }
}
