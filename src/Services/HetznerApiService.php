<?php

namespace Platform\Integrations\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Core\Models\User;
use Platform\Integrations\Exceptions\HetznerApiException;
use Platform\Integrations\Models\IntegrationConnection;

/**
 * Service für die Kommunikation mit der Hetzner Cloud API (v1).
 *
 * Auth: Bearer-Token (projektbezogenes API-Token aus der Cloud Console).
 * Base-URL: https://api.hetzner.cloud/v1
 *
 * Besonderheiten der API:
 * - Das Token gilt IMMER für genau ein Projekt — es gibt keinen Projekt-Parameter.
 * - Listen sind seiten-paginiert: page + per_page (max. 50);
 *   Antwort: { <resource>: [...], meta: { pagination: { page, per_page, next_page, last_page, total_entries } } }
 * - Verändernde Aufrufe sind ASYNCHRON: sie liefern ein "action"-Objekt mit
 *   status running|success|error. Der Fortschritt wird über GET /actions/{id}
 *   abgefragt (siehe getAction() / waitForAction()).
 * - Fehler: { error: { code, message, details } }
 *
 * @see https://docs.hetzner.cloud/reference/cloud
 */
class HetznerApiService
{
    /**
     * Server-Aktionen, die die Hetzner-API unter
     * POST /servers/{id}/actions/{action} anbietet.
     */
    public const SERVER_ACTIONS = [
        'poweron', 'poweroff', 'reboot', 'reset', 'shutdown',
        'reset_password', 'enable_rescue', 'disable_rescue', 'rebuild',
        'change_type', 'create_image', 'enable_backup', 'disable_backup',
        'attach_iso', 'detach_iso', 'change_dns_ptr', 'change_protection',
        'request_console', 'attach_to_network', 'detach_from_network',
        'change_alias_ips', 'add_to_placement_group', 'remove_from_placement_group',
    ];

    /**
     * Server-Aktionen, die den Server hart unterbrechen oder Daten zerstören.
     * Werden in den Tools gesondert abgesichert.
     */
    public const DESTRUCTIVE_SERVER_ACTIONS = ['poweroff', 'reset', 'rebuild'];

    protected HetznerIntegrationService $integrationService;

    protected ?int $connectionIdOverride = null;

    /**
     * Optionaler Beobachter für jeden ausgeführten Aufruf.
     *
     * Wird mit (method, path, status, durationMs, headers) aufgerufen — auch bei
     * Erfolg. Für Hetzner besonders nützlich, weil der Header
     * RateLimit-Remaining sonst nirgends sichtbar wird: Damit lässt sich der
     * Budgetverbrauch beobachten, BEVOR das Limit von 3600 Aufrufen je Stunde
     * erreicht ist und Aufrufe zu scheitern beginnen.
     *
     * @var (callable(string, string, int, int, array<string, string>): void)|null
     */
    protected $observer = null;

    public function __construct(HetznerIntegrationService $integrationService)
    {
        $this->integrationService = $integrationService;
    }

    /**
     * Gibt eine Kopie zurück, die jeden Aufruf an den Beobachter meldet.
     *
     * @param callable(string, string, int, int, array<string, string>): void $observer
     */
    public function withObserver(callable $observer): static
    {
        $clone = clone $this;
        $clone->observer = $observer;

        return $clone;
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
     */
    protected function resolveConnection(?User $user): IntegrationConnection
    {
        if ($this->connectionIdOverride) {
            $connection = $user
                ? app(IntegrationConnectionResolver::class)->resolveById($this->connectionIdOverride, $user)
                : IntegrationConnection::with('integration')->find($this->connectionIdOverride);
        } else {
            if (!$user) {
                throw HetznerApiException::noConnection();
            }

            $connection = $this->integrationService->getConnectionForUser($user);
        }

        if (!$connection) {
            Log::warning('Hetzner API: Keine Connection', [
                'user_id' => $user?->id,
                'connection_override' => $this->connectionIdOverride,
            ]);

            throw HetznerApiException::noConnection();
        }

        return $connection;
    }

    // =========================================================================
    // LESENDE RESSOURCEN
    // =========================================================================

    /**
     * @throws HetznerApiException
     */
    public function listServers(?User $user, array $query = []): array
    {
        return $this->get($user, '/servers', $query);
    }

    /**
     * @throws HetznerApiException
     */
    public function getServer(?User $user, int|string $id): array
    {
        return $this->get($user, '/servers/' . $this->segment($id));
    }

    /**
     * Metriken eines Servers abrufen (cpu, disk, network).
     *
     * @throws HetznerApiException
     */
    public function getServerMetrics(?User $user, int|string $id, array $query = []): array
    {
        return $this->get($user, '/servers/' . $this->segment($id) . '/metrics', $query);
    }

    /**
     * @throws HetznerApiException
     */
    public function listServerTypes(?User $user, array $query = []): array
    {
        return $this->get($user, '/server_types', $query);
    }

    /**
     * @throws HetznerApiException
     */
    public function listImages(?User $user, array $query = []): array
    {
        return $this->get($user, '/images', $query);
    }

    /**
     * @throws HetznerApiException
     */
    public function listLocations(?User $user, array $query = []): array
    {
        return $this->get($user, '/locations', $query);
    }

    /**
     * @throws HetznerApiException
     */
    public function listDatacenters(?User $user, array $query = []): array
    {
        return $this->get($user, '/datacenters', $query);
    }

    /**
     * @throws HetznerApiException
     */
    public function listVolumes(?User $user, array $query = []): array
    {
        return $this->get($user, '/volumes', $query);
    }

    /**
     * @throws HetznerApiException
     */
    public function listSshKeys(?User $user, array $query = []): array
    {
        return $this->get($user, '/ssh_keys', $query);
    }

    /**
     * @throws HetznerApiException
     */
    public function listFirewalls(?User $user, array $query = []): array
    {
        return $this->get($user, '/firewalls', $query);
    }

    /**
     * @throws HetznerApiException
     */
    public function listNetworks(?User $user, array $query = []): array
    {
        return $this->get($user, '/networks', $query);
    }

    /**
     * @throws HetznerApiException
     */
    public function listLoadBalancers(?User $user, array $query = []): array
    {
        return $this->get($user, '/load_balancers', $query);
    }

    /**
     * @throws HetznerApiException
     */
    public function listPrimaryIps(?User $user, array $query = []): array
    {
        return $this->get($user, '/primary_ips', $query);
    }

    /**
     * @throws HetznerApiException
     */
    public function listFloatingIps(?User $user, array $query = []): array
    {
        return $this->get($user, '/floating_ips', $query);
    }

    /**
     * @throws HetznerApiException
     */
    public function listCertificates(?User $user, array $query = []): array
    {
        return $this->get($user, '/certificates', $query);
    }

    /**
     * @throws HetznerApiException
     */
    public function listPlacementGroups(?User $user, array $query = []): array
    {
        return $this->get($user, '/placement_groups', $query);
    }

    /**
     * @throws HetznerApiException
     */
    public function listIsos(?User $user, array $query = []): array
    {
        return $this->get($user, '/isos', $query);
    }

    /**
     * DNS-Zonen auflisten.
     *
     * @throws HetznerApiException
     */
    public function listZones(?User $user, array $query = []): array
    {
        return $this->get($user, '/zones', $query);
    }

    /**
     * RRSets (DNS-Einträge) einer Zone auflisten.
     *
     * @throws HetznerApiException
     */
    public function listZoneRrsets(?User $user, int|string $zone, array $query = []): array
    {
        return $this->get($user, '/zones/' . $this->segment($zone) . '/rrsets', $query);
    }

    /**
     * Preisliste des Projekts abrufen.
     *
     * @throws HetznerApiException
     */
    public function getPricing(?User $user): array
    {
        return $this->get($user, '/pricing');
    }

    // =========================================================================
    // AKTIONEN
    // =========================================================================

    /**
     * Aktionen des Projekts auflisten (GET /actions).
     *
     * @throws HetznerApiException
     */
    public function listActions(?User $user, array $query = []): array
    {
        return $this->get($user, '/actions', $query);
    }

    /**
     * Status einer Aktion abfragen (GET /actions/{id}).
     *
     * @throws HetznerApiException
     */
    public function getAction(?User $user, int|string $id): array
    {
        return $this->get($user, '/actions/' . $this->segment($id));
    }

    /**
     * Server-Aktion auslösen — SCHREIBEND
     * (POST /servers/{id}/actions/{action}).
     *
     * Die Antwort enthält ein "action"-Objekt mit status running|success|error.
     *
     * @throws HetznerApiException
     */
    public function runServerAction(?User $user, int|string $id, string $action, array $body = []): array
    {
        $action = trim($action);

        if (!in_array($action, self::SERVER_ACTIONS, true)) {
            throw new HetznerApiException(
                "Unbekannte Server-Aktion \"{$action}\". Erlaubt: " . implode(', ', self::SERVER_ACTIONS),
                400,
                'invalid_input'
            );
        }

        return $this->post($user, '/servers/' . $this->segment($id) . '/actions/' . $this->segment($action), $body);
    }

    /**
     * Wartet, bis eine Aktion abgeschlossen ist (Polling auf GET /actions/{id}).
     *
     * @param int $maxWaitSeconds Obergrenze in Sekunden (Standard 60)
     * @param int $intervalSeconds Abfrageintervall in Sekunden (Standard 2)
     *
     * @throws HetznerApiException
     */
    public function waitForAction(?User $user, int|string $actionId, int $maxWaitSeconds = 60, int $intervalSeconds = 2): array
    {
        $intervalSeconds = max(1, $intervalSeconds);
        $deadline = time() + max(1, $maxWaitSeconds);
        $last = [];

        while (time() < $deadline) {
            $last = $this->getAction($user, $actionId);
            $status = $last['action']['status'] ?? null;

            if ($status !== 'running') {
                return $last;
            }

            sleep($intervalSeconds);
        }

        return $last;
    }

    // =========================================================================
    // GENERISCHER ZUGRIFF
    // =========================================================================

    /**
     * Generischer API-Aufruf auf einen beliebigen Hetzner-Pfad.
     *
     * @throws HetznerApiException
     */
    public function call(?User $user, string $method, string $path, array $query = [], array $body = []): array
    {
        return $this->request($user, strtoupper($method), $this->normalizePath($path), $query, $body);
    }

    /**
     * @throws HetznerApiException
     */
    public function get(?User $user, string $path, array $query = []): array
    {
        return $this->request($user, 'GET', $path, $query);
    }

    /**
     * @throws HetznerApiException
     */
    public function post(?User $user, string $path, array $body = []): array
    {
        return $this->request($user, 'POST', $path, [], $body);
    }

    /**
     * @throws HetznerApiException
     */
    public function put(?User $user, string $path, array $body = []): array
    {
        return $this->request($user, 'PUT', $path, [], $body);
    }

    /**
     * @throws HetznerApiException
     */
    public function delete(?User $user, string $path, array $body = []): array
    {
        return $this->request($user, 'DELETE', $path, [], $body);
    }

    // =========================================================================
    // INTERNE HTTP-METHODEN
    // =========================================================================

    /**
     * Normalisiert einen übergebenen Pfad auf "/pfad" relativ zur Base-URL.
     *
     * @throws HetznerApiException
     */
    protected function normalizePath(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            throw new HetznerApiException('Leerer API-Pfad.', 400, 'invalid_input');
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $path = (string) (parse_url($path, PHP_URL_PATH) ?: '/');
            // Absolute URLs der API enthalten bereits das /v1-Präfix.
            if (str_starts_with($path, '/v1/')) {
                $path = substr($path, 3);
            }
        }

        return '/' . ltrim($path, '/');
    }

    protected function segment(int|string $value): string
    {
        return rawurlencode(trim((string) $value));
    }

    /**
     * Führt einen HTTP-Request gegen die Hetzner Cloud API aus.
     *
     * @throws HetznerApiException
     */
    protected function request(?User $user, string $method, string $path, array $query = [], array $body = []): array
    {
        $connection = $this->resolveConnection($user);
        $token = $this->integrationService->getApiToken($connection);

        if (!$token) {
            Log::warning('Hetzner API: Kein API-Token', ['user_id' => $user?->id]);

            throw HetznerApiException::unauthorized();
        }

        $baseUrl = $this->integrationService->getBaseUrl($connection);
        $url = $baseUrl . $path;
        $timeout = (int) config('integrations.hetzner.timeout.default', 30);
        $connectTimeout = (int) config('integrations.hetzner.timeout.connect', 10);

        try {
            $http = Http::withToken($token)
                ->timeout($timeout)
                ->connectTimeout($connectTimeout)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ]);

            $startedAt = microtime(true);

            $response = match ($method) {
                'GET' => $http->get($url, $query),
                'POST' => $http->post($url, $body),
                'PUT' => $http->put($url, $body),
                'PATCH' => $http->patch($url, $body),
                'DELETE' => $http->delete($url, $body),
                default => throw new HetznerApiException("Nicht unterstützte HTTP-Methode: {$method}", 400, 'invalid_input'),
            };

            $this->notifyObserver($method, $path, $response, $startedAt);

            return $this->handleResponse($response, $connection);
        } catch (HetznerApiException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Hetzner API: Verbindungsfehler', [
                'user_id' => $user?->id,
                'method' => $method,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            $this->updateConnectionStatus($connection, 'error', $e->getMessage());

            throw HetznerApiException::connectionError($e->getMessage());
        }
    }

    /**
     * Verarbeitet die HTTP-Response und behandelt Fehler.
     *
     * @throws HetznerApiException
     */
    protected function handleResponse(Response $response, IntegrationConnection $connection): array
    {
        $statusCode = $response->status();

        if ($statusCode === 401) {
            $this->updateConnectionStatus($connection, 'error', 'Ungültiges Hetzner API-Token');

            throw HetznerApiException::unauthorized();
        }

        if ($statusCode === 403) {
            throw HetznerApiException::forbidden();
        }

        if ($statusCode === 429) {
            // Hetzner liefert die Wartezeit über RateLimit-Reset (Unix-Timestamp).
            $retryAfter = (int) $response->header('Retry-After');

            if (!$retryAfter && ($reset = (int) $response->header('RateLimit-Reset'))) {
                $retryAfter = max(0, $reset - time());
            }

            throw HetznerApiException::rateLimited($retryAfter ?: null);
        }

        if ($response->successful()) {
            $this->updateConnectionStatus($connection, 'active');

            if ($statusCode === 204 || trim($response->body()) === '') {
                return ['success' => true, 'status' => $statusCode];
            }

            $data = $response->json();

            if (!is_array($data)) {
                // z.B. GET /zones/{id}/zonefile liefert Plaintext.
                return ['content' => $response->body(), 'status' => $statusCode];
            }

            return $data;
        }

        $data = $response->json();
        $data = is_array($data) ? $data : [];

        Log::warning('Hetzner API: Fehler-Response', [
            'status_code' => $statusCode,
            'response' => $data,
        ]);

        throw HetznerApiException::fromResponse($statusCode, $data);
    }

    /**
     * Meldet einen abgeschlossenen Aufruf an den Beobachter, falls einer gesetzt ist.
     *
     * Ein Fehler im Beobachter darf den Aufruf nie zum Scheitern bringen —
     * Protokollierung ist Beiwerk, nicht Zweck.
     */
    protected function notifyObserver(string $method, string $path, Response $response, float $startedAt): void
    {
        if (!$this->observer) {
            return;
        }

        try {
            ($this->observer)(
                $method,
                $path,
                $response->status(),
                (int) round((microtime(true) - $startedAt) * 1000),
                [
                    'Retry-After' => (string) $response->header('Retry-After'),
                    'RateLimit-Remaining' => (string) $response->header('RateLimit-Remaining'),
                    'RateLimit-Reset' => (string) $response->header('RateLimit-Reset'),
                ],
            );
        } catch (\Throwable $e) {
            Log::warning('Hetzner API: Beobachter fehlgeschlagen', ['error' => $e->getMessage()]);
        }
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
