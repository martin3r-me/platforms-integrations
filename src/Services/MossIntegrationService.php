<?php

namespace Platform\Integrations\Services;

use Platform\Core\Models\User;
use Platform\Integrations\Models\Integration;
use Platform\Integrations\Models\IntegrationConnection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Helper-Service für Moss-Integrationen
 *
 * Moss verwendet OAuth2 Client Credentials (client_id + client_secret → Bearer Token).
 * Die Credentials werden in credentials.login (client_id) und credentials.password (client_secret) gespeichert.
 * Der Bearer Token wird im Laravel Cache gecacht (55 Min TTL bei 1h Expiry).
 */
class MossIntegrationService
{
    protected IntegrationConnectionResolver $resolver;

    public function __construct(IntegrationConnectionResolver $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * Ruft die Moss-IntegrationConnection für einen User ab
     */
    public function getConnectionForUser(User $user): ?IntegrationConnection
    {
        return $this->resolver->resolveForUser('moss', $user);
    }

    /**
     * Ruft die Credentials (Client ID + Client Secret) aus einer Moss-IntegrationConnection ab
     *
     * @return array{login: string, password: string}|null
     */
    public function getCredentials(IntegrationConnection $connection): ?array
    {
        $credentials = $connection->credentials ?? [];
        $login = $credentials['login'] ?? null;
        $password = $credentials['password'] ?? null;

        if ($login && $password) {
            return ['login' => $login, 'password' => $password];
        }

        return null;
    }

    /**
     * Prüft, ob die Connection gültige Credentials hat
     */
    public function hasValidCredentials(IntegrationConnection $connection): bool
    {
        $credentials = $this->getCredentials($connection);
        return $credentials !== null;
    }

    /**
     * Aktualisiert die Credentials einer Moss-IntegrationConnection
     */
    public function updateCredentials(IntegrationConnection $connection, string $clientId, string $clientSecret): void
    {
        $credentials = $connection->credentials ?? [];
        $credentials['login'] = $clientId;
        $credentials['password'] = $clientSecret;

        $connection->credentials = $credentials;
        $connection->auth_scheme = 'basic';
        $connection->status = 'active';
        $connection->last_error = null;
        $connection->save();
    }

    /**
     * Erstellt oder aktualisiert eine Moss-IntegrationConnection für einen User.
     *
     * @param int|null $connectionId Wenn gesetzt: Update dieser Connection; null = neue Connection
     */
    public function createOrUpdateConnectionForUser(User $user, string $clientId, string $clientSecret, ?int $connectionId = null): IntegrationConnection
    {
        $integration = Integration::firstOrCreate(
            ['key' => 'moss'],
            [
                'name' => 'Moss',
                'is_enabled' => true,
                'supported_auth_schemes' => ['basic'],
                'meta' => [
                    'description' => 'Moss Spend-Management Integration für Expenses, Suppliers, Users, Bank Accounts, Dimensions und Payment Terms.',
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

            $connection->auth_scheme = 'basic';
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
                'auth_scheme' => 'basic',
                'status' => 'active',
            ]);
        }

        $this->updateCredentials($connection, $clientId, $clientSecret);

        // Alten Token-Cache invalidieren
        $this->clearTokenCache($connection);

        Log::info('Moss connection created/updated', [
            'connection_id' => $connection->id,
            'user_id' => $user->id,
        ]);

        return $connection;
    }

    /**
     * Testet die Moss API-Verbindung
     *
     * Holt einen OAuth2 Bearer Token per Client Credentials Grant.
     *
     * @return array{success: bool, message: string, data?: array}
     */
    public function testConnection(IntegrationConnection $connection): array
    {
        $credentials = $this->getCredentials($connection);

        if (!$credentials) {
            return [
                'success' => false,
                'message' => 'Keine Moss Credentials (Client ID/Client Secret) vorhanden.',
            ];
        }

        try {
            // Token-Cache leeren, damit wir wirklich die Credentials prüfen
            $this->clearTokenCache($connection);

            $token = $this->getValidAccessToken($connection);

            if ($token) {
                $connection->status = 'active';
                $connection->last_error = null;
                $connection->last_tested_at = now();
                $connection->save();

                return [
                    'success' => true,
                    'message' => 'Moss-Verbindung erfolgreich.',
                ];
            }

            $connection->status = 'error';
            $connection->last_error = 'Token konnte nicht abgerufen werden';
            $connection->last_tested_at = now();
            $connection->save();

            return [
                'success' => false,
                'message' => 'Moss-Token konnte nicht abgerufen werden. Bitte Client ID und Client Secret prüfen.',
            ];
        } catch (\Exception $e) {
            Log::error('Moss connection test failed', [
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
     * Holt einen gültigen Access Token per OAuth2 Client Credentials Grant.
     * Token wird im Laravel Cache gecacht (55 Min TTL bei 1h Expiry).
     */
    public function getValidAccessToken(IntegrationConnection $connection): ?string
    {
        $cacheKey = "moss_token_{$connection->id}";

        return Cache::remember($cacheKey, 55 * 60, function () use ($connection) {
            return $this->fetchAccessToken($connection);
        });
    }

    /**
     * Holt einen frischen Access Token von der Moss OAuth2 API
     */
    protected function fetchAccessToken(IntegrationConnection $connection): ?string
    {
        $credentials = $this->getCredentials($connection);

        if (!$credentials) {
            return null;
        }

        $baseUrl = config('integrations.moss.api_base_url', 'https://public-api.getmoss.com');
        $tokenPath = config('integrations.moss.token_url', '/oauth2/token');
        $tokenUrl = $baseUrl . $tokenPath;

        $response = Http::asForm()
            ->timeout(15)
            ->connectTimeout(10)
            ->post($tokenUrl, [
                'grant_type' => 'client_credentials',
                'client_id' => $credentials['login'],
                'client_secret' => $credentials['password'],
            ]);

        if ($response->status() === 401 || $response->status() === 403) {
            Log::warning('Moss OAuth2: Ungültige Credentials', [
                'connection_id' => $connection->id,
                'status' => $response->status(),
            ]);
            return null;
        }

        if (!$response->successful()) {
            Log::warning('Moss OAuth2: Token-Request fehlgeschlagen', [
                'connection_id' => $connection->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        }

        $data = $response->json();
        return $data['access_token'] ?? null;
    }

    /**
     * Leert den Token-Cache für eine Connection
     */
    public function clearTokenCache(IntegrationConnection $connection): void
    {
        Cache::forget("moss_token_{$connection->id}");
    }

    /**
     * Löscht die Moss-IntegrationConnection für einen User
     */
    public function deleteConnectionForUser(User $user): bool
    {
        $connection = $this->getConnectionForUser($user);

        if ($connection) {
            $this->clearTokenCache($connection);
            $connection->delete();
            Log::info('Moss connection deleted', [
                'user_id' => $user->id,
            ]);
            return true;
        }

        return false;
    }
}
