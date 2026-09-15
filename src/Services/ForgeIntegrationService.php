<?php

namespace Platform\Integrations\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Core\Models\User;
use Platform\Integrations\Models\Integration;
use Platform\Integrations\Models\IntegrationConnection;

/**
 * Helper-Service für Laravel-Forge-Integrationen (API v2).
 *
 * Forge nutzt ein persönliches API-Token (Bearer):
 *   Authorization: Bearer <api_key>
 *
 * Das Token wird im Forge-Profil erzeugt: https://forge.laravel.com/profile/api
 * Es wird nur einmal vollständig angezeigt und in credentials.api_key gespeichert.
 *
 * Base-URL ist fix: https://forge.laravel.com/api
 *
 * Wichtig: Die v2-API ist organisationsbezogen — nahezu alle Ressourcen liegen
 * unter /orgs/{organization}/... Deshalb kann pro Connection eine Standard-
 * Organisation in credentials.organization hinterlegt werden, die von allen
 * Tools als Default verwendet wird.
 *
 * @see https://laravel.com/forge/docs/api-reference/introduction
 */
class ForgeIntegrationService
{
    public const INTEGRATION_KEY = 'forge';

    protected IntegrationConnectionResolver $resolver;

    public function __construct(IntegrationConnectionResolver $resolver)
    {
        $this->resolver = $resolver;
    }

    public function getConnectionForUser(User $user): ?IntegrationConnection
    {
        return $this->resolver->resolveForUser(self::INTEGRATION_KEY, $user);
    }

    public function getApiTokenForUser(User $user): ?string
    {
        $connection = $this->getConnectionForUser($user);

        return $connection ? $this->getApiToken($connection) : null;
    }

    public function getApiToken(IntegrationConnection $connection): ?string
    {
        $token = ($connection->credentials ?? [])['api_key'] ?? null;

        return $token !== null ? trim((string) $token) : null;
    }

    public function hasValidApiToken(IntegrationConnection $connection): bool
    {
        return !empty($this->getApiToken($connection));
    }

    /**
     * Standard-Organisation dieser Connection (Slug oder ID), falls hinterlegt.
     */
    public function getDefaultOrganization(IntegrationConnection $connection): ?string
    {
        $org = ($connection->credentials ?? [])['organization'] ?? null;
        $org = $org !== null ? trim((string) $org) : '';

        return $org !== '' ? $org : null;
    }

    /**
     * Base-URL dieser Connection (ohne Trailing-Slash).
     */
    public function getBaseUrl(IntegrationConnection $connection): string
    {
        $baseUrl = ($connection->credentials ?? [])['base_url']
            ?? config('integrations.forge.api_base_url', 'https://forge.laravel.com/api');

        return rtrim(trim((string) $baseUrl), '/');
    }

    public function updateApiToken(IntegrationConnection $connection, string $apiToken): void
    {
        $credentials = $connection->credentials ?? [];
        $credentials['api_key'] = trim($apiToken);

        $connection->credentials = $credentials;
        $connection->status = 'active';
        $connection->last_error = null;
        $connection->save();

        Log::info('Forge API-Token aktualisiert', ['connection_id' => $connection->id]);
    }

    /**
     * Erstellt oder aktualisiert eine Forge-Connection für einen User.
     *
     * @param int|null $connectionId Wenn gesetzt: Update dieser Connection; null = neue Connection
     */
    public function createOrUpdateConnectionForUser(
        User $user,
        string $apiToken,
        ?string $organization = null,
        ?int $connectionId = null
    ): IntegrationConnection {
        $integration = Integration::firstOrCreate(
            ['key' => self::INTEGRATION_KEY],
            [
                'name' => 'Laravel Forge',
                'is_enabled' => true,
                'supported_auth_schemes' => ['api_key'],
                'meta' => [
                    'description' => 'Laravel Forge Integration (API v2) für Server, Sites und Deployments.',
                    'icon' => 'heroicon-o-server-stack',
                ],
            ]
        );

        if ($connectionId) {
            $connection = IntegrationConnection::withTrashed()
                ->where('id', $connectionId)
                ->where('owner_user_id', $user->id)
                ->first();

            if (!$connection) {
                throw new \RuntimeException("Connection #{$connectionId} nicht gefunden.");
            }

            if ($connection->trashed()) {
                $connection->restore();
            }

            $connection->auth_scheme = 'api_key';
            $connection->status = 'active';
        } else {
            $isFirst = !IntegrationConnection::query()
                ->where('integration_id', $integration->id)
                ->where('owner_user_id', $user->id)
                ->exists();

            $connection = new IntegrationConnection([
                'integration_id' => $integration->id,
                'owner_user_id' => $user->id,
                'name' => IntegrationConnection::generateName($integration->id, $user->id, $integration->name),
                'is_default' => $isFirst,
                'auth_scheme' => 'api_key',
                'status' => 'active',
            ]);
        }

        $credentials = $connection->credentials ?? [];
        $credentials['api_key'] = trim($apiToken);

        if ($organization !== null) {
            $organization = trim($organization);
            if ($organization !== '') {
                $credentials['organization'] = $organization;
            } else {
                unset($credentials['organization']);
            }
        }

        $connection->credentials = $credentials;
        $connection->last_error = null;
        $connection->save();

        Log::info('Forge connection created/updated', [
            'connection_id' => $connection->id,
            'user_id' => $user->id,
            'has_organization' => isset($credentials['organization']),
        ]);

        return $connection;
    }

    /**
     * Testet die Forge-Verbindung über GET /orgs.
     *
     * Der Endpunkt ist leichtgewichtig und bestätigt zugleich, auf welche
     * Organisationen das Token Zugriff hat — genau die Information, die für
     * alle weiteren Aufrufe gebraucht wird.
     *
     * @return array{success: bool, message: string, organizations?: array<int, array<string, mixed>>}
     */
    public function testConnection(IntegrationConnection $connection): array
    {
        $token = $this->getApiToken($connection);

        if (!$token) {
            return $this->markTested($connection, false, 'Kein Laravel-Forge API-Token hinterlegt.');
        }

        $baseUrl = $this->getBaseUrl($connection);
        $timeout = (int) config('integrations.forge.timeout.default', 30);
        $connectTimeout = (int) config('integrations.forge.timeout.connect', 10);

        try {
            $response = Http::withToken($token)
                ->timeout($timeout)
                ->connectTimeout($connectTimeout)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->get($baseUrl . '/orgs');

            if ($response->status() === 401) {
                return $this->markTested($connection, false, 'Token ungültig oder abgelaufen (HTTP 401).');
            }

            if ($response->status() === 403) {
                return $this->markTested($connection, false, 'Token hat keine Berechtigung für /orgs (HTTP 403).');
            }

            if (!$response->successful()) {
                $body = $response->json();

                return $this->markTested(
                    $connection,
                    false,
                    'Forge-API-Fehler: HTTP ' . $response->status() . ' — '
                    . ($body['message'] ?? $response->body())
                );
            }

            $orgs = $response->json('data') ?? [];
            $names = [];
            foreach ($orgs as $org) {
                $names[] = [
                    'id' => $org['id'] ?? null,
                    'name' => $org['name'] ?? null,
                    'slug' => $org['slug'] ?? null,
                ];
            }

            $count = count($names);
            $default = $this->getDefaultOrganization($connection);

            $message = "Verbindung OK — Zugriff auf {$count} Organisation(en).";

            if ($default) {
                $known = array_filter(
                    $names,
                    fn ($o) => (string) ($o['slug'] ?? '') === $default || (string) ($o['id'] ?? '') === $default
                );

                $message .= $known
                    ? " Standard-Organisation '{$default}' ist erreichbar."
                    : " ACHTUNG: Standard-Organisation '{$default}' ist in dieser Liste nicht enthalten.";
            } elseif ($count > 0) {
                $first = $names[0]['slug'] ?? $names[0]['id'] ?? null;
                $message .= $first
                    ? " Keine Standard-Organisation hinterlegt — z.B. '{$first}' eintragen."
                    : ' Keine Standard-Organisation hinterlegt.';
            }

            $result = $this->markTested($connection, true, $message);
            $result['organizations'] = $names;

            return $result;
        } catch (\Throwable $e) {
            Log::warning('Forge: Verbindungstest fehlgeschlagen', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
            ]);

            return $this->markTested($connection, false, 'Verbindungsfehler: ' . $e->getMessage());
        }
    }

    public function deleteConnectionForUser(User $user): bool
    {
        $connection = $this->getConnectionForUser($user);

        if (!$connection || !$connection->isOwner($user)) {
            return false;
        }

        return (bool) $connection->delete();
    }

    /**
     * @return array{success: bool, message: string}
     */
    private function markTested(IntegrationConnection $connection, bool $ok, string $message): array
    {
        $connection->status = $ok ? 'active' : 'error';
        $connection->last_error = $ok ? null : $message;
        $connection->last_tested_at = now();
        $connection->save();

        return ['success' => $ok, 'message' => $message];
    }
}
