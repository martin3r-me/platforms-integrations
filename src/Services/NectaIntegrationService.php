<?php

namespace Platform\Integrations\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Platform\Integrations\Models\Integration;
use Platform\Integrations\Models\IntegrationConnection;

/**
 * Helper-Service für necta.one Raw-API-Integrationen.
 *
 * necta.one nutzt API-Key-Authentifizierung via HTTP-Header:
 *   X-Api-Key: <api_key>
 *
 * Die necta.one-Instanz ist pro Connection unterschiedlich (jeder Kunde hat
 * seine eigene Subdomain). Daher werden zwei Credential-Keys gespeichert:
 *   - api_key  : der vom necta-Systemadmin ausgestellte API-Key
 *   - base_url : Root-URL der necta.one-Instanz (ohne /rawapi), z.B.
 *                https://firma.necta.one
 */
class NectaIntegrationService
{
    public const INTEGRATION_KEY = 'necta';

    protected IntegrationConnectionResolver $resolver;

    public function __construct(IntegrationConnectionResolver $resolver)
    {
        $this->resolver = $resolver;
    }

    // =========================================================================
    // CONNECTION-AUFLÖSUNG
    // =========================================================================

    public function getConnectionForUser(User $user): ?IntegrationConnection
    {
        return $this->resolver->resolveForUser(self::INTEGRATION_KEY, $user);
    }

    public function getConnectionForTeam(Team $team): ?IntegrationConnection
    {
        return $this->resolver->resolveForTeam(self::INTEGRATION_KEY, $team);
    }

    // =========================================================================
    // CREDENTIALS
    // =========================================================================

    public function getApiKey(IntegrationConnection $connection): ?string
    {
        $key = ($connection->credentials ?? [])['api_key'] ?? null;

        return $key !== null ? trim($key) : null;
    }

    /**
     * Liefert die normalisierte Base-URL (nur scheme://host[:port], OHNE Pfad).
     *
     * Robust gegen versehentlich mitgegebene Pfade: egal ob jemand
     * "https://api.necta.one", ".../rawapi" oder ".../api/v1/42/customers"
     * einträgt — wir reduzieren immer auf den Host-Root. Die API-Prefixe
     * (/rawapi bzw. /api/v1/{tenantId}) setzen die jeweiligen ApiServices selbst.
     */
    public function getBaseUrl(IntegrationConnection $connection): ?string
    {
        $baseUrl = ($connection->credentials ?? [])['base_url'] ?? null;

        if ($baseUrl === null || trim($baseUrl) === '') {
            return null;
        }

        return self::normalizeHost($baseUrl);
    }

    /**
     * Reduziert eine URL auf scheme://host[:port]. Fällt zurück auf simples
     * Trimmen, falls kein Host geparst werden kann.
     */
    public static function normalizeHost(string $url): string
    {
        $url = trim($url);
        // Ohne Schema kann parse_url den Host nicht zuverlässig lesen.
        $withScheme = preg_match('#^https?://#i', $url) ? $url : 'https://' . $url;
        $parts = parse_url($withScheme);

        if (!empty($parts['host'])) {
            $scheme = $parts['scheme'] ?? 'https';
            $host = $parts['host'];
            $port = isset($parts['port']) ? ':' . $parts['port'] : '';

            return "{$scheme}://{$host}{$port}";
        }

        // Fallback: bekannte Suffixe abschneiden.
        $url = rtrim($url, '/');
        $url = preg_replace('#/api/v1(/.*)?$#', '', $url);
        $url = preg_replace('#/rawapi(/.*)?$#', '', $url);

        return rtrim($url, '/');
    }

    /**
     * Tenant-ID für die necta.one API (v1, /api/v1/{tenantId}/…). Für die
     * Raw-API nicht nötig (Tenant steckt im Key).
     */
    public function getTenantId(IntegrationConnection $connection): ?string
    {
        $tenant = ($connection->credentials ?? [])['tenant_id'] ?? null;

        return ($tenant !== null && trim((string) $tenant) !== '') ? trim((string) $tenant) : null;
    }

    public function hasValidCredentials(IntegrationConnection $connection): bool
    {
        return !empty($this->getApiKey($connection)) && !empty($this->getBaseUrl($connection));
    }

    /**
     * Schreibt api_key + base_url (+ optional tenant_id) in die Credentials.
     */
    public function updateCredentials(
        IntegrationConnection $connection,
        string $apiKey,
        string $baseUrl,
        ?string $tenantId = null
    ): void {
        $credentials = $connection->credentials ?? [];
        $credentials['api_key'] = trim($apiKey);
        $credentials['base_url'] = self::normalizeHost($baseUrl);
        if ($tenantId !== null) {
            $tenantId = trim($tenantId);
            $credentials['tenant_id'] = $tenantId !== '' ? $tenantId : null;
        }

        $connection->credentials = $credentials;
        $connection->auth_scheme = 'api_key';
        $connection->status = 'active';
        $connection->last_error = null;
        $connection->save();
    }

    /**
     * Erstellt oder aktualisiert eine necta.one-IntegrationConnection für einen User.
     *
     * @param int|null $connectionId Wenn gesetzt: Update dieser Connection; null = neue Connection
     */
    public function createOrUpdateConnectionForUser(
        User $user,
        string $apiKey,
        string $baseUrl,
        ?string $tenantId = null,
        ?int $connectionId = null
    ): IntegrationConnection {
        $integration = Integration::firstOrCreate(
            ['key' => self::INTEGRATION_KEY],
            [
                'name' => 'necta.one',
                'is_enabled' => true,
                'supported_auth_schemes' => ['api_key'],
                'meta' => [
                    'description' => 'necta.one — Raw-API (/rawapi, read-only) UND necta.one API '
                        . '(/api/v1/{tenantId}, CRUD). Auth via X-Api-Key.',
                    'icon' => 'heroicon-o-cube',
                ],
            ]
        );

        if ($connectionId) {
            $connection = IntegrationConnection::withTrashed()
                ->where('id', $connectionId)
                ->where('owner_user_id', $user->id)
                ->first();

            if ($connection && $connection->trashed()) {
                $connection->restore();
            }

            if (!$connection) {
                throw new \RuntimeException("Connection #{$connectionId} nicht gefunden.");
            }

            $connection->auth_scheme = 'api_key';
            $connection->status = 'active';
        } else {
            $existing = IntegrationConnection::withTrashed()
                ->where('integration_id', $integration->id)
                ->where('owner_user_id', $user->id)
                ->first();

            if ($existing) {
                if ($existing->trashed()) {
                    $existing->restore();
                }
                $connection = $existing;
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
                $connection->save();
            }
        }

        $this->updateCredentials($connection, $apiKey, $baseUrl, $tenantId);

        Log::info('necta.one connection created/updated', [
            'connection_id' => $connection->id,
            'user_id' => $user->id,
        ]);

        return $connection;
    }

    // =========================================================================
    // VERBINDUNGSTEST
    // =========================================================================

    /**
     * Testet die necta.one API-Verbindung gegen einen leichtgewichtigen
     * Raw-API-Endpunkt (GET /rawapi/products?pageNumber=1&pageSize=1).
     *
     * @return array{success: bool, message: string, data?: array}
     */
    public function testConnection(IntegrationConnection $connection): array
    {
        $apiKey = $this->getApiKey($connection);
        $baseUrl = $this->getBaseUrl($connection);

        if (!$apiKey) {
            return ['success' => false, 'message' => 'Kein API-Key (api_key) vorhanden.'];
        }
        if (!$baseUrl) {
            return ['success' => false, 'message' => 'Keine base_url hinterlegt.'];
        }

        try {
            $response = Http::withHeaders([
                'X-Api-Key' => $apiKey,
                'Accept' => 'application/json',
            ])->get($baseUrl . '/rawapi/products', [
                'pageNumber' => 1,
                'pageSize' => 1,
            ]);

            if ($response->successful()) {
                $connection->status = 'active';
                $connection->last_error = null;
                $connection->last_tested_at = now();
                $connection->save();

                return [
                    'success' => true,
                    'message' => 'Verbindung erfolgreich.',
                    'data' => $response->json() ?? [],
                ];
            }

            $status = $response->status();
            $body = $response->json();
            $error = is_array($body) ? ($body['message'] ?? $body['error'] ?? null) : null;

            if (!$error) {
                $raw = trim((string) $response->body());
                $error = $raw !== '' ? mb_substr($raw, 0, 300) : 'Unbekannter Fehler';
            }

            $hint = '';
            if ($status === 401) {
                $hint = ' Hinweis: Prüfe den API-Key (Header X-Api-Key) und ob er gültig ist.';
            } elseif ($status === 403) {
                $hint = ' Hinweis: Der API-Key besitzt keine RAW-API-Berechtigung.';
            } elseif ($status === 404) {
                $hint = ' Hinweis: Prüfe die base_url — sie muss der Instanz-Root ohne /rawapi sein.';
            }

            $connection->status = 'error';
            $connection->last_error = is_string($error) ? $error : json_encode($error);
            $connection->last_tested_at = now();
            $connection->save();

            Log::warning('necta.one testConnection failed', [
                'connection_id' => $connection->id,
                'http_status' => $status,
            ]);

            return [
                'success' => false,
                'message' => 'API-Fehler (HTTP ' . $status . '): ' . (is_string($error) ? $error : json_encode($error)) . $hint,
            ];
        } catch (\Exception $e) {
            Log::error('necta.one connection test failed', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
            ]);

            $connection->status = 'error';
            $connection->last_error = $e->getMessage();
            $connection->last_tested_at = now();
            $connection->save();

            return ['success' => false, 'message' => 'Verbindungsfehler: ' . $e->getMessage()];
        }
    }

    public function deleteConnectionForUser(User $user): bool
    {
        $connection = $this->getConnectionForUser($user);

        if ($connection) {
            $connection->delete();
            Log::info('necta.one connection deleted', ['user_id' => $user->id]);

            return true;
        }

        return false;
    }
}
