<?php

namespace Platform\Integrations\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Core\Models\User;
use Platform\Integrations\Models\Integration;
use Platform\Integrations\Models\IntegrationConnection;

/**
 * Helper-Service für HubSpot-Integrationen
 *
 * HubSpot verwendet kein OAuth in dieser Integration — stattdessen wird ein
 * Private App Access Token manuell eingegeben und als api_key gespeichert.
 */
class HubspotIntegrationService
{
    protected IntegrationConnectionResolver $resolver;

    public function __construct(IntegrationConnectionResolver $resolver)
    {
        $this->resolver = $resolver;
    }

    public function getConnectionForUser(User $user): ?IntegrationConnection
    {
        return $this->resolver->resolveForUser('hubspot', $user);
    }

    public function getApiToken(IntegrationConnection $connection): ?string
    {
        $credentials = $connection->credentials ?? [];
        return $credentials['api_key'] ?? null;
    }

    public function hasValidApiToken(IntegrationConnection $connection): bool
    {
        return !empty($this->getApiToken($connection));
    }

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
     * Erstellt oder aktualisiert eine HubSpot-IntegrationConnection für einen User.
     *
     * @param int|null $connectionId Wenn gesetzt: Update dieser Connection; null = neue Connection
     */
    public function createOrUpdateConnectionForUser(User $user, string $apiToken, ?int $connectionId = null): IntegrationConnection
    {
        $integration = Integration::firstOrCreate(
            ['key' => 'hubspot'],
            [
                'name' => 'HubSpot',
                'is_enabled' => true,
                'supported_auth_schemes' => ['api_key'],
                'meta' => [
                    'description' => 'HubSpot CRM Integration via Private App Token',
                    'icon' => 'heroicon-o-users',
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

        Log::info('HubSpot connection created/updated', [
            'connection_id' => $connection->id,
            'user_id' => $user->id,
        ]);

        return $connection;
    }

    /**
     * Testet die HubSpot API-Verbindung gegen /account-info/v3/details
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
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiToken,
                'Accept' => 'application/json',
            ])->get('https://api.hubapi.com/account-info/v3/details');

            if ($response->successful()) {
                $data = $response->json();

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
            Log::error('HubSpot connection test failed', [
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

    public function deleteConnectionForUser(User $user): bool
    {
        $connection = $this->getConnectionForUser($user);

        if ($connection) {
            $connection->delete();
            Log::info('HubSpot connection deleted', [
                'user_id' => $user->id,
            ]);
            return true;
        }

        return false;
    }
}
