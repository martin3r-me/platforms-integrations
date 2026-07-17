<?php

namespace Platform\Integrations\Services;

use Platform\Core\Models\User;
use Platform\Integrations\Models\Integration;
use Platform\Integrations\Models\IntegrationConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Helper-Service für DedeFleet-Integrationen (Ortung & Tourenplanung).
 *
 * DedeFleet nutzt einen Dauertoken (Bearer):
 *   Authorization: Bearer <api_key>
 *
 * Der Token wird im DedeFleet-Portal erzeugt:
 *   Systemeinstellungen → Benutzer → neuen Benutzer vom Typ "Api Vollzugriff"
 *   anlegen → Token vom Typ "Permanent" generieren.
 * Er wird nur einmal vollständig angezeigt und in credentials.api_key gespeichert.
 *
 * Base-URL ist fix (Mandanten-Trennung erfolgt über den Token):
 *   https://ortung.dedefleet.de/data/api/2
 *
 * @see https://wiki.dedefleet.de/books/tourenplanung
 */
class DedefleetIntegrationService
{
    public const INTEGRATION_KEY = 'dedefleet';

    /** Leichtgewichtiger GET-Endpunkt für den Verbindungstest. */
    protected const TEST_URL = 'https://ortung.dedefleet.de/data/api/2/Location/List';

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

        return $token !== null ? trim($token) : null;
    }

    public function hasValidApiToken(IntegrationConnection $connection): bool
    {
        return !empty($this->getApiToken($connection));
    }

    public function updateApiToken(IntegrationConnection $connection, string $apiToken): void
    {
        // Trim — Copy-Paste aus dem Portal bringt oft Whitespace mit → sonst 401.
        $apiToken = trim($apiToken);

        $credentials = $connection->credentials ?? [];
        $credentials['api_key'] = $apiToken;

        $connection->credentials = $credentials;
        $connection->auth_scheme = 'api_key';
        $connection->status = 'active';
        $connection->last_error = null;
        $connection->save();
    }

    /**
     * Erstellt oder aktualisiert eine DedeFleet-IntegrationConnection für einen User.
     *
     * @param int|null $connectionId Wenn gesetzt: Update dieser Connection; null = neue Connection
     */
    public function createOrUpdateConnectionForUser(User $user, string $apiToken, ?int $connectionId = null): IntegrationConnection
    {
        $integration = Integration::firstOrCreate(
            ['key' => self::INTEGRATION_KEY],
            [
                'name' => 'DedeFleet',
                'is_enabled' => true,
                'supported_auth_schemes' => ['api_key'],
                'meta' => [
                    'description' => 'DedeFleet Ortung & Tourenplanung — REST-API (Bearer-Dauertoken). '
                        . 'Aufträge, Touren, Kunden, Mitarbeiter, Standorte, Fahrzeuge & GPS-Ortung.',
                    'icon' => 'heroicon-o-truck',
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
            }
        }

        $this->updateApiToken($connection, $apiToken);

        Log::info('DedeFleet connection created/updated', [
            'connection_id' => $connection->id,
            'user_id' => $user->id,
        ]);

        return $connection;
    }

    /**
     * Testet die DedeFleet API-Verbindung gegen GET /Location/List.
     *
     * @return array{success: bool, message: string, data?: array}
     */
    public function testConnection(IntegrationConnection $connection): array
    {
        $apiToken = $this->getApiToken($connection);

        if (!$apiToken) {
            return ['success' => false, 'message' => 'Kein Dauertoken vorhanden.'];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiToken,
                'Accept' => 'application/json',
            ])->get(self::TEST_URL);

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
            $error = is_array($body) ? ($body['message'] ?? $body['error'] ?? $body['Message'] ?? null) : null;

            if (!$error) {
                $raw = trim((string) $response->body());
                $error = $raw !== '' ? mb_substr($raw, 0, 300) : 'Unbekannter Fehler';
            }

            $hint = '';
            if ($status === 401) {
                $hint = ' Hinweis: Prüfe den Dauertoken (Portal → Systemeinstellungen → Benutzer → '
                    . 'Typ "Api Vollzugriff"). Der Token wird nur einmal angezeigt — bei Verlust neu erzeugen.';
            } elseif ($status === 403) {
                $hint = ' Hinweis: Der Benutzer/Token hat keine Berechtigung für diese Aktion.';
            }

            $connection->status = 'error';
            $connection->last_error = is_string($error) ? $error : json_encode($error);
            $connection->last_tested_at = now();
            $connection->save();

            Log::warning('DedeFleet testConnection failed', [
                'connection_id' => $connection->id,
                'http_status' => $status,
            ]);

            return [
                'success' => false,
                'message' => 'API-Fehler (HTTP ' . $status . '): ' . (is_string($error) ? $error : json_encode($error)) . $hint,
            ];
        } catch (\Exception $e) {
            Log::error('DedeFleet connection test failed', [
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
            Log::info('DedeFleet connection deleted', ['user_id' => $user->id]);

            return true;
        }

        return false;
    }
}
