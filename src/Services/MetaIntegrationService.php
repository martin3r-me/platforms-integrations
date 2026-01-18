<?php

namespace Platform\Integrations\Services;

use Platform\Core\Models\User;
use Platform\Integrations\Models\Integration;
use Platform\Integrations\Models\IntegrationConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Helper-Service für Meta-Integrationen
 * 
 * Meta Tokens werden ausschließlich über OAuth-Flow gespeichert
 * in IntegrationConnection.credentials.oauth
 */
class MetaIntegrationService
{
    protected IntegrationConnectionResolver $resolver;

    public function __construct(IntegrationConnectionResolver $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * Ruft die Meta-IntegrationConnection für einen User ab (OAuth)
     */
    public function getConnectionForUser(User $user): ?IntegrationConnection
    {
        return $this->resolver->resolveForUser('meta', $user);
    }

    /**
     * Ruft den Access Token für einen User ab (nur OAuth)
     */
    public function getAccessTokenForUser(User $user): ?string
    {
        $connection = $this->getConnectionForUser($user);
        if ($connection) {
            return $this->getAccessToken($connection);
        }
        return null;
    }

    /**
     * Ruft den gültigen Access Token für einen User ab (mit Refresh, nur OAuth)
     */
    public function getValidAccessTokenForUser(User $user): ?string
    {
        $connection = $this->getConnectionForUser($user);
        if ($connection) {
            return $this->getValidAccessToken($connection);
        }
        return null;
    }

    /**
     * Ruft den Access Token aus einer Meta-IntegrationConnection ab
     */
    public function getAccessToken(IntegrationConnection $connection): ?string
    {
        $credentials = $connection->credentials ?? [];
        return $credentials['oauth']['access_token'] ?? null;
    }

    /**
     * Ruft den Refresh Token aus einer Meta-IntegrationConnection ab
     */
    public function getRefreshToken(IntegrationConnection $connection): ?string
    {
        $credentials = $connection->credentials ?? [];
        return $credentials['oauth']['refresh_token'] ?? null;
    }

    /**
     * Prüft, ob der Token abgelaufen ist
     */
    public function isTokenExpired(IntegrationConnection $connection): bool
    {
        $credentials = $connection->credentials ?? [];
        $expiresAt = $credentials['oauth']['expires_at'] ?? null;
        
        if (!$expiresAt) {
            return false; // Kein Ablaufdatum = unbegrenzt gültig
        }

        return now()->timestamp >= $expiresAt;
    }

    /**
     * Prüft, ob der Token bald abläuft (innerhalb der nächsten 24 Stunden)
     */
    public function isTokenExpiringSoon(IntegrationConnection $connection): bool
    {
        $credentials = $connection->credentials ?? [];
        $expiresAt = $credentials['oauth']['expires_at'] ?? null;
        
        if (!$expiresAt) {
            return false;
        }

        $expiresIn = $expiresAt - now()->timestamp;
        return $expiresIn > 0 && $expiresIn < 86400; // Weniger als 24 Stunden
    }

    /**
     * Aktualisiert die Credentials einer Meta-IntegrationConnection
     */
    public function updateCredentials(IntegrationConnection $connection, array $oauthData): void
    {
        $credentials = $connection->credentials ?? [];
        $credentials['oauth'] = array_merge($credentials['oauth'] ?? [], $oauthData);
        
        $connection->credentials = $credentials;
        $connection->status = 'active';
        $connection->last_error = null;
        $connection->save();
    }

    /**
     * Erstellt oder aktualisiert eine Meta-IntegrationConnection für einen User
     */
    public function createOrUpdateConnectionForUser(User $user, array $oauthData): IntegrationConnection
    {
        $integration = Integration::where('key', 'meta')->firstOrFail();

        $connection = IntegrationConnection::query()
            ->where('integration_id', $integration->id)
            ->where('owner_user_id', $user->id)
            ->first();

        if (!$connection) {
            $connection = new IntegrationConnection([
                'integration_id' => $integration->id,
                'owner_user_id' => $user->id,
                'auth_scheme' => 'oauth2',
                'status' => 'active',
            ]);
        }

        $this->updateCredentials($connection, $oauthData);

        return $connection;
    }

    /**
     * Prüft und aktualisiert Token, falls nötig
     */
    public function refreshTokenIfNeeded(IntegrationConnection $connection): ?string
    {
        if (!$this->isTokenExpired($connection) && !$this->isTokenExpiringSoon($connection)) {
            return null; // Token ist noch gültig
        }

        return $this->refreshToken($connection);
    }

    /**
     * Aktualisiert den Access Token
     */
    public function refreshToken(IntegrationConnection $connection): ?string
    {
        $apiVersion = config('integrations.oauth2.providers.meta.api_version', '21.0');
        $accessToken = $this->getAccessToken($connection);
        
        if (!$accessToken) {
            return null;
        }
        
        try {
            $response = Http::get("https://graph.facebook.com/debug_token", [
                'input_token' => $accessToken,
                'access_token' => $accessToken,
            ]);

            $data = $response->json();

            if (isset($data['data']['expires_at'])) {
                $expiresAt = $data['data']['expires_at'];
                $expiresIn = $expiresAt - time();
                
                if ($expiresIn < 86400) { // Weniger als 24 Stunden
                    $clientId = config('integrations.oauth2.providers.meta.client_id');
                    $clientSecret = config('integrations.oauth2.providers.meta.client_secret');
                    
                    $refreshResponse = Http::get("https://graph.facebook.com/{$apiVersion}/oauth/access_token", [
                        'grant_type' => 'fb_exchange_token',
                        'client_id' => $clientId,
                        'client_secret' => $clientSecret,
                        'fb_exchange_token' => $accessToken,
                    ]);

                    $refreshData = $refreshResponse->json();

                    if (isset($refreshData['access_token'])) {
                        $this->updateCredentials($connection, [
                            'access_token' => $refreshData['access_token'],
                            'expires_at' => now()->addSeconds($refreshData['expires_in'] ?? 0)->timestamp,
                        ]);
                        
                        Log::info('Meta Token refreshed', [
                            'connection_id' => $connection->id,
                            'user_id' => $connection->owner_user_id,
                        ]);
                        
                        return $refreshData['access_token'];
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to refresh Meta token', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Gibt den gültigen Access Token zurück (refresht automatisch, falls nötig)
     */
    public function getValidAccessToken(IntegrationConnection $connection): ?string
    {
        if ($this->isTokenExpired($connection) || $this->isTokenExpiringSoon($connection)) {
            $newToken = $this->refreshTokenIfNeeded($connection);
            if ($newToken) {
                return $newToken;
            }
        }

        return $this->getAccessToken($connection);
    }
}
