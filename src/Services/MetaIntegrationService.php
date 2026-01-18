<?php

namespace Platform\Integrations\Services;

use Platform\Core\Models\User;
use Platform\Integrations\Models\Integration;
use Platform\Integrations\Models\IntegrationConnection;

/**
 * Helper-Service für Meta-Integrationen
 * 
 * Vereinfacht die Arbeit mit Meta-Integrationen über IntegrationConnection
 */
class MetaIntegrationService
{
    protected IntegrationConnectionResolver $resolver;

    public function __construct(IntegrationConnectionResolver $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * Ruft die Meta-IntegrationConnection für einen User ab
     */
    public function getConnectionForUser(User $user): ?IntegrationConnection
    {
        return $this->resolver->resolveForUser('meta', $user);
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
}
