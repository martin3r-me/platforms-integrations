<?php

namespace Platform\Integrations\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Core\Models\User;
use Platform\Integrations\Exceptions\DedefleetApiException;
use Platform\Integrations\Models\IntegrationConnection;

/**
 * Service für die Kommunikation mit der DedeFleet REST-API (Ortung & Tourenplanung).
 *
 * Auth:      Bearer-Dauertoken — Authorization: Bearer <api_key>
 * Base-URL:  fix — https://ortung.dedefleet.de/data/api/2
 * Stil:      RPC — /{Resource}/{Action}. List-Endpunkte sind teils GET, teils POST
 *            (mit Filter-Body); schreibende Aktionen (Create/Update/Delete/Assign/…) sind POST.
 *
 * User-zentriert (analog EasybillApiService): jede Methode bekommt den User, die
 * Connection wird aufgelöst. forConnection() erlaubt eine spezifische Connection.
 */
class DedefleetApiService
{
    public const BASE_URL = 'https://ortung.dedefleet.de/data/api/2';

    protected DedefleetIntegrationService $integrationService;

    protected ?int $connectionIdOverride = null;

    public function __construct(DedefleetIntegrationService $integrationService)
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
            Log::warning('DedeFleet API: Keine Connection für User', ['user_id' => $user->id]);
            throw DedefleetApiException::noConnection();
        }

        return $connection;
    }

    // =========================================================================
    // KOMFORT-READS (Stammdaten / Listen)
    // =========================================================================

    /** GET /Customer/List */
    public function listCustomers(User $user, array $query = []): array
    {
        return $this->get($user, '/Customer/List', $query);
    }

    /** GET /Location/List */
    public function listLocations(User $user, array $query = []): array
    {
        return $this->get($user, '/Location/List', $query);
    }

    /** GET /Employee/List */
    public function listEmployees(User $user, array $query = []): array
    {
        return $this->get($user, '/Employee/List', $query);
    }

    /** GET /VehicleProfile/List */
    public function listVehicleProfiles(User $user, array $query = []): array
    {
        return $this->get($user, '/VehicleProfile/List', $query);
    }

    /** GET /Order/ListUnassigned */
    public function listUnassignedOrders(User $user, array $query = []): array
    {
        return $this->get($user, '/Order/ListUnassigned', $query);
    }

    /** POST /Tour/List — Touren auflisten (Filter im Body, z.B. Datumsbereich). */
    public function listTours(User $user, array $filter = []): array
    {
        return $this->post($user, '/Tour/List', $filter);
    }

    /** GET /TrackingObject/ListCurrentData — aktuelle GPS-Positionen der Ortungsobjekte. */
    public function listTrackingCurrentData(User $user, array $query = []): array
    {
        return $this->get($user, '/TrackingObject/ListCurrentData', $query);
    }

    /** POST /Order/Get — Auftrag per GUID/Body abrufen. */
    public function getOrder(User $user, array $body): array
    {
        return $this->post($user, '/Order/Get', $body);
    }

    /** POST /Tour/Get — Tour per GUID/Body abrufen. */
    public function getTour(User $user, array $body): array
    {
        return $this->post($user, '/Tour/Get', $body);
    }

    // =========================================================================
    // TOURENPLANUNG — SCHREIBENDE WORKFLOW-AKTIONEN (Wiki Steps 1–5)
    // =========================================================================

    /** Step 1 — POST /Order/Create */
    public function createOrder(User $user, array $data): array
    {
        return $this->post($user, '/Order/Create', $data);
    }

    /** Step 5 — POST /Order/Update */
    public function updateOrder(User $user, array $data): array
    {
        return $this->post($user, '/Order/Update', $data);
    }

    /** Step 5 — POST /Order/Delete */
    public function deleteOrder(User $user, array $data): array
    {
        return $this->post($user, '/Order/Delete', $data);
    }

    /** Step 3 — POST /Order/Assign (ein Auftrag → Tour) */
    public function assignOrder(User $user, array $data): array
    {
        return $this->post($user, '/Order/Assign', $data);
    }

    /** Step 3 — POST /Order/AssignBulk (mehrere Aufträge → Tour) */
    public function assignOrdersBulk(User $user, array $data): array
    {
        return $this->post($user, '/Order/AssignBulk', $data);
    }

    /** Step 5 — POST /Order/Unassign (Auftrag aus Tour lösen) */
    public function unassignOrder(User $user, array $data): array
    {
        return $this->post($user, '/Order/Unassign', $data);
    }

    /** Step 2 — POST /Tour/Create */
    public function createTour(User $user, array $data): array
    {
        return $this->post($user, '/Tour/Create', $data);
    }

    /** Step 2 — POST /Tour/CreateFromTemplate */
    public function createTourFromTemplate(User $user, array $data): array
    {
        return $this->post($user, '/Tour/CreateFromTemplate', $data);
    }

    /** Step 4 — POST /Tour/Reorder (Reihenfolge der Aufträge in der Tour setzen) */
    public function reorderTour(User $user, array $data): array
    {
        return $this->post($user, '/Tour/Reorder', $data);
    }

    /** Step 4 — POST /Tour/Optimize (eine Tour optimieren) */
    public function optimizeTour(User $user, array $data): array
    {
        return $this->post($user, '/Tour/Optimize', $data);
    }

    /** Step 4 — POST /Tour/ChangeStatus (Planning/Released/Completed) */
    public function changeTourStatus(User $user, array $data): array
    {
        return $this->post($user, '/Tour/ChangeStatus', $data);
    }

    /** Step 4 — POST /Tour/SetLockState (Touren sperren/entsperren) */
    public function setTourLockState(User $user, array $data): array
    {
        return $this->post($user, '/Tour/SetLockState', $data);
    }

    // =========================================================================
    // GENERISCHER RPC-ZUGRIFF
    // =========================================================================

    /**
     * Ruft einen beliebigen DedeFleet-Endpunkt auf (Escape-Hatch für alle
     * {Resource}/{Action}-Kombinationen der Swagger-Spec).
     *
     * @throws DedefleetApiException
     */
    public function call(User $user, string $method, string $endpoint, array $payload = []): array
    {
        $method = strtoupper($method);

        return $method === 'GET'
            ? $this->get($user, $endpoint, $payload)
            : $this->post($user, $endpoint, $payload);
    }

    /** @throws DedefleetApiException */
    public function get(User $user, string $endpoint, array $query = []): array
    {
        return $this->request($user, 'GET', $endpoint, $query);
    }

    /** @throws DedefleetApiException */
    public function post(User $user, string $endpoint, array $data = []): array
    {
        return $this->request($user, 'POST', $endpoint, [], $data);
    }

    // =========================================================================
    // INTERNE HTTP-METHODE
    // =========================================================================

    /**
     * @throws DedefleetApiException
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
            throw DedefleetApiException::unauthorized();
        }

        $url = self::BASE_URL . '/' . ltrim($endpoint, '/');

        // Konsistenz zur Plattform: Aufrufer geben ISO-8601-Datumsangaben; hier
        // werden sie ins DedeFleet-Format (DD.MM.YYYY [HH:mm[:ss]]) übersetzt.
        $query = self::convertIsoDatesDeep($query);
        $data = self::convertIsoDatesDeep($data);

        try {
            $request = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiToken,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ]);

            $response = match ($method) {
                'GET' => $request->get($url, self::normalizeQuery($query)),
                'POST' => $request->post($url, $data),
                default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
            };

            return $this->handleResponse($response, $connection);
        } catch (DedefleetApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('DedeFleet API: Verbindungsfehler', [
                'connection_id' => $connection->id,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            $this->updateConnectionStatus($connection, 'error', $e->getMessage());

            throw DedefleetApiException::connectionError($e->getMessage());
        }
    }

    /**
     * @throws DedefleetApiException
     */
    protected function handleResponse(Response $response, IntegrationConnection $connection): array
    {
        $statusCode = $response->status();
        $data = $response->json() ?? [];

        if ($response->successful()) {
            $this->updateConnectionStatus($connection, 'active');

            return is_array($data) ? $data : ['data' => $data];
        }

        $errorMsg = is_array($data) ? ($data['message'] ?? $data['error'] ?? $data['Message'] ?? null) : null;
        if (is_array($errorMsg)) {
            $errorMsg = json_encode($errorMsg, JSON_UNESCAPED_UNICODE);
        } elseif ($errorMsg !== null && !is_string($errorMsg)) {
            $errorMsg = (string) $errorMsg;
        }

        // 401/403 markieren die Connection als defekt; fachliche Fehler lassen sie aktiv.
        $this->updateConnectionStatus(
            $connection,
            in_array($statusCode, [401, 403], true) ? 'error' : 'active',
            $errorMsg
        );

        Log::warning('DedeFleet API: Fehler-Response', [
            'connection_id' => $connection->id,
            'status_code' => $statusCode,
        ]);

        throw DedefleetApiException::fromResponse($statusCode, is_array($data) ? $data : []);
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

    /**
     * Query-Booleans → "true"/"false" (statt Laravels 1/0), damit die .NET-basierte
     * DedeFleet-API sie korrekt als bool parst (sonst 400).
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

    /**
     * Wandelt ISO-8601-Datums-/Zeitstrings rekursiv ins DedeFleet-Format um.
     * Die Präzision des Eingabewerts bleibt erhalten:
     *   2026-07-23              → 23.07.2026
     *   2026-07-23T09:00        → 23.07.2026 09:00
     *   2026-07-23T09:00:00(Z)  → 23.07.2026 09:00:00
     * Nicht-ISO-Strings (z.B. reine Zeiten "HH:MM") bleiben unverändert.
     *
     * @param mixed $value
     * @return mixed
     */
    protected static function convertIsoDatesDeep(mixed $value): mixed
    {
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = self::convertIsoDatesDeep($v);
            }

            return $value;
        }

        if (is_string($value)) {
            return self::isoToDedefleetDate($value);
        }

        return $value;
    }

    protected static function isoToDedefleetDate(string $s): string
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[T ](\d{2}):(\d{2})(?::(\d{2}))?)?(?:\.\d+)?(?:Z|[+-]\d{2}:?\d{2})?$/', $s, $m)) {
            return $s;
        }

        $date = "{$m[3]}.{$m[2]}.{$m[1]}";

        if (!isset($m[4])) {
            return $date;
        }

        $time = "{$m[4]}:{$m[5]}" . (isset($m[6]) && $m[6] !== '' ? ":{$m[6]}" : '');

        return "{$date} {$time}";
    }
}
