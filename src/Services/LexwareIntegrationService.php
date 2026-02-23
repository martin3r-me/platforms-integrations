<?php

namespace Platform\Integrations\Services;

use Platform\Core\Models\User;
use Platform\Integrations\Models\Integration;
use Platform\Integrations\Models\IntegrationConnection;
use Illuminate\Support\Facades\Log;

/**
 * Helper-Service für Lexware-Integrationen
 *
 * WICHTIG: Lexware hat KEIN OAuth-Verfahren!
 * Der API-Token muss manuell eingegeben werden.
 * Der Token wird in credentials.api_key gespeichert.
 */
class LexwareIntegrationService
{
    protected IntegrationConnectionResolver $resolver;

    public function __construct(IntegrationConnectionResolver $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * Ruft die Lexware-IntegrationConnection für einen User ab
     */
    public function getConnectionForUser(User $user): ?IntegrationConnection
    {
        return $this->resolver->resolveForUser('lexoffice', $user);
    }

    /**
     * Ruft den API-Token für einen User ab
     */
    public function getApiTokenForUser(User $user): ?string
    {
        $connection = $this->getConnectionForUser($user);
        if ($connection) {
            return $this->getApiToken($connection);
        }
        return null;
    }

    /**
     * Ruft den API-Token aus einer Lexware-IntegrationConnection ab
     */
    public function getApiToken(IntegrationConnection $connection): ?string
    {
        $credentials = $connection->credentials ?? [];
        return $credentials['api_key'] ?? null;
    }

    /**
     * Prüft, ob die Connection einen gültigen API-Token hat
     */
    public function hasValidApiToken(IntegrationConnection $connection): bool
    {
        $apiKey = $this->getApiToken($connection);
        return !empty($apiKey);
    }

    /**
     * Aktualisiert den API-Token einer Lexware-IntegrationConnection
     */
    public function updateApiToken(IntegrationConnection $connection, string $apiToken): void
    {
        $credentials = $connection->credentials ?? [];
        $credentials['api_key'] = $apiToken;

        $connection->credentials = $credentials;
        $connection->auth_scheme = 'api_key';
        $connection->status = 'active';
        $connection->last_error = null;
        $connection->save();
    }

    /**
     * Erstellt oder aktualisiert eine Lexware-IntegrationConnection für einen User.
     *
     * @param int|null $connectionId Wenn gesetzt: Update dieser Connection; null = neue Connection
     */
    public function createOrUpdateConnectionForUser(User $user, string $apiToken, ?int $connectionId = null): IntegrationConnection
    {
        $integration = Integration::firstOrCreate(
            ['key' => 'lexoffice'],
            [
                'name' => 'Lexware / Lexoffice',
                'is_enabled' => true,
                'supported_auth_schemes' => ['api_key'],
                'meta' => [
                    'description' => 'Lexware/Lexoffice Integration für Buchhaltung, Kontakte und Rechnungen. Verbindung erfolgt über API-Token (kein OAuth).',
                    'icon' => 'heroicon-o-calculator',
                ],
            ]
        );

        if ($connectionId) {
            // Update spezifische Connection
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
            // Neue Connection erstellen
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

        $this->updateApiToken($connection, $apiToken);

        Log::info('Lexware connection created/updated', [
            'connection_id' => $connection->id,
            'user_id' => $user->id,
        ]);

        return $connection;
    }

    /**
     * Testet die Lexware API-Verbindung
     *
     * @return array{success: bool, message: string, data?: array}
     */
    public function testConnection(IntegrationConnection $connection): array
    {
        $apiToken = $this->getApiToken($connection);

        if (!$apiToken) {
            return [
                'success' => false,
                'message' => 'Kein API-Token vorhanden.',
            ];
        }

        try {
            // Test mit dem /profile Endpoint von Lexoffice
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiToken,
                'Accept' => 'application/json',
            ])->get('https://api.lexoffice.io/v1/profile');

            if ($response->successful()) {
                $data = $response->json();

                // Connection als aktiv markieren
                $connection->status = 'active';
                $connection->last_error = null;
                $connection->last_tested_at = now();
                $connection->save();

                return [
                    'success' => true,
                    'message' => 'Verbindung erfolgreich.',
                    'data' => $data,
                ];
            }

            $error = $response->json()['message'] ?? 'Unbekannter Fehler';

            $connection->status = 'error';
            $connection->last_error = $error;
            $connection->last_tested_at = now();
            $connection->save();

            return [
                'success' => false,
                'message' => 'API-Fehler: ' . $error,
            ];
        } catch (\Exception $e) {
            Log::error('Lexware connection test failed', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
            ]);

            $connection->status = 'error';
            $connection->last_error = $e->getMessage();
            $connection->last_tested_at = now();
            $connection->save();

            return [
                'success' => false,
                'message' => 'Verbindungsfehler: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Löscht die Lexware-IntegrationConnection für einen User
     */
    public function deleteConnectionForUser(User $user): bool
    {
        $connection = $this->getConnectionForUser($user);

        if ($connection) {
            $connection->delete();
            Log::info('Lexware connection deleted', [
                'user_id' => $user->id,
            ]);
            return true;
        }

        return false;
    }
}
