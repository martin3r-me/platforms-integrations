<?php

namespace Platform\Integrations\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Core\Models\User;
use Platform\Integrations\Exceptions\GoogleSearchConsoleApiException;
use Platform\Integrations\Models\IntegrationConnection;

/**
 * Service für die Kommunikation mit der Google Search Console API
 *
 * Zwei Base-URLs:
 * - webmasters/v3: Sites, Search Analytics, Sitemaps
 * - searchconsole/v1: URL Inspection
 *
 * Read-Only Endpoints:
 * - GET /sites — Alle verifizierten Sites auflisten
 * - GET /sites/{siteUrl} — Details einer Site
 * - POST /sites/{siteUrl}/searchAnalytics/query — Search Analytics abfragen
 * - GET /sites/{siteUrl}/sitemaps — Sitemaps einer Site
 * - GET /sites/{siteUrl}/sitemaps/{feedpath} — Details einer Sitemap
 * - POST /urlInspection/index:inspect — URL Inspection (andere Base-URL!)
 *
 * @see https://developers.google.com/webmaster-tools/v3/
 */
class GoogleSearchConsoleApiService
{
    protected GoogleSearchConsoleIntegrationService $integrationService;

    protected ?int $connectionIdOverride = null;

    public function __construct(GoogleSearchConsoleIntegrationService $integrationService)
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
     * Löst die IntegrationConnection auf.
     *
     * Mit gesetzter connectionId (forConnection) kann sie ohne User aufgelöst
     * werden — nötig für Scheduler/CLI-Kontexte (z.B. SEO-Collectors) ohne
     * eingeloggten User. Analog zu DataForSeoApiService.
     */
    protected function resolveConnection(?User $user): IntegrationConnection
    {
        if ($this->connectionIdOverride) {
            if ($user) {
                $resolver = app(IntegrationConnectionResolver::class);
                $connection = $resolver->resolveById($this->connectionIdOverride, $user);
            } else {
                // Scheduler/CLI: direkt per ID laden ohne Access-Check
                $connection = IntegrationConnection::with('integration')->find($this->connectionIdOverride);
            }
        } else {
            if (!$user) {
                throw GoogleSearchConsoleApiException::noConnection();
            }
            $connection = $this->integrationService->getConnectionForUser($user);
        }

        if (!$connection) {
            Log::warning('Google Search Console API: Keine Connection', [
                'user_id' => $user?->id,
                'connection_override' => $this->connectionIdOverride,
            ]);
            throw GoogleSearchConsoleApiException::noConnection();
        }

        return $connection;
    }

    // =========================================================================
    // PUBLIC API METHODS
    // =========================================================================

    /**
     * Alle verifizierten Sites auflisten
     *
     * @throws GoogleSearchConsoleApiException
     */
    public function getSites(?User $user = null): array
    {
        return $this->get($user, '/sites');
    }

    /**
     * Details einer Site abrufen
     *
     * @throws GoogleSearchConsoleApiException
     */
    public function getSite(User $user, string $siteUrl): array
    {
        return $this->get($user, '/sites/' . urlencode($siteUrl));
    }

    /**
     * Search Analytics abfragen (Clicks, Impressions, CTR, Position)
     *
     * @param array $params Request-Body (startDate, endDate, dimensions, etc.)
     * @throws GoogleSearchConsoleApiException
     */
    public function querySearchAnalytics(?User $user, string $siteUrl, array $params): array
    {
        return $this->post($user, '/sites/' . urlencode($siteUrl) . '/searchAnalytics/query', $params);
    }

    /**
     * Sitemaps einer Site auflisten
     *
     * @throws GoogleSearchConsoleApiException
     */
    public function getSitemaps(User $user, string $siteUrl): array
    {
        return $this->get($user, '/sites/' . urlencode($siteUrl) . '/sitemaps');
    }

    /**
     * Details einer Sitemap abrufen
     *
     * @throws GoogleSearchConsoleApiException
     */
    public function getSitemap(User $user, string $siteUrl, string $feedpath): array
    {
        return $this->get($user, '/sites/' . urlencode($siteUrl) . '/sitemaps/' . urlencode($feedpath));
    }

    /**
     * URL Inspection — Index-Status einer URL prüfen
     *
     * Verwendet die separate Inspection-API Base-URL.
     *
     * @throws GoogleSearchConsoleApiException
     */
    public function inspectUrl(User $user, string $inspectionUrl, string $siteUrl): array
    {
        return $this->request($user, 'POST', '/urlInspection/index:inspect', [], [
            'inspectionUrl' => $inspectionUrl,
            'siteUrl' => $siteUrl,
        ], true);
    }

    // =========================================================================
    // INTERNE HTTP METHODEN
    // =========================================================================

    /**
     * GET Request an die Google Search Console API
     *
     * @throws GoogleSearchConsoleApiException
     */
    protected function get(?User $user, string $path, array $query = []): array
    {
        return $this->request($user, 'GET', $path, $query);
    }

    /**
     * POST Request an die Google Search Console API
     *
     * @throws GoogleSearchConsoleApiException
     */
    protected function post(?User $user, string $path, array $body = []): array
    {
        return $this->request($user, 'POST', $path, [], $body);
    }

    /**
     * Führt einen HTTP Request an die Google Search Console API aus
     *
     * @param bool $useInspectionApi Verwendet die Inspection-API Base-URL statt webmasters/v3
     * @throws GoogleSearchConsoleApiException
     */
    protected function request(?User $user, string $method, string $path, array $query = [], array $body = [], bool $useInspectionApi = false): array
    {
        $connection = $this->resolveConnection($user);

        $token = $this->integrationService->getValidAccessToken($connection);

        if (!$token) {
            Log::warning('Google Search Console API: Kein gültiger Token', ['user_id' => $user?->id]);
            throw GoogleSearchConsoleApiException::unauthorized();
        }

        $baseUrl = $useInspectionApi
            ? config('integrations.google_search_console.inspection_base_url', 'https://searchconsole.googleapis.com/v1')
            : config('integrations.google_search_console.api_base_url', 'https://www.googleapis.com/webmasters/v3');
        $url = $baseUrl . $path;
        $timeout = config('integrations.google_search_console.timeout.default', 30);
        $connectTimeout = config('integrations.google_search_console.timeout.connect', 10);

        try {
            $http = Http::withToken($token)
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
        } catch (GoogleSearchConsoleApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Google Search Console API: Verbindungsfehler', [
                'user_id' => $user?->id,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            $this->updateConnectionStatus($connection, 'error', $e->getMessage());

            throw GoogleSearchConsoleApiException::connectionError($e->getMessage());
        }
    }

    /**
     * Verarbeitet die HTTP Response und behandelt Fehler
     *
     * @throws GoogleSearchConsoleApiException
     */
    protected function handleResponse(Response $response, IntegrationConnection $connection): array
    {
        $statusCode = $response->status();
        $data = $response->json() ?? [];

        // Auth-Fehler — Token ungültig oder abgelaufen
        if ($statusCode === 401) {
            $this->updateConnectionStatus($connection, 'error', 'Ungültiger Token oder Token abgelaufen');
            throw GoogleSearchConsoleApiException::unauthorized();
        }

        // Rate-Limit
        if ($statusCode === 429) {
            $retryAfter = (int) $response->header('Retry-After');
            throw GoogleSearchConsoleApiException::rateLimited($retryAfter ?: null);
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
            $data['error']['message'] ?? $data['message'] ?? null
        );

        Log::warning('Google Search Console API: Fehler-Response', [
            'status_code' => $statusCode,
            'response' => $data,
        ]);

        throw GoogleSearchConsoleApiException::fromResponse($statusCode, $data);
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
