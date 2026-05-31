<?php

namespace Platform\Integrations\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Core\Models\User;
use Platform\Integrations\Exceptions\PlausibleApiException;
use Platform\Integrations\Models\IntegrationConnection;

/**
 * Service für die Kommunikation mit der Plausible Analytics API
 *
 * Bearer Token (API-Key direkt) Authentifizierung.
 * Base-URL pro Connection konfigurierbar (Self-Hosted-Support).
 *
 * Endpoints:
 * - GET /api/v1/sites — Alle Sites auflisten
 * - GET /api/v1/stats/realtime/visitors?site_id={siteId} — Realtime Visitors
 * - GET /api/v1/stats/aggregate?site_id={siteId} — Aggregierte Statistiken
 * - GET /api/v1/stats/timeseries?site_id={siteId} — Zeitreihen-Daten
 * - GET /api/v1/stats/breakdown?site_id={siteId} — Aufschlüsselung
 *
 * @see https://plausible.io/docs/stats-api
 */
class PlausibleApiService
{
    protected PlausibleIntegrationService $integrationService;

    protected ?int $connectionIdOverride = null;

    public function __construct(PlausibleIntegrationService $integrationService)
    {
        $this->integrationService = $integrationService;
    }

    /**
     * Gibt eine Kopie dieses Services zurück, die eine spezifische Connection verwendet.
     */
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
     * Löst die IntegrationConnection für den User auf.
     */
    protected function resolveConnection(User $user): IntegrationConnection
    {
        if ($this->connectionIdOverride) {
            $resolver = app(IntegrationConnectionResolver::class);
            $connection = $resolver->resolveById($this->connectionIdOverride, $user);
        } else {
            $connection = $this->integrationService->getConnectionForUser($user);
        }

        if (!$connection) {
            Log::warning('Plausible API: Keine Connection für User', ['user_id' => $user->id]);
            throw PlausibleApiException::noConnection();
        }

        return $connection;
    }

    // =========================================================================
    // PUBLIC API METHODS
    // =========================================================================

    /**
     * Alle Sites auflisten
     *
     * @throws PlausibleApiException
     */
    public function getSites(User $user): array
    {
        return $this->get($user, '/api/v1/sites');
    }

    /**
     * Realtime Visitors für eine Site abrufen
     *
     * @throws PlausibleApiException
     */
    public function getRealtimeVisitors(User $user, string $siteId): array
    {
        // Plausible gibt hier nur eine Zahl zurück, wir wrappen sie
        $connection = $this->resolveConnection($user);
        $apiKey = $this->integrationService->getApiKey($connection);

        if (!$apiKey) {
            throw PlausibleApiException::unauthorized();
        }

        $baseUrl = $this->integrationService->getBaseUrl($connection);
        $url = $baseUrl . '/api/v1/stats/realtime/visitors';
        $timeout = config('integrations.plausible.timeout.default', 30);
        $connectTimeout = config('integrations.plausible.timeout.connect', 10);

        try {
            $response = Http::withToken($apiKey)
                ->timeout($timeout)
                ->connectTimeout($connectTimeout)
                ->get($url, ['site_id' => $siteId]);

            if ($response->status() === 401) {
                $this->updateConnectionStatus($connection, 'error', 'Ungültiger API-Key');
                throw PlausibleApiException::unauthorized();
            }

            if ($response->status() === 429) {
                $retryAfter = (int) $response->header('Retry-After');
                throw PlausibleApiException::rateLimited($retryAfter ?: null);
            }

            if ($response->successful()) {
                $this->updateConnectionStatus($connection, 'active');
                // Realtime endpoint returns a plain number
                $body = $response->body();
                return ['visitors' => (int) $body, 'site_id' => $siteId];
            }

            throw PlausibleApiException::fromResponse($response->status(), $response->json() ?? []);
        } catch (PlausibleApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Plausible API: Verbindungsfehler', [
                'path' => '/api/v1/stats/realtime/visitors',
                'error' => $e->getMessage(),
            ]);
            $this->updateConnectionStatus($connection, 'error', $e->getMessage());
            throw PlausibleApiException::connectionError($e->getMessage());
        }
    }

    /**
     * Aggregierte Statistiken abrufen
     *
     * @param array $params site_id (required), period, date, metrics, compare, filters
     * @throws PlausibleApiException
     */
    public function getAggregateStats(User $user, array $params): array
    {
        return $this->get($user, '/api/v1/stats/aggregate', $params);
    }

    /**
     * Zeitreihen-Daten abrufen
     *
     * @param array $params site_id (required), period, date, metrics, interval, filters
     * @throws PlausibleApiException
     */
    public function getTimeseries(User $user, array $params): array
    {
        return $this->get($user, '/api/v1/stats/timeseries', $params);
    }

    /**
     * Aufschlüsselung nach Dimension abrufen
     *
     * @param array $params site_id (required), property (required), period, date, metrics, limit, page, filters
     * @throws PlausibleApiException
     */
    public function getBreakdown(User $user, array $params): array
    {
        return $this->get($user, '/api/v1/stats/breakdown', $params);
    }

    // =========================================================================
    // INTERNE HTTP METHODEN
    // =========================================================================

    /**
     * GET Request an die Plausible API
     *
     * @throws PlausibleApiException
     */
    protected function get(User $user, string $path, array $query = []): array
    {
        return $this->request($user, 'GET', $path, $query);
    }

    /**
     * Führt einen HTTP Request an die Plausible API aus
     *
     * @throws PlausibleApiException
     */
    protected function request(User $user, string $method, string $path, array $query = [], array $body = []): array
    {
        $connection = $this->resolveConnection($user);

        $apiKey = $this->integrationService->getApiKey($connection);

        if (!$apiKey) {
            Log::warning('Plausible API: Kein API-Key für User', ['user_id' => $user->id]);
            throw PlausibleApiException::unauthorized();
        }

        $baseUrl = $this->integrationService->getBaseUrl($connection);
        $url = $baseUrl . $path;
        $timeout = config('integrations.plausible.timeout.default', 30);
        $connectTimeout = config('integrations.plausible.timeout.connect', 10);

        try {
            $http = Http::withToken($apiKey)
                ->timeout($timeout)
                ->connectTimeout($connectTimeout)
                ->withHeaders([
                    'Accept' => 'application/json',
                ]);

            $response = match ($method) {
                'GET' => $http->get($url, $query),
                'POST' => $http->post($url, $body),
                default => $http->get($url, $query),
            };

            return $this->handleResponse($response, $connection);
        } catch (PlausibleApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Plausible API: Verbindungsfehler', [
                'user_id' => $user->id,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            $this->updateConnectionStatus($connection, 'error', $e->getMessage());

            throw PlausibleApiException::connectionError($e->getMessage());
        }
    }

    /**
     * Verarbeitet die HTTP Response und behandelt Fehler
     *
     * @throws PlausibleApiException
     */
    protected function handleResponse(Response $response, IntegrationConnection $connection): array
    {
        $statusCode = $response->status();
        $data = $response->json() ?? [];

        // Auth-Fehler — API-Key ungültig
        if ($statusCode === 401) {
            $this->updateConnectionStatus($connection, 'error', 'Ungültiger API-Key');
            throw PlausibleApiException::unauthorized();
        }

        // Rate-Limit
        if ($statusCode === 429) {
            $retryAfter = (int) $response->header('Retry-After');
            throw PlausibleApiException::rateLimited($retryAfter ?: null);
        }

        // Erfolgreiche Response (2xx)
        if ($response->successful()) {
            $this->updateConnectionStatus($connection, 'active');
            return $data;
        }

        // Sonstige Fehler
        $this->updateConnectionStatus(
            $connection,
            'active',
            $data['error'] ?? $data['message'] ?? null
        );

        Log::warning('Plausible API: Fehler-Response', [
            'status_code' => $statusCode,
            'response' => $data,
        ]);

        throw PlausibleApiException::fromResponse($statusCode, $data);
    }

    /**
     * Aktualisiert den Status der IntegrationConnection
     */
    protected function updateConnectionStatus(
        IntegrationConnection $connection,
        string $status,
        ?string $error = null
    ): void {
        $connection->status = $status;
        $connection->last_error = $error;
        $connection->last_tested_at = now();
        $connection->save();
    }
}
