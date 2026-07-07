<?php

namespace Platform\Integrations\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Platform\Integrations\Models\Integration;
use Platform\Integrations\Models\IntegrationConnection;

/**
 * Helper-Service für FLYNK-Integrationen.
 *
 * FLYNK nutzt Laravel-Sanctum-Authentifizierung via Bearer Token:
 *   Authorization: Bearer <api_key>
 *
 * Anders als bei Providern mit fester Base-URL ist die FLYNK-Instanz pro
 * Connection unterschiedlich. Daher werden zwei Credential-Keys gespeichert:
 *   - api_key  : Sanctum Personal-Access-Token
 *   - base_url : Root-URL der FLYNK-Instanz (ohne /api)
 */
class FlynkIntegrationService
{
    public const INTEGRATION_KEY = 'flynk';

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

    public function getApiToken(IntegrationConnection $connection): ?string
    {
        $token = ($connection->credentials ?? [])['api_key'] ?? null;

        return $token !== null ? trim($token) : null;
    }

    /**
     * Liefert die normalisierte Base-URL der FLYNK-Instanz (ohne trailing Slash,
     * ohne ein evtl. mitgegebenes /api-Suffix — das setzt der ApiService selbst).
     */
    public function getBaseUrl(IntegrationConnection $connection): ?string
    {
        $baseUrl = ($connection->credentials ?? [])['base_url'] ?? null;

        if ($baseUrl === null || trim($baseUrl) === '') {
            return null;
        }

        $baseUrl = rtrim(trim($baseUrl), '/');

        // Falls jemand die URL inkl. /api hinterlegt hat: normalisieren.
        if (str_ends_with($baseUrl, '/api')) {
            $baseUrl = substr($baseUrl, 0, -4);
        }

        return $baseUrl;
    }

    public function hasValidCredentials(IntegrationConnection $connection): bool
    {
        return !empty($this->getApiToken($connection)) && !empty($this->getBaseUrl($connection));
    }

    /**
     * Schreibt api_key + base_url in die Connection-Credentials.
     */
    public function updateCredentials(IntegrationConnection $connection, string $apiToken, string $baseUrl): void
    {
        $credentials = $connection->credentials ?? [];
        $credentials['api_key'] = trim($apiToken);
        $credentials['base_url'] = rtrim(trim($baseUrl), '/');

        $connection->credentials = $credentials;
        $connection->auth_scheme = 'api_key';
        $connection->status = 'active';
        $connection->last_error = null;
        $connection->save();
    }

    /**
     * Erstellt oder aktualisiert eine FLYNK-IntegrationConnection für einen User.
     *
     * @param int|null $connectionId Wenn gesetzt: Update dieser Connection; null = neue Connection
     */
    public function createOrUpdateConnectionForUser(
        User $user,
        string $apiToken,
        string $baseUrl,
        ?int $connectionId = null
    ): IntegrationConnection {
        $integration = Integration::firstOrCreate(
            ['key' => self::INTEGRATION_KEY],
            [
                'name' => 'FLYNK',
                'is_enabled' => true,
                'supported_auth_schemes' => ['api_key'],
                'meta' => [
                    'description' => 'FLYNK Client Portal & Agency Management — REST-API (Sanctum Bearer-Token).',
                    'icon' => 'heroicon-o-arrows-right-left',
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

        $this->updateCredentials($connection, $apiToken, $baseUrl);

        Log::info('FLYNK connection created/updated', [
            'connection_id' => $connection->id,
            'user_id' => $user->id,
        ]);

        return $connection;
    }

    // =========================================================================
    // VERBINDUNGSTEST
    // =========================================================================

    /**
     * Testet die FLYNK API-Verbindung gegen GET /api/projects.
     *
     * @return array{success: bool, message: string, data?: array}
     */
    public function testConnection(IntegrationConnection $connection): array
    {
        $apiToken = $this->getApiToken($connection);
        $baseUrl = $this->getBaseUrl($connection);

        if (!$apiToken) {
            return ['success' => false, 'message' => 'Kein API-Token (api_key) vorhanden.'];
        }
        if (!$baseUrl) {
            return ['success' => false, 'message' => 'Keine base_url hinterlegt.'];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiToken,
                'Accept' => 'application/json',
            ])->get($baseUrl . '/api/projects');

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
                $hint = ' Hinweis: Prüfe den Sanctum-Token und ob er gültig/nicht widerrufen ist.';
            } elseif ($status === 404) {
                $hint = ' Hinweis: Prüfe die base_url — sie muss der Instanz-Root ohne /api sein.';
            }

            $connection->status = 'error';
            $connection->last_error = is_string($error) ? $error : json_encode($error);
            $connection->last_tested_at = now();
            $connection->save();

            Log::warning('FLYNK testConnection failed', [
                'connection_id' => $connection->id,
                'http_status' => $status,
            ]);

            return [
                'success' => false,
                'message' => 'API-Fehler (HTTP ' . $status . '): ' . (is_string($error) ? $error : json_encode($error)) . $hint,
            ];
        } catch (\Exception $e) {
            Log::error('FLYNK connection test failed', [
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
            Log::info('FLYNK connection deleted', ['user_id' => $user->id]);

            return true;
        }

        return false;
    }
}
