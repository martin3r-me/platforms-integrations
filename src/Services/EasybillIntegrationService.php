<?php

namespace Platform\Integrations\Services;

use Platform\Core\Models\User;
use Platform\Integrations\Models\Integration;
use Platform\Integrations\Models\IntegrationConnection;
use Illuminate\Support\Facades\Log;

/**
 * Helper-Service für easybill-Integrationen.
 *
 * easybill nutzt API-Key-Authentifizierung via Bearer Token:
 *   Authorization: Bearer <api_key>
 *
 * Der API-Token wird vom Nutzer in den easybill-Einstellungen erzeugt
 * (Mein Konto → Einstellungen → API) und in credentials.api_key gespeichert.
 *
 * @see https://www.easybill.de/api/
 */
class EasybillIntegrationService
{
    protected const TEST_URL = 'https://api.easybill.de/rest/v1/customers';

    protected IntegrationConnectionResolver $resolver;

    public function __construct(IntegrationConnectionResolver $resolver)
    {
        $this->resolver = $resolver;
    }

    public function getConnectionForUser(User $user): ?IntegrationConnection
    {
        return $this->resolver->resolveForUser('easybill', $user);
    }

    public function getApiTokenForUser(User $user): ?string
    {
        $connection = $this->getConnectionForUser($user);
        if ($connection) {
            return $this->getApiToken($connection);
        }
        return null;
    }

    public function getApiToken(IntegrationConnection $connection): ?string
    {
        $credentials = $connection->credentials ?? [];
        return $credentials['api_key'] ?? null;
    }

    public function hasValidApiToken(IntegrationConnection $connection): bool
    {
        $apiKey = $this->getApiToken($connection);
        return !empty($apiKey);
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
     * Erstellt oder aktualisiert eine easybill-IntegrationConnection für einen User.
     *
     * @param int|null $connectionId Wenn gesetzt: Update dieser Connection; null = neue Connection
     */
    public function createOrUpdateConnectionForUser(User $user, string $apiToken, ?int $connectionId = null): IntegrationConnection
    {
        $integration = Integration::firstOrCreate(
            ['key' => 'easybill'],
            [
                'name' => 'easybill',
                'is_enabled' => true,
                'supported_auth_schemes' => ['api_key'],
                'meta' => [
                    'description' => 'easybill Integration für Rechnungen, Belege, Kunden und Positionen über die REST API (Bearer Token).',
                    'icon' => 'heroicon-o-document-text',
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

        Log::info('easybill connection created/updated', [
            'connection_id' => $connection->id,
            'user_id' => $user->id,
        ]);

        return $connection;
    }

    /**
     * Testet die easybill API-Verbindung gegen /customers?limit=1.
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
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiToken,
                'Accept' => 'application/json',
            ])->get(self::TEST_URL, ['limit' => 1]);

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

            $body = $response->json();
            $error = is_array($body)
                ? ($body['message'] ?? $body['error'] ?? 'Unbekannter Fehler')
                : 'Unbekannter Fehler';

            $connection->status = 'error';
            $connection->last_error = is_string($error) ? $error : json_encode($error);
            $connection->last_tested_at = now();
            $connection->save();

            return [
                'success' => false,
                'message' => 'API-Fehler (HTTP ' . $response->status() . '): ' . (is_string($error) ? $error : json_encode($error)),
            ];
        } catch (\Exception $e) {
            Log::error('easybill connection test failed', [
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
            Log::info('easybill connection deleted', [
                'user_id' => $user->id,
            ]);
            return true;
        }

        return false;
    }
}
