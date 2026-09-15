<?php

namespace Platform\Integrations\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Core\Models\User;
use Platform\Integrations\Exceptions\ForgeApiException;
use Platform\Integrations\Models\IntegrationConnection;

/**
 * Service für die Kommunikation mit der Laravel Forge API (v2).
 *
 * Auth: Bearer-Token (persönliches API-Token aus dem Forge-Profil).
 * Base-URL: https://forge.laravel.com/api
 *
 * Besonderheiten der v2-API:
 * - Fast alle Ressourcen sind organisationsbezogen: /orgs/{organization}/...
 *   Die Organisation wird über den Slug adressiert und kann pro Connection als
 *   Default hinterlegt werden (credentials.organization).
 * - Listen sind CURSOR-paginiert: page[size] + page[cursor];
 *   Antwort-Envelope: { data: [...], meta: { per_page, next_cursor, prev_cursor }, links: {...} }
 * - Einzelressourcen liefern { data: {...} }.
 *
 * @see https://laravel.com/forge/docs/api-reference/introduction
 */
class ForgeApiService
{
    protected ForgeIntegrationService $integrationService;

    protected ?int $connectionIdOverride = null;

    public function __construct(ForgeIntegrationService $integrationService)
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
     * Ohne User (Scheduler/CLI) ist nur der Weg über eine explizite
     * connection_id möglich — analog zu PlausibleApiService.
     */
    protected function resolveConnection(?User $user): IntegrationConnection
    {
        if ($this->connectionIdOverride) {
            $connection = $user
                ? app(IntegrationConnectionResolver::class)->resolveById($this->connectionIdOverride, $user)
                : IntegrationConnection::with('integration')->find($this->connectionIdOverride);
        } else {
            if (!$user) {
                throw ForgeApiException::noConnection();
            }

            $connection = $this->integrationService->getConnectionForUser($user);
        }

        if (!$connection) {
            Log::warning('Forge API: Keine Connection', [
                'user_id' => $user?->id,
                'connection_override' => $this->connectionIdOverride,
            ]);

            throw ForgeApiException::noConnection();
        }

        return $connection;
    }

    /**
     * Ermittelt die zu verwendende Organisation: explizites Argument gewinnt,
     * sonst die in der Connection hinterlegte Standard-Organisation.
     *
     * @throws ForgeApiException wenn keine Organisation ermittelt werden kann
     */
    public function resolveOrganization(?User $user, ?string $organization = null): string
    {
        $organization = $organization !== null ? trim($organization) : '';

        if ($organization !== '') {
            return $organization;
        }

        $connection = $this->resolveConnection($user);
        $default = $this->integrationService->getDefaultOrganization($connection);

        if (!$default) {
            throw ForgeApiException::missingOrganization();
        }

        return $default;
    }

    // =========================================================================
    // PUBLIC API METHODS
    // =========================================================================

    /**
     * Aktuellen API-Benutzer abrufen (GET /me).
     *
     * @throws ForgeApiException
     */
    public function getMe(?User $user = null): array
    {
        return $this->get($user, '/me');
    }

    /**
     * Organisationen auflisten (GET /orgs).
     *
     * @throws ForgeApiException
     */
    public function listOrganizations(?User $user = null, array $query = []): array
    {
        return $this->get($user, '/orgs', $query);
    }

    /**
     * Einzelne Organisation abrufen (GET /orgs/{organization}).
     *
     * @throws ForgeApiException
     */
    public function getOrganization(?User $user, ?string $organization = null): array
    {
        $org = $this->resolveOrganization($user, $organization);

        return $this->get($user, '/orgs/' . $this->segment($org));
    }

    /**
     * Server einer Organisation auflisten (GET /orgs/{organization}/servers).
     *
     * @throws ForgeApiException
     */
    public function listServers(?User $user, ?string $organization = null, array $query = []): array
    {
        $org = $this->resolveOrganization($user, $organization);

        return $this->get($user, '/orgs/' . $this->segment($org) . '/servers', $query);
    }

    /**
     * Einzelnen Server abrufen (GET /orgs/{organization}/servers/{server}).
     *
     * @throws ForgeApiException
     */
    public function getServer(?User $user, int|string $server, ?string $organization = null, array $query = []): array
    {
        $org = $this->resolveOrganization($user, $organization);

        return $this->get($user, '/orgs/' . $this->segment($org) . '/servers/' . $this->segment($server), $query);
    }

    /**
     * Sites einer Organisation auflisten (GET /orgs/{organization}/sites).
     *
     * Liefert alle Sites über sämtliche Server hinweg.
     *
     * @throws ForgeApiException
     */
    public function listOrganizationSites(?User $user, ?string $organization = null, array $query = []): array
    {
        $org = $this->resolveOrganization($user, $organization);

        return $this->get($user, '/orgs/' . $this->segment($org) . '/sites', $query);
    }

    /**
     * Sites eines Servers auflisten (GET /orgs/{organization}/servers/{server}/sites).
     *
     * @throws ForgeApiException
     */
    public function listServerSites(?User $user, int|string $server, ?string $organization = null, array $query = []): array
    {
        $org = $this->resolveOrganization($user, $organization);

        return $this->get(
            $user,
            '/orgs/' . $this->segment($org) . '/servers/' . $this->segment($server) . '/sites',
            $query
        );
    }

    /**
     * Einzelne Site abrufen (GET /orgs/{organization}/sites/{site}).
     *
     * @throws ForgeApiException
     */
    public function getSite(?User $user, int|string $site, ?string $organization = null, array $query = []): array
    {
        $org = $this->resolveOrganization($user, $organization);

        return $this->get($user, '/orgs/' . $this->segment($org) . '/sites/' . $this->segment($site), $query);
    }

    /**
     * Deployments einer Site auflisten
     * (GET /orgs/{organization}/servers/{server}/sites/{site}/deployments).
     *
     * @throws ForgeApiException
     */
    public function listDeployments(
        ?User $user,
        int|string $server,
        int|string $site,
        ?string $organization = null,
        array $query = []
    ): array {
        return $this->get($user, $this->sitePath($user, $organization, $server, $site) . '/deployments', $query);
    }

    /**
     * Einzelnes Deployment abrufen.
     *
     * @throws ForgeApiException
     */
    public function getDeployment(
        ?User $user,
        int|string $server,
        int|string $site,
        int|string $deployment,
        ?string $organization = null
    ): array {
        return $this->get(
            $user,
            $this->sitePath($user, $organization, $server, $site) . '/deployments/' . $this->segment($deployment)
        );
    }

    /**
     * Deployment-Log abrufen.
     *
     * @throws ForgeApiException
     */
    public function getDeploymentLog(
        ?User $user,
        int|string $server,
        int|string $site,
        int|string $deployment,
        ?string $organization = null
    ): array {
        return $this->get(
            $user,
            $this->sitePath($user, $organization, $server, $site) . '/deployments/' . $this->segment($deployment) . '/log'
        );
    }

    /**
     * Deployment auslösen — SCHREIBEND
     * (POST /orgs/{organization}/servers/{server}/sites/{site}/deployments).
     *
     * @throws ForgeApiException
     */
    public function deploySite(
        ?User $user,
        int|string $server,
        int|string $site,
        ?string $organization = null,
        array $body = []
    ): array {
        return $this->post($user, $this->sitePath($user, $organization, $server, $site) . '/deployments', $body);
    }

    /**
     * Deployment-Status einer Site abrufen.
     *
     * @throws ForgeApiException
     */
    public function getDeploymentStatus(
        ?User $user,
        int|string $server,
        int|string $site,
        ?string $organization = null
    ): array {
        return $this->get($user, $this->sitePath($user, $organization, $server, $site) . '/deployments/status');
    }

    /**
     * Server-Aktion ausführen — SCHREIBEND
     * (POST /orgs/{organization}/servers/{server}/actions).
     *
     * @throws ForgeApiException
     */
    public function runServerAction(
        ?User $user,
        int|string $server,
        string $action,
        ?string $organization = null,
        array $body = []
    ): array {
        $org = $this->resolveOrganization($user, $organization);
        $payload = array_merge(['action' => $action], $body);

        return $this->post(
            $user,
            '/orgs/' . $this->segment($org) . '/servers/' . $this->segment($server) . '/actions',
            $payload
        );
    }

    /**
     * Dienst-Aktion ausführen (nginx, mysql, php, postgres, redis, supervisor) — SCHREIBEND
     * (POST /orgs/{organization}/servers/{server}/services/{service}/actions).
     *
     * @throws ForgeApiException
     */
    public function runServiceAction(
        ?User $user,
        int|string $server,
        string $service,
        string $action,
        ?string $organization = null
    ): array {
        $org = $this->resolveOrganization($user, $organization);

        return $this->post(
            $user,
            '/orgs/' . $this->segment($org) . '/servers/' . $this->segment($server)
            . '/services/' . $this->segment($service) . '/actions',
            ['action' => $action]
        );
    }

    /**
     * Organisations-Events auflisten (GET /orgs/{organization}/events).
     *
     * @throws ForgeApiException
     */
    public function listEvents(?User $user, ?string $organization = null, array $query = []): array
    {
        $org = $this->resolveOrganization($user, $organization);

        return $this->get($user, '/orgs/' . $this->segment($org) . '/events', $query);
    }

    /**
     * Events eines Servers auflisten (GET /orgs/{organization}/servers/{server}/events).
     *
     * @throws ForgeApiException
     */
    public function listServerEvents(?User $user, int|string $server, ?string $organization = null, array $query = []): array
    {
        $org = $this->resolveOrganization($user, $organization);

        return $this->get(
            $user,
            '/orgs/' . $this->segment($org) . '/servers/' . $this->segment($server) . '/events',
            $query
        );
    }

    // =========================================================================
    // GENERISCHER ZUGRIFF
    // =========================================================================

    /**
     * Generischer API-Aufruf auf einen beliebigen Forge-Pfad.
     *
     * Platzhalter im Pfad:
     * - "{org}" wird durch die aufgelöste Organisation ersetzt.
     * - Ein Pfad ohne führenden Slash wird als organisationsrelativ behandelt
     *   und automatisch unter /orgs/{organization}/ eingehängt.
     *
     * @throws ForgeApiException
     */
    public function call(
        ?User $user,
        string $method,
        string $path,
        array $query = [],
        array $body = [],
        ?string $organization = null
    ): array {
        return $this->request($user, strtoupper($method), $this->normalizePath($user, $path, $organization), $query, $body);
    }

    /**
     * @throws ForgeApiException
     */
    public function get(?User $user, string $path, array $query = []): array
    {
        return $this->request($user, 'GET', $path, $query);
    }

    /**
     * @throws ForgeApiException
     */
    public function post(?User $user, string $path, array $body = []): array
    {
        return $this->request($user, 'POST', $path, [], $body);
    }

    /**
     * @throws ForgeApiException
     */
    public function put(?User $user, string $path, array $body = []): array
    {
        return $this->request($user, 'PUT', $path, [], $body);
    }

    /**
     * @throws ForgeApiException
     */
    public function delete(?User $user, string $path, array $body = []): array
    {
        return $this->request($user, 'DELETE', $path, [], $body);
    }

    // =========================================================================
    // INTERNE HTTP-METHODEN
    // =========================================================================

    /**
     * Baut den Basispfad einer Site.
     *
     * @throws ForgeApiException
     */
    protected function sitePath(?User $user, ?string $organization, int|string $server, int|string $site): string
    {
        $org = $this->resolveOrganization($user, $organization);

        return '/orgs/' . $this->segment($org)
            . '/servers/' . $this->segment($server)
            . '/sites/' . $this->segment($site);
    }

    /**
     * Normalisiert einen vom Aufrufer übergebenen Pfad.
     *
     * @throws ForgeApiException
     */
    protected function normalizePath(?User $user, string $path, ?string $organization): string
    {
        $path = trim($path);

        if ($path === '') {
            throw new ForgeApiException('Leerer API-Pfad.', 400, 'VALIDATION_ERROR');
        }

        // Vollständige URL auf den Pfadanteil reduzieren.
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $path = (string) (parse_url($path, PHP_URL_PATH) ?: '/');
            // Ein von "links.next" übernommener Pfad enthält bereits das /api-Präfix.
            if (str_starts_with($path, '/api/')) {
                $path = substr($path, 4);
            }
        }

        if (str_contains($path, '{org}') || str_contains($path, '{organization}')) {
            $org = $this->resolveOrganization($user, $organization);
            $path = str_replace(['{org}', '{organization}'], $this->segment($org), $path);
        }

        // Relativer Pfad ("servers/123") = organisationsrelativ.
        if (!str_starts_with($path, '/')) {
            $org = $this->resolveOrganization($user, $organization);

            return '/orgs/' . $this->segment($org) . '/' . ltrim($path, '/');
        }

        return $path;
    }

    /**
     * Kodiert ein einzelnes Pfadsegment (Slugs dürfen keine Sonderzeichen einschleusen).
     */
    protected function segment(int|string $value): string
    {
        return rawurlencode(trim((string) $value));
    }

    /**
     * Führt einen HTTP-Request gegen die Forge API aus.
     *
     * @throws ForgeApiException
     */
    protected function request(?User $user, string $method, string $path, array $query = [], array $body = []): array
    {
        $connection = $this->resolveConnection($user);
        $token = $this->integrationService->getApiToken($connection);

        if (!$token) {
            Log::warning('Forge API: Kein API-Token', ['user_id' => $user?->id]);

            throw ForgeApiException::unauthorized();
        }

        $baseUrl = $this->integrationService->getBaseUrl($connection);
        $url = $baseUrl . $path;
        $timeout = (int) config('integrations.forge.timeout.default', 30);
        $connectTimeout = (int) config('integrations.forge.timeout.connect', 10);

        try {
            $http = Http::withToken($token)
                ->timeout($timeout)
                ->connectTimeout($connectTimeout)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ]);

            $response = match ($method) {
                'GET' => $http->get($url, $query),
                'POST' => $http->post($url, $body),
                'PUT' => $http->put($url, $body),
                'PATCH' => $http->patch($url, $body),
                'DELETE' => $http->delete($url, $body),
                default => throw new ForgeApiException("Nicht unterstützte HTTP-Methode: {$method}", 400, 'VALIDATION_ERROR'),
            };

            return $this->handleResponse($response, $connection);
        } catch (ForgeApiException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Forge API: Verbindungsfehler', [
                'user_id' => $user?->id,
                'method' => $method,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            $this->updateConnectionStatus($connection, 'error', $e->getMessage());

            throw ForgeApiException::connectionError($e->getMessage());
        }
    }

    /**
     * Verarbeitet die HTTP-Response und behandelt Fehler.
     *
     * @throws ForgeApiException
     */
    protected function handleResponse(Response $response, IntegrationConnection $connection): array
    {
        $statusCode = $response->status();

        if ($statusCode === 401) {
            $this->updateConnectionStatus($connection, 'error', 'Ungültiger Forge API-Token');

            throw ForgeApiException::unauthorized();
        }

        if ($statusCode === 403) {
            throw ForgeApiException::forbidden();
        }

        if ($statusCode === 429) {
            $retryAfter = (int) $response->header('Retry-After');

            throw ForgeApiException::rateLimited($retryAfter ?: null);
        }

        if ($response->successful()) {
            $this->updateConnectionStatus($connection, 'active');

            // 204 No Content (z.B. DELETE) liefert keinen Body.
            if ($statusCode === 204 || trim($response->body()) === '') {
                return ['success' => true, 'status' => $statusCode];
            }

            $data = $response->json();

            if (!is_array($data)) {
                // Log-Endpunkte liefern Plaintext.
                return ['content' => $response->body(), 'status' => $statusCode];
            }

            return $data;
        }

        $data = $response->json();
        $data = is_array($data) ? $data : [];

        Log::warning('Forge API: Fehler-Response', [
            'status_code' => $statusCode,
            'response' => $data,
        ]);

        throw ForgeApiException::fromResponse($statusCode, $data);
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
