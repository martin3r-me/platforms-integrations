<?php

namespace Platform\Integrations\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Core\Models\User;
use Platform\Integrations\Exceptions\EasybillApiException;
use Platform\Integrations\Models\IntegrationConnection;

/**
 * Service für die Kommunikation mit der easybill REST API.
 *
 * Bietet ein generisches Gerüst (get/post/put/delete) mit Bearer-Token-Auth.
 * Konkrete Resource-Wrapper (Customers, Documents, Positions, Projects) werden
 * bei Bedarf in folgenden Tickets ergänzt.
 *
 * Auth-Header: Authorization: Bearer <api_key>
 * Base URL:    https://api.easybill.de/rest/v1
 *
 * Rate Limits laut easybill:
 *  - PLUS:     10 req/min
 *  - BUSINESS: 60 req/min
 * Bei Überschreitung HTTP 429.
 *
 * Default-Listenlimit: 100, max 1000 (Query-Param `limit`).
 *
 * @see https://www.easybill.de/api/
 */
class EasybillApiService
{
    protected const BASE_URL = 'https://api.easybill.de/rest/v1';

    protected EasybillIntegrationService $integrationService;

    protected ?int $connectionIdOverride = null;

    public function __construct(EasybillIntegrationService $integrationService)
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

    protected function resolveConnection(User $user): IntegrationConnection
    {
        if ($this->connectionIdOverride) {
            $resolver = app(IntegrationConnectionResolver::class);
            $connection = $resolver->resolveById($this->connectionIdOverride, $user);
        } else {
            $connection = $this->integrationService->getConnectionForUser($user);
        }

        if (!$connection) {
            Log::warning('easybill API: Keine Connection für User', ['user_id' => $user->id]);
            throw EasybillApiException::noConnection();
        }

        return $connection;
    }

    // =========================================================================
    // RESOURCE WRAPPERS (Stubs — werden bei Bedarf ausgebaut)
    // =========================================================================
    //
    // Folgende Resource-Wrapper sind in folgenden Tickets vorgesehen:
    //   - Customers:       /customers, /customers/{id}, /customers/{id}/contacts
    //   - Documents:       /documents, /documents/{id}, /documents/{id}/pdf
    //   - Positions:       /positions, /positions/{id}
    //   - Projects:        /projects, /projects/{id}
    //   - Attachments:     /attachments, /attachments/{id}/content
    //   - Doc-Payments:    /document-payments, /document-payments/{id}
    //
    // Bis dahin nutzen Aufrufer die generischen get/post/put/delete-Methoden.
    // =========================================================================

    // =========================================================================
    // INTERNE HTTP METHODEN
    // =========================================================================

    /**
     * GET Request an die easybill API.
     *
     * @throws EasybillApiException
     */
    public function get(User $user, string $endpoint, array $query = []): array
    {
        return $this->request($user, 'GET', $endpoint, $query);
    }

    /**
     * POST Request an die easybill API.
     *
     * @throws EasybillApiException
     */
    public function post(User $user, string $endpoint, array $data = [], array $query = []): array
    {
        return $this->request($user, 'POST', $endpoint, $query, $data);
    }

    /**
     * PUT Request an die easybill API.
     *
     * @throws EasybillApiException
     */
    public function put(User $user, string $endpoint, array $data = [], array $query = []): array
    {
        return $this->request($user, 'PUT', $endpoint, $query, $data);
    }

    /**
     * DELETE Request an die easybill API.
     *
     * @throws EasybillApiException
     */
    public function delete(User $user, string $endpoint): array
    {
        return $this->request($user, 'DELETE', $endpoint);
    }

    /**
     * @throws EasybillApiException
     */
    protected function request(
        User $user,
        string $method,
        string $endpoint,
        array $query = [],
        array $data = []
    ): array {
        $connection = $this->resolveConnection($user);

        $apiToken = $this->integrationService->getApiToken($connection);

        if (!$apiToken) {
            Log::warning('easybill API: Kein Token für User', ['user_id' => $user->id]);
            throw EasybillApiException::unauthorized();
        }

        $url = self::BASE_URL . $endpoint;

        try {
            $request = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiToken,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ]);

            $response = match ($method) {
                'GET' => $request->get($url, $query),
                'POST' => $request->post($url . $this->buildQueryString($query), $data),
                'PUT' => $request->put($url . $this->buildQueryString($query), $data),
                'DELETE' => $request->delete($url),
                default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
            };

            return $this->handleResponse($response, $connection);
        } catch (EasybillApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('easybill API: Verbindungsfehler', [
                'user_id' => $user->id,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            $this->updateConnectionStatus($connection, 'error', $e->getMessage());

            throw EasybillApiException::connectionError($e->getMessage());
        }
    }

    /**
     * @throws EasybillApiException
     */
    protected function handleResponse(Response $response, IntegrationConnection $connection): array
    {
        $statusCode = $response->status();
        $data = $response->json() ?? [];

        if ($response->successful()) {
            $this->updateConnectionStatus($connection, 'active');
            return $data;
        }

        $this->updateConnectionStatus(
            $connection,
            $statusCode === 401 ? 'error' : 'active',
            $data['message'] ?? $data['error'] ?? null
        );

        Log::warning('easybill API: Fehler-Response', [
            'status_code' => $statusCode,
            'response' => $data,
        ]);

        throw EasybillApiException::fromResponse($statusCode, $data);
    }

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

    protected function buildQueryString(array $query): string
    {
        if (empty($query)) {
            return '';
        }

        return '?' . http_build_query($query);
    }
}
