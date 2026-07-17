<?php

namespace Platform\Integrations\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Core\Models\User;
use Platform\Integrations\Exceptions\NectaApiException;
use Platform\Integrations\Models\IntegrationConnection;

/**
 * Service für die Kommunikation mit der necta.one Raw-API (/rawapi/*).
 *
 * Auth:      API-Key — Header: X-Api-Key: <api_key>
 * Base-URL:  pro Connection (credentials.base_url), API-Prefix /rawapi wird hier gesetzt
 * Umfang:    Read-Only — die Raw-API stellt ausschließlich GET-Endpunkte bereit
 *            (300+ Ressourcen: products, customers, orders, suppliers, invoices, …).
 * Paging:    Alle Endpunkte sind pflicht-paginiert über pageNumber (1-basiert) + pageSize.
 *
 * Dieser Service ist connection-zentriert: jede Methode bekommt die
 * IntegrationConnection übergeben, gegen die gearbeitet wird. Damit funktioniert
 * er sowohl im User-Kontext (UI) als auch in team-scoped Jobs (kein User nötig).
 */
class NectaApiService
{
    protected const API_PREFIX = '/rawapi';

    /** Default-Seitengröße, falls der Aufrufer keine angibt. */
    protected const DEFAULT_PAGE_SIZE = 50;

    protected NectaIntegrationService $integrationService;

    /** Optionaler Connection-Override für den User-Kontext (z.B. MCP-Tools). */
    protected ?int $connectionIdOverride = null;

    public function __construct(NectaIntegrationService $integrationService)
    {
        $this->integrationService = $integrationService;
    }

    // =========================================================================
    // USER-KONTEXT (MCP-Tools / UI) — Connection-Auflösung
    // =========================================================================

    /**
     * Gibt eine Kopie dieses Services zurück, die eine spezifische Connection
     * verwendet (analog EasybillApiService::forConnection()).
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
     * Löst die zu verwendende Connection für einen User auf: entweder die per
     * forConnection() gesetzte, sonst die Default-Connection des Users.
     *
     * @throws NectaApiException
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
            Log::warning('necta.one API: Keine Connection für User', ['user_id' => $user->id]);
            throw NectaApiException::noConnection();
        }

        return $connection;
    }

    /**
     * User-zentrierter Ressourcen-Zugriff (für MCP-Tools): löst die Connection
     * des Users auf und liest eine Seite der Ressource.
     *
     * @throws NectaApiException
     */
    public function listForUser(
        User $user,
        string $resource,
        int $pageNumber = 1,
        int $pageSize = self::DEFAULT_PAGE_SIZE,
        array $filters = []
    ): array {
        return $this->list($this->resolveConnection($user), $resource, $pageNumber, $pageSize, $filters);
    }

    // =========================================================================
    // GENERISCHER RESSOURCEN-ZUGRIFF (Connection-zentriert)
    // =========================================================================

    /**
     * Liest eine einzelne Seite einer beliebigen Raw-API-Ressource.
     *
     * Der Ressourcen-Slug wird gegen die {@see NectaResource}-Registry validiert
     * (alle 417 Raw-API-Ressourcen). Wer bewusst einen nicht registrierten
     * Endpunkt ansprechen will, nutzt direkt {@see self::get()} als Escape-Hatch.
     *
     * @param string $resource z.B. NectaResource::PRODUCTS ('products'), 'customers', 'agencys'
     * @param array  $filters  zusätzliche Query-Filter — siehe NectaResource::filters($resource)
     *
     * @throws NectaApiException wenn der Ressourcen-Slug unbekannt ist
     */
    public function list(
        IntegrationConnection $connection,
        string $resource,
        int $pageNumber = 1,
        int $pageSize = self::DEFAULT_PAGE_SIZE,
        array $filters = []
    ): array {
        $resource = ltrim($resource, '/');

        if (!NectaResource::exists($resource)) {
            throw new NectaApiException(
                "Unbekannte necta.one Raw-API-Ressource: \"{$resource}\". "
                . 'Gültige Slugs siehe NectaResource::all().',
                400,
                'UNKNOWN_RESOURCE'
            );
        }

        $query = array_merge($filters, [
            'pageNumber' => max(1, $pageNumber),
            'pageSize' => max(1, $pageSize),
        ]);

        return $this->get($connection, '/' . $resource, $query);
    }

    /**
     * Alle gültigen Raw-API-Ressourcen-Slugs (417 Stück).
     *
     * @return array<int, string>
     */
    public function resources(): array
    {
        return NectaResource::all();
    }

    /**
     * Dokumentierte Query-Filter einer Ressource (ohne pageNumber/pageSize).
     *
     * @return array<int, string>
     */
    public function filtersFor(string $resource): array
    {
        return NectaResource::filters(ltrim($resource, '/'));
    }

    /**
     * Läuft alle Seiten einer Ressource durch und gibt die zusammengeführten
     * Einträge zurück. Nur für überschaubare Datenmengen gedacht — für große
     * Ressourcen seitenweise über list() iterieren.
     *
     * @return array<int, mixed>
     */
    public function listAll(
        IntegrationConnection $connection,
        string $resource,
        int $pageSize = self::DEFAULT_PAGE_SIZE,
        array $filters = [],
        int $maxPages = 100
    ): array {
        $items = [];
        $page = 1;

        do {
            $response = $this->list($connection, $resource, $page, $pageSize, $filters);
            $chunk = $this->extractItems($response);
            $items = array_merge($items, $chunk);
            $page++;
        } while (count($chunk) === $pageSize && $page <= $maxPages);

        return $items;
    }

    // =========================================================================
    // KOMFORT-METHODEN FÜR KERN-RESSOURCEN
    // =========================================================================

    /** GET /rawapi/products */
    public function listProducts(IntegrationConnection $connection, int $pageNumber = 1, int $pageSize = self::DEFAULT_PAGE_SIZE, array $filters = []): array
    {
        return $this->list($connection, 'products', $pageNumber, $pageSize, $filters);
    }

    /** GET /rawapi/customers */
    public function listCustomers(IntegrationConnection $connection, int $pageNumber = 1, int $pageSize = self::DEFAULT_PAGE_SIZE, array $filters = []): array
    {
        return $this->list($connection, 'customers', $pageNumber, $pageSize, $filters);
    }

    /** GET /rawapi/orders */
    public function listOrders(IntegrationConnection $connection, int $pageNumber = 1, int $pageSize = self::DEFAULT_PAGE_SIZE, array $filters = []): array
    {
        return $this->list($connection, 'orders', $pageNumber, $pageSize, $filters);
    }

    /** GET /rawapi/suppliers */
    public function listSuppliers(IntegrationConnection $connection, int $pageNumber = 1, int $pageSize = self::DEFAULT_PAGE_SIZE, array $filters = []): array
    {
        return $this->list($connection, 'suppliers', $pageNumber, $pageSize, $filters);
    }

    /** GET /rawapi/invoices */
    public function listInvoices(IntegrationConnection $connection, int $pageNumber = 1, int $pageSize = self::DEFAULT_PAGE_SIZE, array $filters = []): array
    {
        return $this->list($connection, 'invoices', $pageNumber, $pageSize, $filters);
    }

    // =========================================================================
    // INTERNE HTTP METHODE (Raw-API ist Read-Only → nur GET)
    // =========================================================================

    /** @throws NectaApiException */
    public function get(IntegrationConnection $connection, string $endpoint, array $query = []): array
    {
        $apiKey = $this->integrationService->getApiKey($connection);
        if (!$apiKey) {
            throw NectaApiException::unauthorized();
        }

        $baseUrl = $this->integrationService->getBaseUrl($connection);
        if (!$baseUrl) {
            throw NectaApiException::missingBaseUrl();
        }

        $url = $baseUrl . self::API_PREFIX . $endpoint;

        try {
            $response = Http::withHeaders([
                'X-Api-Key' => $apiKey,
                'Accept' => 'application/json',
            ])->get($url, self::normalizeQuery($query));

            return $this->handleResponse($response, $connection);
        } catch (NectaApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('necta.one API: Verbindungsfehler', [
                'connection_id' => $connection->id,
                'endpoint' => $endpoint,
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

            return is_array($data) ? $data : [];
        }

        $errorMsg = is_array($data) ? ($data['message'] ?? $data['error'] ?? null) : null;
        if (is_array($errorMsg)) {
            $errorMsg = json_encode($errorMsg, JSON_UNESCAPED_UNICODE);
        } elseif ($errorMsg !== null && !is_string($errorMsg)) {
            $errorMsg = (string) $errorMsg;
        }

        // 401 (ungültiger Key) und 403 (fehlende Rechte) markieren die Connection
        // als defekt; fachliche/transiente Fehler lassen sie aktiv.
        $this->updateConnectionStatus(
            $connection,
            in_array($statusCode, [401, 403], true) ? 'error' : 'active',
            $errorMsg
        );

        Log::warning('necta.one API: Fehler-Response', [
            'connection_id' => $connection->id,
            'status_code' => $statusCode,
        ]);

        throw NectaApiException::fromResponse($statusCode, is_array($data) ? $data : [], $response->body());
    }

    /**
     * Extrahiert die Einträge aus einer Raw-API-Antwort, unabhängig davon ob die
     * Seite direkt als Array oder unter einem Wrapper-Key (data/items/results)
     * geliefert wird.
     *
     * @return array<int, mixed>
     */
    protected function extractItems(array $response): array
    {
        if (array_is_list($response)) {
            return $response;
        }

        foreach (['data', 'items', 'results', 'content'] as $key) {
            if (isset($response[$key]) && is_array($response[$key])) {
                return $response[$key];
            }
        }

        return [];
    }

    /**
     * Query-Booleans → "true"/"false" (statt Laravels 1/0), damit necta sie
     * korrekt als bool parst (sonst 400).
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
