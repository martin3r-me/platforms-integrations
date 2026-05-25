<?php

namespace Platform\Integrations\Services;

use Platform\Core\Models\User;
use Platform\Integrations\Models\IntegrationConnection;
use Illuminate\Support\Facades\Log;

/**
 * Integration-Service für Google Search Console
 *
 * Token-Handling via OAuth2Service (Standard-Flow).
 * Read-Only-Zugriff auf Sites, Search Analytics, Sitemaps und URL Inspection.
 */
class GoogleSearchConsoleIntegrationService
{
    protected IntegrationConnectionResolver $resolver;

    public function __construct(IntegrationConnectionResolver $resolver)
    {
        $this->resolver = $resolver;
    }

    public function getConnectionForUser(User $user): ?IntegrationConnection
    {
        return $this->resolver->resolveForUser('google_search_console', $user);
    }

    public function getAccessToken(IntegrationConnection $connection): ?string
    {
        $credentials = $connection->credentials ?? [];
        return $credentials['oauth']['access_token'] ?? null;
    }

    public function isTokenExpired(IntegrationConnection $connection): bool
    {
        $credentials = $connection->credentials ?? [];
        $expiresAt = $credentials['oauth']['expires_at'] ?? null;

        if (!$expiresAt) {
            return false;
        }

        // 5 Minuten Puffer vor tatsächlichem Ablauf
        return now()->timestamp >= ($expiresAt - 300);
    }

    /**
     * Gibt einen gültigen Access Token zurück, refresht automatisch falls nötig.
     */
    public function getValidAccessToken(IntegrationConnection $connection): ?string
    {
        if ($this->isTokenExpired($connection)) {
            $newToken = $this->refreshToken($connection);
            if ($newToken) {
                return $newToken;
            }
        }

        return $this->getAccessToken($connection);
    }

    /**
     * Refresht den Access Token via OAuth2Service.
     */
    public function refreshToken(IntegrationConnection $connection): ?string
    {
        $refreshToken = $connection->credentials['oauth']['refresh_token'] ?? null;
        if (!$refreshToken) {
            Log::warning('Google Search Console: Kein Refresh-Token vorhanden', [
                'connection_id' => $connection->id,
            ]);
            return null;
        }

        try {
            $oauth2Service = app(OAuth2Service::class);
            $connection = $oauth2Service->refreshToken('google_search_console', $connection);

            Log::info('Google Search Console: Token refreshed', [
                'connection_id' => $connection->id,
            ]);

            return $this->getAccessToken($connection);
        } catch (\Exception $e) {
            Log::error('Google Search Console: Token-Refresh fehlgeschlagen', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Testet die Verbindung durch Abruf der Sites.
     *
     * @return array{success: bool, message: string}
     */
    public function testConnection(IntegrationConnection $connection): array
    {
        $token = $this->getValidAccessToken($connection);

        if (!$token) {
            return [
                'success' => false,
                'message' => 'Kein gültiger Access-Token. Bitte erneut verbinden.',
            ];
        }

        try {
            $baseUrl = config('integrations.google_search_console.api_base_url', 'https://www.googleapis.com/webmasters/v3');
            $response = \Illuminate\Support\Facades\Http::withToken($token)
                ->timeout(10)
                ->get($baseUrl . '/sites');

            if ($response->successful()) {
                $data = $response->json();
                $siteCount = count($data['siteEntry'] ?? []);

                $connection->status = 'active';
                $connection->last_error = null;
                $connection->last_tested_at = now();
                $connection->save();

                return [
                    'success' => true,
                    'message' => "Verbindung erfolgreich. {$siteCount} Site(s) gefunden.",
                ];
            }

            $connection->status = 'error';
            $connection->last_error = 'API-Fehler: HTTP ' . $response->status();
            $connection->last_tested_at = now();
            $connection->save();

            return [
                'success' => false,
                'message' => 'API-Fehler: HTTP ' . $response->status() . ' — ' . ($response->json()['error']['message'] ?? $response->body()),
            ];
        } catch (\Exception $e) {
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
}
