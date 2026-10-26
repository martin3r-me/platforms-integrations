<?php

namespace Platform\Integrations\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Core\Models\User;
use Platform\Integrations\Exceptions\NinjaApiException;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Support\HttpTransientFailure;

/**
 * Service für die NinjaOne Public API 2.0.
 *
 * Auth:     OAuth2-Bearer-Token, beschafft von {@see NinjaIntegrationService}.
 * Base-URL: regionsabhängig, z.B. https://eu.ninjarmm.com — Pfade beginnen mit /v2/.
 * Umfang:   315 Operationen in 28 Themenbereichen, katalogisiert in
 *           {@see NinjaResource}. Jeder Aufruf wird gegen diesen Katalog
 *           geprüft, damit Tippfehler sofort auffallen statt in einem 404 zu enden.
 *
 * PAGINIERUNG
 * NinjaOne paginiert uneinheitlich: die meisten Listen über pageSize + after
 * (Cursor auf der letzten ID), einige über cursor, andere gar nicht. Deshalb
 * reicht dieser Service die Parameter durch, statt eine Einheitslogik zu
 * erzwingen; {@see fetchAll()} kennt beide Spielarten.
 *
 * @see https://app.ninjarmm.com/apidocs/
 */
class NinjaApiService
{
    protected NinjaIntegrationService $integrationService;

    protected ?int $connectionIdOverride = null;

    /** @var callable|null */
    protected $observer = null;

    public function __construct(NinjaIntegrationService $integrationService)
    {
        $this->integrationService = $integrationService;
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
     * Beobachter für jeden Aufruf: (method, path, status, durationMs).
     * Nötig, weil call() nur die dekodierten Daten zurückgibt und der
     * Status-Code erfolgreicher Antworten sonst verloren ginge.
     */
    public function withObserver(callable $observer): static
    {
        $clone = clone $this;
        $clone->observer = $observer;

        return $clone;
    }

    /** @throws NinjaApiException */
    protected function resolveConnection(?User $user): IntegrationConnection
    {
        // resolveById prueft die Zugriffsrechte des Users und verlangt ihn
        // deshalb zwingend — ohne User gibt es keine aufloesbare Verbindung.
        if (!$user) {
            throw NinjaApiException::noConnection();
        }

        $connection = $this->connectionIdOverride
            ? app(IntegrationConnectionResolver::class)->resolveById($this->connectionIdOverride, $user)
            : $this->integrationService->getConnectionForUser($user);

        if (!$connection) {
            throw NinjaApiException::noConnection();
        }

        return $connection;
    }

    // =========================================================================
    // GENERISCHER ZUGRIFF
    // =========================================================================

    /**
     * Führt eine katalogisierte Operation aus.
     *
     * Pfad-Platzhalter werden aus $pathParams ersetzt: call($u, 'GET',
     * '/v2/device/{id}', ['id' => 42]) wird zu /v2/device/42.
     *
     * @param array<string, mixed> $pathParams
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @return array<mixed>
     *
     * @throws NinjaApiException
     */
    public function call(
        ?User $user,
        string $method,
        string $path,
        array $pathParams = [],
        array $query = [],
        array $body = []
    ): array {
        $method = mb_strtoupper(trim($method));
        $path = '/' . ltrim(trim($path), '/');

        if (!NinjaResource::exists($method, $path)) {
            throw NinjaApiException::unknownOperation($method, $path);
        }

        return $this->request($user, $method, $this->resolvePath($path, $pathParams), $query, $body);
    }

    /**
     * Ersetzt {platzhalter} im Pfad.
     *
     * @param array<string, mixed> $params
     *
     * @throws NinjaApiException
     */
    protected function resolvePath(string $path, array $params): string
    {
        return preg_replace_callback(
            '/\{(\w+)\}/',
            static function (array $m) use ($params, $path) {
                $name = $m[1];
                $value = $params[$name] ?? null;

                if ($value === null || $value === '') {
                    throw NinjaApiException::missingPathParameter($name, $path);
                }

                return rawurlencode((string) $value);
            },
            $path
        ) ?? $path;
    }

    /**
     * Läuft eine Liste über alle Seiten durch.
     *
     * NinjaOne nutzt bei den großen Listen einen Cursor auf der zuletzt
     * gelieferten ID ("after"). Abbruch, sobald eine Seite kleiner als pageSize
     * ist oder maxPages erreicht wird — letzteres verhindert, dass ein
     * unerwartetes Antwortformat zur Endlosschleife wird.
     *
     * @param array<string, mixed> $query
     * @return array<int, mixed>
     *
     * @throws NinjaApiException
     */
    public function fetchAll(
        ?User $user,
        string $path,
        array $pathParams = [],
        array $query = [],
        int $pageSize = 500,
        int $maxPages = 50
    ): array {
        $items = [];
        $after = $query['after'] ?? null;
        $page = 0;

        do {
            $pageQuery = array_merge($query, ['pageSize' => $pageSize]);
            if ($after !== null) {
                $pageQuery['after'] = $after;
            }

            $chunk = $this->call($user, 'GET', $path, $pathParams, $pageQuery);
            $chunk = $this->extractItems($chunk);
            $items = array_merge($items, $chunk);
            $page++;

            $last = end($chunk);
            $after = is_array($last) ? ($last['id'] ?? null) : null;
        } while (count($chunk) === $pageSize && $after !== null && $page < $maxPages);

        return $items;
    }

    /**
     * Holt die Einträge aus einer Antwort, unabhängig davon ob sie als Liste
     * oder unter einem Wrapper-Key kommen.
     *
     * @param array<mixed> $response
     * @return array<int, mixed>
     */
    public function extractItems(array $response): array
    {
        if (array_is_list($response)) {
            return $response;
        }

        foreach (['results', 'data', 'items', 'content'] as $key) {
            if (isset($response[$key]) && is_array($response[$key])) {
                return $response[$key];
            }
        }

        return [];
    }

    // =========================================================================
    // KOMFORT-METHODEN
    // =========================================================================

    /** @return array<mixed> */
    public function listOrganizations(?User $user, array $query = []): array
    {
        return $this->call($user, 'GET', '/v2/organizations', [], $query);
    }

    /** @return array<mixed> */
    public function getOrganization(?User $user, int|string $id): array
    {
        return $this->call($user, 'GET', '/v2/organization/{id}', ['id' => $id]);
    }

    /** @return array<mixed> */
    public function listDevices(?User $user, array $query = []): array
    {
        return $this->call($user, 'GET', '/v2/devices', [], $query);
    }

    /** @return array<mixed> */
    public function listDevicesDetailed(?User $user, array $query = []): array
    {
        return $this->call($user, 'GET', '/v2/devices-detailed', [], $query);
    }

    /** @return array<mixed> */
    public function getDevice(?User $user, int|string $id): array
    {
        return $this->call($user, 'GET', '/v2/device/{id}', ['id' => $id]);
    }

    /** @return array<mixed> */
    public function listAlerts(?User $user, array $query = []): array
    {
        return $this->call($user, 'GET', '/v2/alerts', [], $query);
    }

    /** @return array<mixed> */
    public function listActivities(?User $user, array $query = []): array
    {
        return $this->call($user, 'GET', '/v2/activities', [], $query);
    }

    /** @return array<mixed> */
    public function listJobs(?User $user, array $query = []): array
    {
        return $this->call($user, 'GET', '/v2/jobs', [], $query);
    }

    // =========================================================================
    // HTTP
    // =========================================================================

    /**
     * Führt den Request aus.
     *
     * Bei 401 wird genau einmal ein frisches Token geholt und wiederholt: Ein
     * serverseitig verfallenes Token ist sonst nicht von falschen Zugangsdaten
     * zu unterscheiden, und der Aufrufer bekäme einen Fehler, obwohl die
     * Verbindung in Ordnung ist.
     *
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @return array<mixed>
     *
     * @throws NinjaApiException
     */
    protected function request(
        ?User $user,
        string $method,
        string $path,
        array $query = [],
        array $body = [],
        bool $isRetry = false
    ): array {
        $connection = $this->resolveConnection($user);
        $token = $this->integrationService->getAccessToken($connection);
        $url = $this->integrationService->getBaseUrl($connection) . $path;

        try {
            $request = $this->buildRequest($token, $method);
            $startedAt = microtime(true);

            $response = match ($method) {
                'GET' => $request->get($url, $this->normalizeQuery($query)),
                'POST' => $request->post($url, $body),
                'PUT' => $request->put($url, $body),
                'PATCH' => $request->patch($url, $body),
                'DELETE' => $request->delete($url, $body ?: $this->normalizeQuery($query)),
                default => throw new NinjaApiException("Nicht unterstützte HTTP-Methode: {$method}", 400, 'INVALID_METHOD'),
            };

            $this->notifyObserver($method, $path, $response, $startedAt);
        } catch (NinjaApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('NinjaOne API: Verbindungsfehler', [
                'connection_id' => $connection->id,
                'method' => $method,
                'path' => $path,
                'error' => $e->getMessage(),
                'transient' => HttpTransientFailure::isTransient($e),
            ]);

            $message = HttpTransientFailure::describe(
                $e,
                'NinjaOne',
                (int) config('integrations.ninja.timeout.default', 30)
            );

            // Netzwerkfehler sagen nichts über die Zugangsdaten aus — der
            // Status bleibt stehen, damit geteilte Verbindungen nutzbar bleiben.
            if (HttpTransientFailure::isTransient($e)) {
                $connection->last_error = $message;
                $connection->last_tested_at = now();
                $connection->save();
            }

            throw NinjaApiException::connectionError($message);
        }

        if ($response->status() === 401 && !$isRetry) {
            Log::info('NinjaOne API: Token abgelehnt, einmaliger Neuversuch', [
                'connection_id' => $connection->id,
                'path' => $path,
            ]);

            $this->forgetToken($connection);

            return $this->request($user, $method, $path, $query, $body, true);
        }

        return $this->handleResponse($response, $connection);
    }

    protected function buildRequest(string $token, string $method): PendingRequest
    {
        $request = Http::withToken($token)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->connectTimeout((int) config('integrations.ninja.timeout.connect', 10))
            ->timeout((int) config('integrations.ninja.timeout.default', 30));

        // Nur idempotente Methoden wiederholen, und nur bei sporadischen
        // Verbindungsabbrüchen — nicht bei Read-Timeouts.
        if (in_array($method, ['GET', 'HEAD'], true)) {
            $request = $request->retry(
                max(1, (int) config('integrations.ninja.retry.times', 2)),
                max(0, (int) config('integrations.ninja.retry.sleep_ms', 500)),
                static fn (\Throwable $e) => HttpTransientFailure::isRetryable($e),
                false
            );
        }

        return $request;
    }

    /** Verwirft das gespeicherte Token, damit der nächste Zugriff ein frisches holt. */
    protected function forgetToken(IntegrationConnection $connection): void
    {
        $credentials = $connection->credentials ?? [];

        if (isset($credentials['oauth']['access_token'])) {
            unset($credentials['oauth']['access_token'], $credentials['oauth']['expires_at']);
            $connection->credentials = $credentials;
            $connection->save();
        }
    }

    /**
     * Booleans als "true"/"false" statt 1/0 — NinjaOne wertet die numerische
     * Form nicht als Wahrheitswert aus.
     *
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    protected function normalizeQuery(array $query): array
    {
        foreach ($query as $key => $value) {
            if (is_bool($value)) {
                $query[$key] = $value ? 'true' : 'false';
            } elseif (is_array($value)) {
                $query[$key] = implode(',', $value);
            }
        }

        return array_filter($query, static fn ($v) => $v !== null && $v !== '');
    }

    /**
     * @return array<mixed>
     *
     * @throws NinjaApiException
     */
    protected function handleResponse(Response $response, IntegrationConnection $connection): array
    {
        $data = $response->json();

        if ($response->successful()) {
            $connection->status = 'active';
            $connection->last_error = null;
            $connection->last_tested_at = now();
            $connection->save();

            if ($data === null) {
                // 204 und leere Antworten sind bei DELETE/PATCH normal.
                return ['success' => true, 'status' => $response->status()];
            }

            return is_array($data) ? $data : ['data' => $data];
        }

        $exception = NinjaApiException::fromResponse(
            $response->status(),
            is_array($data) ? $data : [],
            $response->body()
        );

        // Nur 401/403 markieren die Verbindung als defekt — fachliche Fehler
        // (404, 422, 429, 5xx) lassen sie aktiv.
        $connection->status = in_array($response->status(), [401, 403], true) ? 'error' : 'active';
        $connection->last_error = $exception->getMessage();
        $connection->last_tested_at = now();
        $connection->save();

        Log::warning('NinjaOne API: Fehler-Response', [
            'connection_id' => $connection->id,
            'status_code' => $response->status(),
        ]);

        throw $exception;
    }

    protected function notifyObserver(string $method, string $path, Response $response, float $startedAt): void
    {
        if ($this->observer) {
            ($this->observer)($method, $path, $response->status(), (int) round((microtime(true) - $startedAt) * 1000));
        }
    }

    /**
     * Reduziert eine Antwort auf die gewünschten Felder (Dot-Notation für
     * verschachtelte). Delegiert an die gemeinsame Projektion.
     */
    public static function projectFields(mixed $response, array $fields): mixed
    {
        return \Platform\Integrations\Support\FieldProjection::apply($response, $fields);
    }
}
