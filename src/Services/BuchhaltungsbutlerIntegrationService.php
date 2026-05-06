<?php

namespace Platform\Integrations\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Core\Models\User;
use Platform\Integrations\Models\Integration;
use Platform\Integrations\Models\IntegrationConnection;

/**
 * Helper-Service für BuchhaltungsButler-Integrationen.
 *
 * Authentifizierung:
 * - HTTP Basic Auth: api_client (Username) + api_secret (Password)
 * - api_key als Body-Feld in jedem Request (kundenspezifisch)
 *
 * Alle drei Werte werden gemeinsam in credentials gespeichert.
 *
 * @see https://app.buchhaltungsbutler.de/docs/api/v1/
 */
class BuchhaltungsbutlerIntegrationService
{
    protected const TEST_URL = 'https://webapp.buchhaltungsbutler.de/api/v1/settings/get/debtors';

    protected IntegrationConnectionResolver $resolver;

    public function __construct(IntegrationConnectionResolver $resolver)
    {
        $this->resolver = $resolver;
    }

    public function getConnectionForUser(User $user): ?IntegrationConnection
    {
        return $this->resolver->resolveForUser('buchhaltungsbutler', $user);
    }

    /**
     * @return array{api_client: ?string, api_secret: ?string, api_key: ?string}
     */
    public function getCredentials(IntegrationConnection $connection): array
    {
        $c = $connection->credentials ?? [];
        return [
            'api_client' => $c['api_client'] ?? null,
            'api_secret' => $c['api_secret'] ?? null,
            'api_key'    => $c['api_key'] ?? null,
        ];
    }

    public function hasValidCredentials(IntegrationConnection $connection): bool
    {
        $c = $this->getCredentials($connection);
        return !empty($c['api_client']) && !empty($c['api_secret']) && !empty($c['api_key']);
    }

    public function updateCredentials(
        IntegrationConnection $connection,
        string $apiClient,
        string $apiSecret,
        string $apiKey
    ): void {
        $credentials = $connection->credentials ?? [];
        $credentials['api_client'] = $apiClient;
        $credentials['api_secret'] = $apiSecret;
        $credentials['api_key']    = $apiKey;

        $connection->credentials  = $credentials;
        $connection->auth_scheme  = 'api_key';
        $connection->status       = 'active';
        $connection->last_error   = null;
        $connection->save();
    }

    public function createOrUpdateConnectionForUser(
        User $user,
        string $apiClient,
        string $apiSecret,
        string $apiKey,
        ?int $connectionId = null
    ): IntegrationConnection {
        $integration = Integration::firstOrCreate(
            ['key' => 'buchhaltungsbutler'],
            [
                'name' => 'BuchhaltungsButler',
                'is_enabled' => true,
                'supported_auth_schemes' => ['api_key'],
                'meta' => [
                    'description' => 'BuchhaltungsButler Integration für Rechnungen, Angebote und Gutschriften.',
                    'icon' => 'heroicon-o-banknotes',
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
            $connection->status      = 'active';
        } else {
            $isFirst = !IntegrationConnection::query()
                ->where('integration_id', $integration->id)
                ->where('owner_user_id', $user->id)
                ->exists();

            $connection = new IntegrationConnection([
                'integration_id' => $integration->id,
                'owner_user_id'  => $user->id,
                'name'           => IntegrationConnection::generateName($integration->id, $user->id, $integration->name),
                'is_default'     => $isFirst,
                'auth_scheme'    => 'api_key',
                'status'         => 'active',
            ]);
        }

        $this->updateCredentials($connection, $apiClient, $apiSecret, $apiKey);

        Log::info('BuchhaltungsButler connection created/updated', [
            'connection_id' => $connection->id,
            'user_id'       => $user->id,
        ]);

        return $connection;
    }

    /**
     * Testet die Verbindung gegen /settings/get/debtors mit limit=1.
     *
     * @return array{success: bool, message: string, data?: array}
     */
    public function testConnection(IntegrationConnection $connection): array
    {
        $c = $this->getCredentials($connection);

        if (!$c['api_client'] || !$c['api_secret'] || !$c['api_key']) {
            return [
                'success' => false,
                'message' => 'Credentials unvollständig (api_client, api_secret oder api_key fehlt).',
            ];
        }

        try {
            $response = Http::withBasicAuth($c['api_client'], $c['api_secret'])
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ])
                ->post(self::TEST_URL, [
                    'api_key' => $c['api_key'],
                    'limit'   => 1,
                    'offset'  => 0,
                ]);

            if ($response->successful()) {
                $connection->status         = 'active';
                $connection->last_error     = null;
                $connection->last_tested_at = now();
                $connection->save();

                return [
                    'success' => true,
                    'message' => 'Verbindung erfolgreich.',
                    'data'    => $response->json(),
                ];
            }

            $data  = $response->json();
            $error = is_array($data)
                ? ($data['error'] ?? $data['message'] ?? 'API-Fehler')
                : 'API-Fehler';

            $connection->status         = 'error';
            $connection->last_error     = is_string($error) ? $error : json_encode($error);
            $connection->last_tested_at = now();
            $connection->save();

            return [
                'success' => false,
                'message' => 'API-Fehler (HTTP ' . $response->status() . '): ' . (is_string($error) ? $error : json_encode($error)),
            ];
        } catch (\Throwable $e) {
            Log::error('BuchhaltungsButler connection test failed', [
                'connection_id' => $connection->id,
                'error'         => $e->getMessage(),
            ]);

            $connection->status         = 'error';
            $connection->last_error     = $e->getMessage();
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
            Log::info('BuchhaltungsButler connection deleted', ['user_id' => $user->id]);
            return true;
        }

        return false;
    }
}
