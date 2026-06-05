<?php

namespace Platform\Integrations\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Core\Models\User;
use Platform\Integrations\Exceptions\DatevApiException;
use Platform\Integrations\Models\IntegrationConnection;

/**
 * Service für die Kommunikation mit der DATEV API
 *
 * DATEV Platform API Endpoints (Beispiele):
 * - GET /platform/v2/tenants — Mandanten auflisten
 * - GET /platform/v2/tenants/{tenant-id} — Mandant abrufen
 *
 * Weitere Dienste (nach Freischaltung durch DATEV):
 * - Accounting (Buchhaltung)
 * - Payroll (Lohnabrechnung)
 * - Document Management (DMS)
 *
 * @see https://developer.datev.de/
 */
class DatevApiService
{
    protected DatevIntegrationService $integrationService;

    protected ?int $connectionIdOverride = null;

    public function __construct(DatevIntegrationService $integrationService)
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
            Log::warning('DATEV API: Keine Connection für User', ['user_id' => $user->id]);
            throw DatevApiException::noConnection();
        }

        return $connection;
    }

    // =========================================================================
    // PUBLIC API METHODS
    // =========================================================================

    /**
     * Mandanten auflisten
     *
     * @throws DatevApiException
     */
    public function getTenants(User $user): array
    {
        return $this->get($user, '/platform/v2/tenants');
    }

    /**
     * Einzelnen Mandanten abrufen
     *
     * @throws DatevApiException
     */
    public function getTenant(User $user, string $tenantId): array
    {
        return $this->get($user, '/platform/v2/tenants/' . urlencode($tenantId));
    }

    // =========================================================================
    // INTERNE HTTP METHODEN
    // =========================================================================

    /**
     * GET Request an die DATEV API
     *
     * @throws DatevApiException
     */
    protected function get(User $user, string $path, array $query = []): array
    {
        return $this->request($user, 'GET', $path, $query);
    }

    /**
     * POST Request an die DATEV API
     *
     * @throws DatevApiException
     */
    protected function post(User $user, string $path, array $body = []): array
    {
        return $this->request($user, 'POST', $path, [], $body);
    }

    /**
     * PUT Request an die DATEV API
     *
     * @throws DatevApiException
     */
    protected function put(User $user, string $path, array $body = []): array
    {
        return $this->request($user, 'PUT', $path, [], $body);
    }

    /**
     * Führt einen HTTP Request an die DATEV API aus
     *
     * @throws DatevApiException
     */
    protected function request(User $user, string $method, string $path, array $query = [], array $body = []): array
    {
        $connection = $this->resolveConnection($user);

        $token = $this->integrationService->getValidAccessToken($connection);

        if (!$token) {
            Log::warning('DATEV API: Kein gültiger Token für User', ['user_id' => $user->id]);
            throw DatevApiException::unauthorized();
        }

        $baseUrl = config('integrations.datev.api_base_url', 'https://sandbox-api.datev.de');
        $url = $baseUrl . $path;
        $timeout = config('integrations.datev.timeout.default', 30);
        $connectTimeout = config('integrations.datev.timeout.connect', 10);

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
                'PUT' => $http->put($url, $body),
                'DELETE' => $http->delete($url),
                default => $http->get($url, $query),
            };

            return $this->handleResponse($response, $connection);
        } catch (DatevApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('DATEV API: Verbindungsfehler', [
                'user_id' => $user->id,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            $this->updateConnectionStatus($connection, 'error', $e->getMessage());

            throw DatevApiException::connectionError($e->getMessage());
        }
    }

    /**
     * Verarbeitet die HTTP Response und behandelt Fehler
     *
     * @throws DatevApiException
     */
    protected function handleResponse(Response $response, IntegrationConnection $connection): array
    {
        $statusCode = $response->status();
        $data = $response->json() ?? [];

        // Auth-Fehler — Token ungültig oder abgelaufen
        if ($statusCode === 401) {
            $this->updateConnectionStatus($connection, 'error', 'Ungültiger Token oder Token abgelaufen');
            throw DatevApiException::unauthorized();
        }

        // Rate-Limit
        if ($statusCode === 429) {
            $retryAfter = (int) $response->header('Retry-After');
            throw DatevApiException::rateLimited($retryAfter ?: null);
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
            $data['error_description'] ?? $data['error']['message'] ?? $data['message'] ?? null
        );

        Log::warning('DATEV API: Fehler-Response', [
            'status_code' => $statusCode,
            'response' => $data,
        ]);

        throw DatevApiException::fromResponse($statusCode, $data);
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
