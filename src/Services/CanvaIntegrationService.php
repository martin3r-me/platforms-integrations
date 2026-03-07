<?php

namespace Platform\Integrations\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Core\Models\User;
use Platform\Integrations\Exceptions\CanvaApiException;
use Platform\Integrations\Models\Integration;
use Platform\Integrations\Models\IntegrationConnection;

/**
 * Service für Canva Integration Management
 *
 * Verwaltet:
 * - OAuth-Token-Verwaltung (Access Token, Refresh Token)
 * - Token-Rotation und automatische Erneuerung
 * - Token-Revoke
 * - Connection-Verwaltung
 *
 * OAuth-Flow gemäß Canva Connect API Dokumentation:
 * @see https://www.canva.dev/docs/connect/authentication/
 */
class CanvaIntegrationService
{
    protected IntegrationConnectionResolver $resolver;

    public function __construct(IntegrationConnectionResolver $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * Ruft die Canva-IntegrationConnection für einen User ab
     */
    public function getConnectionForUser(User $user): ?IntegrationConnection
    {
        return $this->resolver->resolveForUser('canva', $user);
    }

    /**
     * Ruft den Access-Token für einen User ab
     * Führt bei Bedarf einen automatischen Refresh durch
     */
    public function getAccessTokenForUser(User $user, bool $autoRefresh = true): ?string
    {
        $connection = $this->getConnectionForUser($user);

        if (!$connection) {
            return null;
        }

        $accessToken = $this->getAccessToken($connection);
        if (!$accessToken) {
            return null;
        }

        if ($autoRefresh && $this->isTokenExpired($connection)) {
            try {
                $connection = $this->refreshToken($connection);
                return $this->getAccessToken($connection);
            } catch (CanvaApiException $e) {
                Log::error('Canva token refresh failed', [
                    'user_id' => $user->id,
                    'connection_id' => $connection->id,
                    'error' => $e->getMessage(),
                ]);

                $this->markConnectionAsError($connection, $e->getMessage());
                return null;
            }
        }

        return $accessToken;
    }

    /**
     * Ruft den Access-Token aus einer Connection ab
     */
    public function getAccessToken(IntegrationConnection $connection): ?string
    {
        $credentials = $connection->credentials ?? [];
        return $credentials['oauth']['access_token'] ?? null;
    }

    /**
     * Ruft den Refresh-Token aus einer Connection ab
     */
    public function getRefreshToken(IntegrationConnection $connection): ?string
    {
        $credentials = $connection->credentials ?? [];
        return $credentials['oauth']['refresh_token'] ?? null;
    }

    /**
     * Prüft, ob der Access-Token abgelaufen ist
     */
    public function isTokenExpired(IntegrationConnection $connection): bool
    {
        $credentials = $connection->credentials ?? [];
        $expiresAt = $credentials['oauth']['expires_at'] ?? null;

        if (!$expiresAt) {
            return false;
        }

        // 5 Minuten vor Ablauf bereits als abgelaufen behandeln (Buffer)
        return $expiresAt <= (now()->timestamp + 300);
    }

    /**
     * Prüft, ob die Connection einen gültigen Token hat
     */
    public function hasValidToken(IntegrationConnection $connection): bool
    {
        $accessToken = $this->getAccessToken($connection);
        return !empty($accessToken) && !$this->isTokenExpired($connection);
    }

    /**
     * Erneuert den Access-Token mit dem Refresh-Token
     *
     * @throws CanvaApiException
     */
    public function refreshToken(IntegrationConnection $connection): IntegrationConnection
    {
        $refreshToken = $this->getRefreshToken($connection);

        if (!$refreshToken) {
            throw CanvaApiException::unauthorized('Kein Refresh-Token vorhanden. Bitte Canva erneut verbinden.');
        }

        $config = $this->getOAuthConfig();

        Log::info('Canva token refresh started', [
            'connection_id' => $connection->id,
        ]);

        try {
            $response = Http::asForm()
                ->timeout(30)
                ->post($config['token_url'], [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                    'client_id' => $config['client_id'],
                    'client_secret' => $config['client_secret'],
                ]);

            if (!$response->successful()) {
                throw CanvaApiException::fromResponse($response->status(), $response->json());
            }

            $payload = $response->json();
            $connection = $this->updateConnectionTokens($connection, $payload);

            Log::info('Canva token refresh successful', [
                'connection_id' => $connection->id,
            ]);

            return $connection;
        } catch (\Exception $e) {
            if (!$e instanceof CanvaApiException) {
                throw CanvaApiException::connectionError($e->getMessage());
            }
            throw $e;
        }
    }

    /**
     * Widerruft den Token (Logout/Disconnect)
     */
    public function revokeToken(IntegrationConnection $connection): bool
    {
        $accessToken = $this->getAccessToken($connection);
        $refreshToken = $this->getRefreshToken($connection);
        $config = $this->getOAuthConfig();

        Log::info('Canva token revoke started', [
            'connection_id' => $connection->id,
        ]);

        $success = true;

        // Canva revoke endpoint
        $revokeUrl = str_replace('/token', '/revoke', $config['token_url']);

        $tokenToRevoke = $refreshToken ?? $accessToken;
        if ($tokenToRevoke) {
            try {
                $response = Http::asForm()
                    ->timeout(30)
                    ->post($revokeUrl, [
                        'token' => $tokenToRevoke,
                        'client_id' => $config['client_id'],
                        'client_secret' => $config['client_secret'],
                    ]);

                if (!$response->successful()) {
                    Log::warning('Canva token revoke failed', [
                        'connection_id' => $connection->id,
                        'status' => $response->status(),
                    ]);
                    $success = false;
                }
            } catch (\Exception $e) {
                Log::warning('Canva token revoke error', [
                    'connection_id' => $connection->id,
                    'error' => $e->getMessage(),
                ]);
                $success = false;
            }
        }

        $this->clearConnectionCredentials($connection);

        Log::info('Canva token revoke completed', [
            'connection_id' => $connection->id,
            'success' => $success,
        ]);

        return $success;
    }

    /**
     * Testet die Canva API-Verbindung
     *
     * @return array{success: bool, message: string, data?: array}
     */
    public function testConnection(IntegrationConnection $connection): array
    {
        $accessToken = $this->getAccessToken($connection);

        if (!$accessToken) {
            return [
                'success' => false,
                'message' => 'Kein Access-Token vorhanden.',
            ];
        }

        try {
            $baseUrl = config('integrations.canva.api_base_url', 'https://api.canva.com');
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Accept' => 'application/json',
            ])->timeout(15)->get($baseUrl . '/rest/v1/users/me');

            if ($response->successful()) {
                $data = $response->json();

                $connection->status = 'active';
                $connection->last_error = null;
                $connection->last_tested_at = now();
                $connection->save();

                return [
                    'success' => true,
                    'message' => 'Canva-Verbindung erfolgreich.',
                    'data' => $data,
                ];
            }

            $error = $response->json()['message'] ?? 'HTTP ' . $response->status();

            $connection->status = 'error';
            $connection->last_error = $error;
            $connection->last_tested_at = now();
            $connection->save();

            return [
                'success' => false,
                'message' => 'API-Fehler: ' . $error,
            ];
        } catch (\Exception $e) {
            Log::error('Canva connection test failed', [
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
     * Erstellt oder aktualisiert eine Canva-Connection für einen User.
     *
     * @param int|null $connectionId Wenn gesetzt: Update dieser Connection; null = neue Connection
     */
    public function createOrUpdateConnectionForUser(User $user, array $oauthData, ?int $connectionId = null): IntegrationConnection
    {
        $integration = Integration::firstOrCreate(
            ['key' => 'canva'],
            [
                'name' => 'Canva',
                'is_enabled' => true,
                'supported_auth_schemes' => ['oauth2'],
                'meta' => [
                    'description' => 'Canva Connect API – Design-Verwaltung, Exporte, Brand Templates',
                    'icon' => 'heroicon-o-paint-brush',
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

            $connection->auth_scheme = 'oauth2';
            $connection->status = 'active';
            $connection->save();
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
                'auth_scheme' => 'oauth2',
                'status' => 'active',
            ]);
            $connection->save();
        }

        $connection = $this->updateConnectionTokens($connection, $oauthData);

        Log::info('Canva connection created/updated', [
            'connection_id' => $connection->id,
            'user_id' => $user->id,
        ]);

        return $connection;
    }

    /**
     * Aktualisiert die Token-Daten in der Connection
     */
    public function updateConnectionTokens(IntegrationConnection $connection, array $tokenPayload): IntegrationConnection
    {
        $credentials = $connection->credentials ?? [];
        $expiresIn = isset($tokenPayload['expires_in']) ? (int) $tokenPayload['expires_in'] : null;
        $expiresAt = $expiresIn ? now()->addSeconds($expiresIn)->timestamp : null;

        $scope = $tokenPayload['scope'] ?? null;
        $scopeArray = [];
        if ($scope && is_string($scope)) {
            $scopeArray = explode(' ', $scope);
        } elseif (is_array($scope)) {
            $scopeArray = $scope;
        }

        $credentials['oauth'] = array_merge($credentials['oauth'] ?? [], [
            'access_token' => $tokenPayload['access_token'] ?? null,
            'refresh_token' => $tokenPayload['refresh_token'] ?? ($credentials['oauth']['refresh_token'] ?? null),
            'token_type' => $tokenPayload['token_type'] ?? 'Bearer',
            'scope' => $scope,
            'scope_array' => $scopeArray,
            'expires_in' => $expiresIn,
            'expires_at' => $expiresAt,
            'token_issued_at' => now()->timestamp,
            'owner_user_id' => $connection->owner_user_id,
        ]);

        $connection->credentials = $credentials;
        $connection->auth_scheme = 'oauth2';
        $connection->status = 'active';
        $connection->last_error = null;
        $connection->save();

        return $connection;
    }

    /**
     * Löscht die Credentials einer Connection
     */
    public function clearConnectionCredentials(IntegrationConnection $connection): void
    {
        $connection->credentials = null;
        $connection->status = 'disabled';
        $connection->save();
    }

    /**
     * Markiert eine Connection als fehlerhaft
     */
    public function markConnectionAsError(IntegrationConnection $connection, string $errorMessage): void
    {
        $connection->status = 'error';
        $connection->last_error = $errorMessage;
        $connection->save();
    }

    /**
     * Gibt die OAuth-Konfiguration zurück
     *
     * @throws CanvaApiException
     */
    public function getOAuthConfig(): array
    {
        $providers = config('integrations.oauth2.providers', []);
        $config = $providers['canva'] ?? null;

        if (!$config || empty($config['client_id'])) {
            throw CanvaApiException::noConnection();
        }

        return [
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'token_url' => $config['token_url'] ?? 'https://api.canva.com/rest/v1/oauth/token',
        ];
    }
}
