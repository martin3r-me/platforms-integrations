<?php

namespace Platform\Integrations\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Core\Models\User;
use Platform\Integrations\Exceptions\MossApiException;
use Platform\Integrations\Models\IntegrationConnection;

/**
 * Service für die Kommunikation mit der Moss Public API
 *
 * Stellt authentifizierte HTTP-Requests an die Moss API bereit.
 * Bearer Token wird per OAuth2 Client Credentials Grant geholt und gecacht.
 *
 * Read-Only Endpoints:
 * - GET /v1/expenses — Expenses auflisten
 * - GET /v1/expenses/{id} — Einzelnen Expense abrufen
 * - GET /v1/expense-accounts — Expense Accounts auflisten
 * - GET /v1/suppliers — Suppliers auflisten
 * - GET /v1/suppliers/{id} — Einzelnen Supplier abrufen
 * - GET /v1/users — Users auflisten
 * - GET /v1/bank-accounts — Bank Accounts auflisten
 * - GET /v1/dimensions — Dimensions auflisten
 * - GET /v1/dimensions/{id}/items — Items einer Dimension auflisten
 * - GET /v1/payment-terms — Payment Terms auflisten
 * - POST /v1/files/search-query — Belege (Files) zu Expenses suchen
 * - GET /v1/files/{fileId}/content — Beleg-Datei (Binary) herunterladen
 *
 * @see https://public-api.getmoss.com
 */
class MossApiService
{
    protected MossIntegrationService $integrationService;

    protected ?int $connectionIdOverride = null;

    public function __construct(MossIntegrationService $integrationService)
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
            Log::warning('Moss API: Keine Connection für User', ['user_id' => $user->id]);
            throw MossApiException::noConnection();
        }

        return $connection;
    }

    // =========================================================================
    // PUBLIC API METHODS
    // =========================================================================

    /**
     * Expenses auflisten
     *
     * @param User $user
     * @param array $filters Optional: type, status, date_from, date_to, page, per_page
     * @return array
     *
     * @throws MossApiException
     */
    public function getExpenses(User $user, array $filters = []): array
    {
        return $this->get($user, '/v1/expenses', $filters);
    }

    /**
     * Einzelnen Expense abrufen
     *
     * @throws MossApiException
     */
    public function getExpense(User $user, string $id): array
    {
        return $this->get($user, "/v1/expenses/{$id}");
    }

    /**
     * Expense Accounts auflisten
     *
     * @param array $filters Optional: page, per_page
     * @return array
     *
     * @throws MossApiException
     */
    public function getExpenseAccounts(User $user, array $filters = []): array
    {
        return $this->get($user, '/v1/expense-accounts', $filters);
    }

    /**
     * Suppliers auflisten
     *
     * @param array $filters Optional: page, per_page
     * @return array
     *
     * @throws MossApiException
     */
    public function getSuppliers(User $user, array $filters = []): array
    {
        return $this->get($user, '/v1/suppliers', $filters);
    }

    /**
     * Einzelnen Supplier abrufen
     *
     * @throws MossApiException
     */
    public function getSupplier(User $user, string $id): array
    {
        return $this->get($user, "/v1/suppliers/{$id}");
    }

    /**
     * Users auflisten
     *
     * @param array $filters Optional: page, per_page
     * @return array
     *
     * @throws MossApiException
     */
    public function getUsers(User $user, array $filters = []): array
    {
        return $this->get($user, '/v1/users', $filters);
    }

    /**
     * Bank Accounts auflisten
     *
     * @throws MossApiException
     */
    public function getBankAccounts(User $user): array
    {
        return $this->get($user, '/v1/bank-accounts');
    }

    /**
     * Dimensions auflisten (Kostenstellen etc.)
     *
     * @throws MossApiException
     */
    public function getDimensions(User $user): array
    {
        return $this->get($user, '/v1/dimensions');
    }

    /**
     * Items einer Dimension auflisten
     *
     * @param array $filters Optional: page, per_page
     * @return array
     *
     * @throws MossApiException
     */
    public function getDimensionItems(User $user, string $dimensionId, array $filters = []): array
    {
        return $this->get($user, "/v1/dimensions/{$dimensionId}/items", $filters);
    }

    /**
     * Payment Terms auflisten
     *
     * @param array $filters Optional: page, per_page
     * @return array
     *
     * @throws MossApiException
     */
    public function getPaymentTerms(User $user, array $filters = []): array
    {
        return $this->get($user, '/v1/payment-terms', $filters);
    }

    // =========================================================================
    // FILES / BELEGE
    // =========================================================================

    /**
     * Belege (Files) zu Expenses suchen.
     *
     * POST /v1/files/search-query — liefert die direkt an die angegebenen
     * Expenses angehängten Dateien (max. 100 Expense-IDs). Antwort: `data`
     * = Array aus File-Objekten (id, name, size, createTime, …).
     *
     * @param  array<int, string>  $expenseIds  Expense-UUIDs
     * @throws MossApiException
     */
    public function searchFilesByExpenses(User $user, array $expenseIds, array $pagination = []): array
    {
        $body = ['filters' => ['expenseIds' => array_values($expenseIds)]];
        if (!empty($pagination)) {
            $body['pagination'] = $pagination;
        }

        return $this->request($user, 'POST', '/v1/files/search-query', [], $body);
    }

    /**
     * Beleg-Datei (Binary) herunterladen.
     *
     * GET /v1/files/{fileId}/content — liefert den Beleg als Binary (PDF/Bild).
     * Rückgabe: ['mime' => …, 'data_base64' => …, 'size' => …, 'filename' => …].
     *
     * @throws MossApiException
     */
    public function downloadFile(User $user, string $fileId, string $fallbackMime = 'application/octet-stream'): array
    {
        return $this->getBinary($user, "/v1/files/{$fileId}/content", $fallbackMime);
    }

    // =========================================================================
    // INTERNE HTTP METHODEN
    // =========================================================================

    /**
     * Binary-Download gegen die Moss API (Belege). Spiegelt request(), gibt aber
     * den Roh-Body base64-kodiert zurück statt JSON.
     *
     * @throws MossApiException
     */
    protected function getBinary(User $user, string $path, string $fallbackMime = 'application/octet-stream'): array
    {
        $connection = $this->resolveConnection($user);
        $token = $this->integrationService->getValidAccessToken($connection);

        if (!$token) {
            Log::warning('Moss API: Kein gültiger Token für User (Binary)', ['user_id' => $user->id]);
            throw MossApiException::unauthorized();
        }

        $baseUrl = config('integrations.moss.api_base_url', 'https://public-api.getmoss.com');
        $url = $baseUrl . $path;
        $timeout = config('integrations.moss.timeout.default', 30);
        $connectTimeout = config('integrations.moss.timeout.connect', 10);

        try {
            $response = Http::withToken($token)
                ->timeout($timeout)
                ->connectTimeout($connectTimeout)
                ->withHeaders(['Accept' => '*/*'])
                ->get($url);

            if ($response->status() === 401) {
                $this->integrationService->clearTokenCache($connection);
                $this->updateConnectionStatus($connection, 'error', 'Ungültige Credentials oder Token abgelaufen');
                throw MossApiException::unauthorized();
            }

            if ($response->status() === 429) {
                $retryAfter = (int) $response->header('Retry-After');
                throw MossApiException::rateLimited($retryAfter ?: null);
            }

            if (!$response->successful()) {
                $this->updateConnectionStatus($connection, 'error', 'HTTP ' . $response->status());
                throw MossApiException::connectionError('Datei-Download fehlgeschlagen (HTTP ' . $response->status() . ')');
            }

            $this->updateConnectionStatus($connection, 'active');

            $body = $response->body();
            $mime = $response->header('Content-Type') ?: $fallbackMime;
            $mime = trim(explode(';', $mime)[0]);

            return [
                'mime' => $mime,
                'data_base64' => base64_encode($body),
                'size' => strlen($body),
                'filename' => $this->filenameFromDisposition($response->header('Content-Disposition')),
            ];
        } catch (MossApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Moss API: Verbindungsfehler (Binary)', [
                'user_id' => $user->id,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            $this->updateConnectionStatus($connection, 'error', $e->getMessage());

            throw MossApiException::connectionError($e->getMessage());
        }
    }

    /** Dateiname aus einem Content-Disposition-Header ziehen. */
    private function filenameFromDisposition(?string $disposition): ?string
    {
        if (!$disposition) {
            return null;
        }

        return preg_match('/filename\*?=(?:UTF-8\'\')?"?([^";]+)"?/i', $disposition, $m)
            ? trim($m[1])
            : null;
    }

    /**
     * GET Request an die Moss API
     *
     * @throws MossApiException
     */
    protected function get(User $user, string $path, array $query = []): array
    {
        return $this->request($user, 'GET', $path, $query);
    }

    /**
     * Führt einen HTTP Request an die Moss API aus
     *
     * Moss verwendet Bearer Token per OAuth2 Client Credentials Grant.
     *
     * @throws MossApiException
     */
    protected function request(User $user, string $method, string $path, array $query = [], array $body = []): array
    {
        $connection = $this->resolveConnection($user);

        $token = $this->integrationService->getValidAccessToken($connection);

        if (!$token) {
            Log::warning('Moss API: Kein gültiger Token für User', ['user_id' => $user->id]);
            throw MossApiException::unauthorized();
        }

        $baseUrl = config('integrations.moss.api_base_url', 'https://public-api.getmoss.com');
        $url = $baseUrl . $path;
        $timeout = config('integrations.moss.timeout.default', 30);
        $connectTimeout = config('integrations.moss.timeout.connect', 10);

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
        } catch (MossApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Moss API: Verbindungsfehler', [
                'user_id' => $user->id,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            $this->updateConnectionStatus($connection, 'error', $e->getMessage());

            throw MossApiException::connectionError($e->getMessage());
        }
    }

    /**
     * Verarbeitet die HTTP Response und behandelt Fehler
     *
     * @throws MossApiException
     */
    protected function handleResponse(Response $response, IntegrationConnection $connection): array
    {
        $statusCode = $response->status();
        $data = $response->json() ?? [];

        // Auth-Fehler — Token ungültig oder abgelaufen
        if ($statusCode === 401) {
            // Token-Cache leeren, damit beim nächsten Request ein neuer geholt wird
            $this->integrationService->clearTokenCache($connection);
            $this->updateConnectionStatus($connection, 'error', 'Ungültige Credentials oder Token abgelaufen');
            throw MossApiException::unauthorized();
        }

        // Rate-Limit
        if ($statusCode === 429) {
            $retryAfter = (int) $response->header('Retry-After');
            throw MossApiException::rateLimited($retryAfter ?: null);
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
            $data['message'] ?? $data['error'] ?? null
        );

        Log::warning('Moss API: Fehler-Response', [
            'status_code' => $statusCode,
            'response' => $data,
        ]);

        throw MossApiException::fromResponse($statusCode, $data);
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
