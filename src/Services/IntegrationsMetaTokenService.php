<?php

namespace Platform\Integrations\Services;

use Platform\Integrations\Models\IntegrationsMetaToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service für Meta OAuth Token Management
 * 
 * @deprecated Für Meta wird dieser Service nicht mehr verwendet.
 * Verwende stattdessen MetaIntegrationService mit IntegrationConnection.
 * Dieser Service bleibt für andere Integrations, die manuelle Token-Eingabe benötigen.
 */
class IntegrationsMetaTokenService
{
    /**
     * Prüft und aktualisiert Token, falls nötig
     */
    public function refreshTokenIfNeeded(IntegrationsMetaToken $token): ?string
    {
        if (!$token->isExpired() && !$token->isExpiringSoon()) {
            return null; // Token ist noch gültig
        }

        return $this->refreshToken($token);
    }

    /**
     * Aktualisiert den Access Token
     */
    public function refreshToken(IntegrationsMetaToken $token): ?string
    {
        $apiVersion = config('integrations.oauth2.providers.meta.api_version', '21.0');
        
        try {
            $response = Http::get("https://graph.facebook.com/debug_token", [
                'input_token' => $token->access_token,
                'access_token' => $token->access_token,
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
                        'fb_exchange_token' => $token->access_token,
                    ]);

                    $refreshData = $refreshResponse->json();

                    if (isset($refreshData['access_token'])) {
                        $token->access_token = $refreshData['access_token'];
                        $token->expires_at = now()->addSeconds($refreshData['expires_in'] ?? 0);
                        $token->save();
                        
                        Log::info('Meta Token refreshed', [
                            'token_id' => $token->id,
                            'user_id' => $token->user_id,
                        ]);
                        
                        return $refreshData['access_token'];
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to refresh Meta token', [
                'token_id' => $token->id,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Gibt den gültigen Access Token zurück (refresht automatisch, falls nötig)
     */
    public function getValidAccessToken(IntegrationsMetaToken $token): ?string
    {
        if ($token->isExpired() || $token->isExpiringSoon()) {
            $newToken = $this->refreshTokenIfNeeded($token);
            if ($newToken) {
                return $newToken;
            }
        }

        return $token->access_token;
    }
}
