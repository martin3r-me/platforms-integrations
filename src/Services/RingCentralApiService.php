<?php

namespace Platform\Integrations\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Core\Models\User;
use Platform\Integrations\Exceptions\RingCentralApiException;
use Platform\Integrations\Models\IntegrationConnection;

/**
 * Service für die Kommunikation mit der RingCentral API
 *
 * OAuth2 Bearer Token Authentifizierung (wie Google Search Console).
 *
 * Read-Only Endpoints:
 * - GET /account/~/extension/~/call-log — Call Log des Users
 * - GET /account/~/call-log — Call Log des gesamten Accounts
 * - GET /account/~ — Account-Informationen
 * - GET /account/~/extension/~ — User-Informationen
 * - GET /account/~/extension/~/active-calls — Aktive Anrufe
 * - GET /account/~/extension — Alle Extensions
 *
 * @see https://developers.ringcentral.com/api-reference
 */
class RingCentralApiService
{
    protected RingCentralIntegrationService $integrationService;

    protected ?int $connectionIdOverride = null;

    public function __construct(RingCentralIntegrationService $integrationService)
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
            Log::warning('RingCentral API: Keine Connection für User', ['user_id' => $user->id]);
            throw RingCentralApiException::noConnection();
        }

        return $connection;
    }

    // =========================================================================
    // PUBLIC API METHODS
    // =========================================================================

    /**
     * User-Informationen abrufen (eigene Extension)
     *
     * @throws RingCentralApiException
     */
    public function getUserInfo(User $user): array
    {
        return $this->get($user, '/account/~/extension/~');
    }

    /**
     * Account-Informationen abrufen
     *
     * @throws RingCentralApiException
     */
    public function getAccountInfo(User $user): array
    {
        return $this->get($user, '/account/~');
    }

    /**
     * Call Log des gesamten Accounts abrufen
     *
     * @param array $filters Optional: dateFrom, dateTo, type, direction, page, perPage
     * @throws RingCentralApiException
     */
    public function getCallLog(User $user, array $filters = []): array
    {
        return $this->get($user, '/account/~/call-log', $filters);
    }

    /**
     * Call Log der eigenen Extension abrufen
     *
     * @param array $filters Optional: dateFrom, dateTo, type, direction, page, perPage
     * @throws RingCentralApiException
     */
    public function getExtensionCallLog(User $user, array $filters = []): array
    {
        return $this->get($user, '/account/~/extension/~/call-log', $filters);
    }

    /**
     * Aktive Anrufe der eigenen Extension abrufen
     *
     * @throws RingCentralApiException
     */
    public function getActiveCalls(User $user): array
    {
        return $this->get($user, '/account/~/extension/~/active-calls');
    }

    /**
     * Alle Extensions/Nebenstellen des Accounts auflisten
     *
     * @param array $filters Optional: page, perPage, status, type
     * @throws RingCentralApiException
     */
    public function getExtensions(User $user, array $filters = []): array
    {
        return $this->get($user, '/account/~/extension', $filters);
    }

    // =========================================================================
    // INTERNE HTTP METHODEN
    // =========================================================================

    /**
     * GET Request an die RingCentral API
     *
     * @throws RingCentralApiException
     */
    protected function get(User $user, string $path, array $query = []): array
    {
        return $this->request($user, 'GET', $path, $query);
    }

    /**
     * Führt einen HTTP Request an die RingCentral API aus
     *
     * @throws RingCentralApiException
     */
    protected function request(User $user, string $method, string $path, array $query = [], array $body = []): array
    {
        $connection = $this->resolveConnection($user);

        $token = $this->integrationService->getValidAccessToken($connection);

        if (!$token) {
            Log::warning('RingCentral API: Kein gültiger Token für User', ['user_id' => $user->id]);
            throw RingCentralApiException::unauthorized();
        }

        $baseUrl = config('integrations.ringcentral.api_base_url', 'https://platform.ringcentral.com/restapi/v1.0');
        $url = $baseUrl . $path;
        $timeout = config('integrations.ringcentral.timeout.default', 30);
        $connectTimeout = config('integrations.ringcentral.timeout.connect', 10);

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
        } catch (RingCentralApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('RingCentral API: Verbindungsfehler', [
                'user_id' => $user->id,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            $this->updateConnectionStatus($connection, 'error', $e->getMessage());

            throw RingCentralApiException::connectionError($e->getMessage());
        }
    }

    /**
     * Verarbeitet die HTTP Response und behandelt Fehler
     *
     * @throws RingCentralApiException
     */
    protected function handleResponse(Response $response, IntegrationConnection $connection): array
    {
        $statusCode = $response->status();
        $data = $response->json() ?? [];

        // Auth-Fehler — Token ungültig oder abgelaufen
        if ($statusCode === 401) {
            $this->updateConnectionStatus($connection, 'error', 'Ungültiger Token oder Token abgelaufen');
            throw RingCentralApiException::unauthorized();
        }

        // Rate-Limit
        if ($statusCode === 429) {
            $retryAfter = (int) $response->header('Retry-After');
            throw RingCentralApiException::rateLimited($retryAfter ?: null);
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
            $data['message'] ?? $data['error_description'] ?? null
        );

        Log::warning('RingCentral API: Fehler-Response', [
            'status_code' => $statusCode,
            'response' => $data,
        ]);

        throw RingCentralApiException::fromResponse($statusCode, $data);
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
