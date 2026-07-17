<?php

namespace Platform\Integrations\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Core\Models\User;
use Platform\Integrations\Exceptions\NectaApiException;
use Platform\Integrations\Models\IntegrationConnection;

/**
 * Service für die necta.one API (v1) — die höhere REST/CRUD-API.
 *
 * Auth:      API-Key — Header: X-Api-Key: <api_key>
 * Base-URL:  {host}/api/v1/{tenantId}   (host aus credentials.base_url, tenantId aus credentials.tenant_id)
 * Umfang:    echtes REST — GET/POST/PUT/PATCH/DELETE, Ressourcen mit /{id}-Detailrouten
 *            (customers, invoices, orders, meals, stocks, supplier-items, purchase-orders, …).
 *
 * Abgrenzung: Die Raw-API (/rawapi, read-only, flach) wird von {@see NectaApiService}
 * bedient. Beide teilen sich dieselbe Connection (api_key + base_url); die v1-API
 * benötigt zusätzlich tenant_id.
 */
class NectaApiV1Service
{
    protected NectaIntegrationService $integrationService;

    protected ?int $connectionIdOverride = null;

    public function __construct(NectaIntegrationService $integrationService)
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

    protected function resolveConnection(User $user): IntegrationConnection
    {
        if ($this->connectionIdOverride) {
            $connection = app(IntegrationConnectionResolver::class)->resolveById($this->connectionIdOverride, $user);
        } else {
            $connection = $this->integrationService->getConnectionForUser($user);
        }

        if (!$connection) {
            Log::warning('necta.one API v1: Keine Connection für User', ['user_id' => $user->id]);
            throw NectaApiException::noConnection();
        }

        return $connection;
    }

    /**
     * Baut die tenant-bezogene Basis: {host}/api/v1/{tenantId}
     *
     * @throws NectaApiException
     */
    protected function tenantBase(IntegrationConnection $connection): string
    {
        $host = $this->integrationService->getBaseUrl($connection);
        if (!$host) {
            throw NectaApiException::missingBaseUrl();
        }

        $tenantId = $this->integrationService->getTenantId($connection);
        if (!$tenantId) {
            throw new NectaApiException(
                'Keine tenant_id in den Connection-Credentials hinterlegt (für die necta.one API v1 erforderlich).',
                400,
                'MISSING_TENANT_ID'
            );
        }

        return $host . '/api/v1/' . rawurlencode($tenantId);
    }

    // =========================================================================
    // KOMFORT-READS (Kernressourcen)
    // =========================================================================

    /** GET /api/v1/{tenantId}/customers */
    public function listCustomers(User $user, array $query = []): array
    {
        return $this->get($user, '/customers', $query);
    }

    /** GET /api/v1/{tenantId}/invoices */
    public function listInvoices(User $user, array $query = []): array
    {
        return $this->get($user, '/invoices', $query);
    }

    /** GET /api/v1/{tenantId}/orders */
    public function listOrders(User $user, array $query = []): array
    {
        return $this->get($user, '/orders', $query);
    }

    // =========================================================================
    // GENERISCHER REST-ZUGRIFF
    // =========================================================================

    /**
     * Beliebiger v1-Aufruf. $path relativ zur Tenant-Basis, z.B. "/customers"
     * oder "/customer-contacts/{id}".
     *
     * @throws NectaApiException
     */
    public function call(User $user, string $method, string $path, array $payload = []): array
    {
        $method = strtoupper($method);

        return match ($method) {
            'GET' => $this->get($user, $path, $payload),
            'POST' => $this->request($user, 'POST', $path, [], $payload),
            'PUT' => $this->request($user, 'PUT', $path, [], $payload),
            'PATCH' => $this->request($user, 'PATCH', $path, [], $payload),
            'DELETE' => $this->request($user, 'DELETE', $path, $payload),
            default => throw new NectaApiException("Nicht unterstützte HTTP-Methode: {$method}", 400, 'BAD_METHOD'),
        };
    }

    /** @throws NectaApiException */
    public function get(User $user, string $path, array $query = []): array
    {
        return $this->request($user, 'GET', $path, $query);
    }

    /**
     * Ruft einen Endpunkt anhand seines vollständigen Spec-Pfad-Templates auf,
     * z.B. "/api/v1/{tenantId}/customers" oder tenant-los "/api/v1/apikeys".
     * {tenantId} wird aus den Credentials ersetzt. Basis: {host}{path}.
     *
     * @throws NectaApiException
     */
    public function callSpec(User $user, string $method, string $pathTemplate, array $query = [], array $data = []): array
    {
        $connection = $this->resolveConnection($user);

        $host = $this->integrationService->getBaseUrl($connection);
        if (!$host) {
            throw NectaApiException::missingBaseUrl();
        }

        $path = $pathTemplate;
        if (str_contains($path, '{tenantId}')) {
            $tenantId = $this->integrationService->getTenantId($connection);
            if (!$tenantId) {
                throw new NectaApiException(
                    'Keine tenant_id hinterlegt (für diesen v1-Endpunkt erforderlich).',
                    400,
                    'MISSING_TENANT_ID'
                );
            }
            $path = str_replace('{tenantId}', rawurlencode($tenantId), $path);
        }

        $method = strtoupper($method);
        $url = $host . '/' . ltrim($path, '/');
        $query = self::normalizeQuery($query);

        $apiKey = $this->integrationService->getApiKey($connection);
        if (!$apiKey) {
            throw NectaApiException::unauthorized();
        }

        try {
            $request = Http::withHeaders([
                'X-Api-Key' => $apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ]);

            $response = match ($method) {
                'GET' => $request->get($url, $query),
                'POST' => $request->post($url, $data),
                'PUT' => $request->put($url, $data),
                'PATCH' => $request->patch($url, $data),
                'DELETE' => $request->delete($url, $query),
                default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
            };

            return $this->handleResponse($response, $connection);
        } catch (NectaApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('necta.one API v1: Verbindungsfehler', [
                'connection_id' => $connection->id,
                'path' => $pathTemplate,
                'error' => $e->getMessage(),
            ]);
            $this->updateConnectionStatus($connection, 'error', $e->getMessage());

            throw NectaApiException::connectionError($e->getMessage());
        }
    }

    /**
     * @throws NectaApiException
     */
    protected function request(User $user, string $method, string $path, array $query = [], array $data = []): array
    {
        $connection = $this->resolveConnection($user);

        $apiKey = $this->integrationService->getApiKey($connection);
        if (!$apiKey) {
            throw NectaApiException::unauthorized();
        }

        $url = $this->tenantBase($connection) . '/' . ltrim($path, '/');
        $query = self::normalizeQuery($query);

        try {
            $request = Http::withHeaders([
                'X-Api-Key' => $apiKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ]);

            $response = match ($method) {
                'GET' => $request->get($url, $query),
                'POST' => $request->post($url, $data),
                'PUT' => $request->put($url, $data),
                'PATCH' => $request->patch($url, $data),
                'DELETE' => $request->delete($url, $query),
                default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
            };

            return $this->handleResponse($response, $connection);
        } catch (NectaApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('necta.one API v1: Verbindungsfehler', [
                'connection_id' => $connection->id,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            $this->updateConnectionStatus($connection, 'error', $e->getMessage());

            throw NectaApiException::connectionError($e->getMessage());
        }
    }

    /**
     * @throws NectaApiException
     */
    protected function handleResponse(Response $response, IntegrationConnection $connection): array
    {
        $statusCode = $response->status();
        $data = $response->json() ?? [];

        if ($response->successful()) {
            $this->updateConnectionStatus($connection, 'active');

            return is_array($data) ? $data : ['data' => $data];
        }

        $errorMsg = is_array($data) ? ($data['message'] ?? $data['error'] ?? null) : null;
        if (is_array($errorMsg)) {
            $errorMsg = json_encode($errorMsg, JSON_UNESCAPED_UNICODE);
        } elseif ($errorMsg !== null && !is_string($errorMsg)) {
            $errorMsg = (string) $errorMsg;
        }

        // 401 (Key) und 403 (Tenant-Mismatch) markieren die Connection als defekt.
        $this->updateConnectionStatus(
            $connection,
            in_array($statusCode, [401, 403], true) ? 'error' : 'active',
            $errorMsg
        );

        Log::warning('necta.one API v1: Fehler-Response', [
            'connection_id' => $connection->id,
            'status_code' => $statusCode,
        ]);

        throw NectaApiException::fromResponse($statusCode, is_array($data) ? $data : [], $response->body());
    }

    protected function updateConnectionStatus(IntegrationConnection $connection, string $status, ?string $error = null): void
    {
        $connection->status = $status;
        $connection->last_error = $error;
        $connection->last_tested_at = now();
        $connection->save();
    }

    /**
     * Normalisiert Query-Parameter: Booleans → "true"/"false" (statt Laravels 1/0),
     * damit .NET-basierte APIs wie necta sie korrekt als bool parsen (sonst 400).
     *
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    protected static function normalizeQuery(array $query): array
    {
        foreach ($query as $key => $value) {
            if (is_bool($value)) {
                $query[$key] = $value ? 'true' : 'false';
            }
        }

        return $query;
    }

    // =========================================================================
    // FELD-PROJEKTION (client-seitig) — reduziert riesige Antworten
    // =========================================================================

    /**
     * Reduziert eine Response auf die gewünschten Felder. Unterstützt Dot-Notation
     * für verschachtelte Felder (z.B. "customer.customerNumber"). Erkennt automatisch
     * Listen (root oder unter result/items/data/content) und projiziert deren Einträge,
     * behält aber Paginierungs-Metadaten (page/totalCount/hasMore) bei. Bei einem
     * Einzelobjekt wird dieses projiziert.
     *
     * @param array<int, string> $fields
     */
    public static function projectFields(mixed $response, array $fields): mixed
    {
        return \Platform\Integrations\Support\FieldProjection::apply($response, $fields);
    }
}
