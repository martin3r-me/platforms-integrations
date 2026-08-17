<?php

namespace Platform\Integrations\Services;

use Platform\Core\Models\User;
use Platform\Integrations\Models\Integration;
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
        // Service-Account: Bearer-Token per JWT-Assertion minten (kein OAuth-Refresh-Flow).
        // Frische SA-Connections haben noch keinen Token/kein expires_at → dann sofort minten.
        if ($this->isServiceAccount($connection)) {
            if (!$this->getAccessToken($connection) || $this->isTokenExpired($connection)) {
                $minted = $this->mintServiceAccountToken($connection);
                if ($minted) {
                    return $minted;
                }
            }

            return $this->getAccessToken($connection);
        }

        if ($this->isTokenExpired($connection)) {
            $newToken = $this->refreshToken($connection);
            if ($newToken) {
                return $newToken;
            }
        }

        return $this->getAccessToken($connection);
    }

    /**
     * Ob es sich um eine Service-Account-Connection handelt (statt OAuth-User-Flow).
     */
    public function isServiceAccount(IntegrationConnection $connection): bool
    {
        if ($connection->auth_scheme === 'service_account') {
            return true;
        }

        $credentials = $connection->credentials ?? [];
        return !empty($credentials['service_account']);
    }

    /**
     * Mintet einen OAuth2-Bearer-Token aus dem hinterlegten Service-Account-Key
     * (signierte JWT-Assertion via google/apiclient) und legt ihn – wie beim OAuth-Flow –
     * unter credentials.oauth.access_token/expires_at ab.
     *
     * @return string|null Der Access-Token oder null bei Fehler.
     */
    public function mintServiceAccountToken(IntegrationConnection $connection): ?string
    {
        $serviceAccount = $connection->credentials['service_account'] ?? null;
        if (empty($serviceAccount)) {
            Log::warning('Google Search Console: Kein Service-Account-Key vorhanden', [
                'connection_id' => $connection->id,
            ]);
            return null;
        }

        try {
            $client = new \Google\Client();
            $client->setAuthConfig($serviceAccount);
            $client->setScopes(['https://www.googleapis.com/auth/webmasters.readonly']);

            $token = $client->fetchAccessTokenWithAssertion();

            if (empty($token['access_token'])) {
                $error = $token['error_description'] ?? ($token['error'] ?? 'Unbekannter Fehler');
                throw new \RuntimeException('Kein access_token erhalten: ' . $error);
            }

            $expiresIn = isset($token['expires_in']) ? (int) $token['expires_in'] : 3600;

            $credentials = $connection->credentials ?? [];
            $credentials['oauth'] = array_merge($credentials['oauth'] ?? [], [
                'access_token'    => $token['access_token'],
                'token_type'      => $token['token_type'] ?? 'Bearer',
                'expires_in'      => $expiresIn,
                'expires_at'      => now()->addSeconds($expiresIn)->timestamp,
                'token_issued_at' => now()->timestamp,
            ]);

            $connection->credentials = $credentials;
            $connection->last_error = null;
            $connection->save();

            Log::info('Google Search Console: Service-Account-Token gemintet', [
                'connection_id' => $connection->id,
            ]);

            return $token['access_token'];
        } catch (\Throwable $e) {
            $connection->status = 'error';
            $connection->last_error = 'Service-Account-Token fehlgeschlagen: ' . $e->getMessage();
            $connection->save();

            Log::error('Google Search Console: Service-Account-Token minten fehlgeschlagen', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Erstellt oder aktualisiert eine Service-Account-basierte GSC-Connection für einen User.
     *
     * Der JSON-Key wird validiert und verschlüsselt (EncryptedJson) in
     * credentials.service_account abgelegt. Gespiegelt an
     * {@see DataForSeoIntegrationService::createOrUpdateConnectionForUser()}.
     *
     * @param int|null $connectionId Wenn gesetzt: Update dieser Connection; null = neue Connection.
     * @throws \RuntimeException bei ungültigem Key/Connection.
     */
    public function createOrUpdateServiceAccountConnection(User $user, string $serviceAccountJson, ?int $connectionId = null): IntegrationConnection
    {
        $decoded = json_decode($serviceAccountJson, true);

        if (!is_array($decoded)) {
            throw new \RuntimeException('Der Service-Account-Key ist kein gültiges JSON.');
        }

        if (($decoded['type'] ?? null) !== 'service_account'
            || empty($decoded['client_email'])
            || empty($decoded['private_key'])) {
            throw new \RuntimeException('Kein gültiger Service-Account-Key (erwartet type=service_account mit client_email und private_key).');
        }

        $integration = Integration::firstOrCreate(
            ['key' => 'google_search_console'],
            [
                'name' => 'Google Search Console',
                'is_enabled' => true,
                'supported_auth_schemes' => ['oauth2', 'service_account'],
                'meta' => [
                    'description' => 'Google Search Console Integration für Search Analytics, Sitemaps und URL Inspection.',
                    'icon' => 'heroicon-o-magnifying-glass-circle',
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
            ]);
        }

        // Neuer Key → alten geminten Token verwerfen, damit beim nächsten Zugriff frisch gemintet wird.
        $credentials = $connection->credentials ?? [];
        $credentials['service_account'] = $decoded;
        unset($credentials['oauth']);

        $connection->integration_id = $integration->id;
        $connection->owner_user_id = $user->id;
        $connection->auth_scheme = 'service_account';
        $connection->status = 'active';
        $connection->last_error = null;
        $connection->credentials = $credentials;
        $connection->save();

        Log::info('Google Search Console Service-Account connection created/updated', [
            'connection_id' => $connection->id,
            'user_id' => $user->id,
        ]);

        return $connection;
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
