<?php

namespace Platform\Integrations\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Platform\Integrations\Models\Integration;
use Platform\Integrations\Models\IntegrationConnection;

class OAuth2Service
{
    public function buildAuthorizeUrl(string $integrationKey, string $state): string
    {
        $cfg = $this->getProviderConfig($integrationKey);
        $scopes = $cfg['scopes'] ?? [];

        // URL dynamisch bauen (für Meta mit api_version)
        $authorizeUrl = $cfg['authorize_url'] ?? null;
        if (!$authorizeUrl && isset($cfg['authorize_url_template'])) {
            $version = $cfg['api_version'] ?? '21.0';
            // Entferne "v" am Anfang, falls vorhanden (Template fügt es hinzu)
            $version = ltrim($version, 'vV');
            $authorizeUrl = str_replace('{version}', $version, $cfg['authorize_url_template']);
        }

        if (!$authorizeUrl) {
            throw new \RuntimeException("authorize_url fehlt für '{$integrationKey}'.");
        }

        $params = [
            'response_type' => 'code',
            'client_id' => $cfg['client_id'],
            'redirect_uri' => $this->redirectUri($integrationKey),
            'scope' => is_array($scopes) ? implode(' ', $scopes) : (string) $scopes,
            'state' => $state,
        ];

        return rtrim($authorizeUrl, '?') . '?' . http_build_query($params);
    }

    public function redirectUri(string $integrationKey): string
    {
        $callbackRoute = route('integrations.oauth2.callback', ['integrationKey' => $integrationKey]);
        
        // Prüfe ob redirect_domain in der Config gesetzt ist
        $cfg = $this->getProviderConfig($integrationKey);
        $redirectDomain = $cfg['redirect_domain'] ?? null;
        
        if ($redirectDomain) {
            // Wenn redirect_domain gesetzt ist, diese verwenden
            if (filter_var($callbackRoute, FILTER_VALIDATE_URL)) {
                // Absolute URL: nur den Pfad extrahieren
                $path = parse_url($callbackRoute, PHP_URL_PATH);
                $query = parse_url($callbackRoute, PHP_URL_QUERY);
                $redirectUri = rtrim($redirectDomain, '/') . $path;
                if ($query) {
                    $redirectUri .= '?' . $query;
                }
            } else {
                // Relative URL: direkt anhängen
                $redirectUri = rtrim($redirectDomain, '/') . '/' . ltrim($callbackRoute, '/');
            }
            return $redirectUri;
        }
        
        // Fallback: absolute URL erstellen (aus route())
        if (filter_var($callbackRoute, FILTER_VALIDATE_URL)) {
            return $callbackRoute;
        }
        
        return url($callbackRoute);
    }

    /**
     * Exchange authorization code for tokens and persist in Connection.
     *
     * Expected session keys (set by Controller):
     * - integrations.oauth2.state
     * - integrations.oauth2.owner_user_id
     */
    public function handleCallback(Request $request, string $integrationKey): IntegrationConnection
    {
        \Log::info('OAuth2 handleCallback Start', [
            'integration_key' => $integrationKey,
            'has_state' => $request->has('state'),
            'has_code' => $request->has('code'),
            'session_id' => $request->session()->getId(),
            'session_data' => [
                'has_state' => $request->session()->has('integrations.oauth2.state'),
                'has_owner_user_id' => $request->session()->has('integrations.oauth2.owner_user_id'),
            ],
        ]);

        $cfg = $this->getProviderConfig($integrationKey);

        $state = (string) $request->query('state', '');
        $expectedState = (string) $request->session()->pull('integrations.oauth2.state');
        
        \Log::info('OAuth2 State Check', [
            'received_state' => $state,
            'expected_state' => $expectedState,
            'match' => hash_equals($expectedState, $state),
        ]);

        if (!$state || !$expectedState || !hash_equals($expectedState, $state)) {
            throw new \RuntimeException('Ungültiger OAuth state.');
        }

        $code = (string) $request->query('code', '');
        if (!$code) {
            $err = (string) $request->query('error', 'OAuth error');
            throw new \RuntimeException('OAuth Callback ohne code: ' . $err);
        }

        // Versuche owner_user_id aus Session zu holen
        $ownerUserId = (int) $request->session()->pull('integrations.oauth2.owner_user_id');
        
        // Fallback: Wenn Session-Daten fehlen, verwende eingeloggten User
        if ($ownerUserId <= 0 && $request->user()) {
            $ownerUserId = $request->user()->id;
            \Log::info('OAuth2 Owner User ID from authenticated user', [
                'owner_user_id' => $ownerUserId,
            ]);
        }
        
        \Log::info('OAuth2 Owner User ID', [
            'owner_user_id' => $ownerUserId,
            'from_session' => $request->session()->has('integrations.oauth2.owner_user_id'),
            'from_auth' => $request->user() !== null,
        ]);

        if ($ownerUserId <= 0) {
            throw new \RuntimeException('Owner-User-ID fehlt. Bitte stelle sicher, dass du eingeloggt bist.');
        }

        $integration = Integration::query()->where('key', $integrationKey)->first();
        
        if (!$integration) {
            \Log::error('OAuth2 Integration Not Found', [
                'integration_key' => $integrationKey,
            ]);
            throw new \RuntimeException("Integration '{$integrationKey}' nicht gefunden. Bitte zuerst die Integration in der Datenbank anlegen.");
        }

        \Log::info('OAuth2 Integration Found', [
            'integration_id' => $integration->id,
            'integration_key' => $integration->key,
        ]);

        // Token URL dynamisch bauen (für Meta mit api_version)
        $tokenUrl = $cfg['token_url'] ?? null;
        if (!$tokenUrl && isset($cfg['token_url_template'])) {
            $version = $cfg['api_version'] ?? '21.0';
            // Entferne "v" am Anfang, falls vorhanden (Template fügt es hinzu)
            $version = ltrim($version, 'vV');
            $tokenUrl = str_replace('{version}', $version, $cfg['token_url_template']);
        }

        if (!$tokenUrl) {
            throw new \RuntimeException("token_url fehlt für '{$integrationKey}'.");
        }

        $tokenParams = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri($integrationKey),
            'client_id' => $cfg['client_id'],
        ];

        // Meta benötigt client_secret als Query-Parameter, nicht im Body
        if ($cfg['client_secret'] ?? null) {
            $tokenParams['client_secret'] = $cfg['client_secret'];
        }

        \Log::info('OAuth2 Token Exchange', [
            'integration_key' => $integrationKey,
            'token_url' => $tokenUrl,
            'redirect_uri' => $this->redirectUri($integrationKey),
            'has_client_secret' => !empty($cfg['client_secret']),
        ]);

        $resp = Http::asForm()->post($tokenUrl, $tokenParams);

        if (!$resp->successful()) {
            \Log::error('OAuth2 Token Exchange Failed', [
                'integration_key' => $integrationKey,
                'status' => $resp->status(),
                'body' => $resp->body(),
                'json' => $resp->json(),
            ]);
            throw new \RuntimeException('Token Exchange fehlgeschlagen: ' . $resp->body());
        }

        $payload = $resp->json();
        
        \Log::info('OAuth2 Token Exchange Success', [
            'integration_key' => $integrationKey,
            'has_access_token' => !empty($payload['access_token']),
            'has_refresh_token' => !empty($payload['refresh_token']),
            'expires_in' => $payload['expires_in'] ?? null,
        ]);

        $expiresIn = isset($payload['expires_in']) ? (int) $payload['expires_in'] : null;
        $expiresAt = $expiresIn ? now()->addSeconds($expiresIn)->timestamp : null;

        $connection = IntegrationConnection::query()
            ->where('integration_id', $integration->id)
            ->where('owner_user_id', $ownerUserId)
            ->first();

        if (!$connection) {
            $connection = new IntegrationConnection([
                'integration_id' => $integration->id,
                'owner_user_id' => $ownerUserId,
            ]);
        }

        $credentials = $connection->credentials ?? [];
        $credentials['oauth'] = array_merge($credentials['oauth'] ?? [], [
            'access_token' => $payload['access_token'] ?? null,
            'refresh_token' => $payload['refresh_token'] ?? ($credentials['oauth']['refresh_token'] ?? null),
            'token_type' => $payload['token_type'] ?? 'Bearer',
            'scope' => $payload['scope'] ?? null,
            'expires_at' => $expiresAt,
        ]);

        $connection->auth_scheme = 'oauth2';
        $connection->status = 'active';
        $connection->last_error = null;
        $connection->credentials = $credentials;
        
        \Log::info('OAuth2 Saving Connection', [
            'integration_id' => $integration->id,
            'owner_user_id' => $ownerUserId,
            'has_credentials' => !empty($credentials),
            'has_oauth' => !empty($credentials['oauth']),
        ]);

        $connection->save();

        \Log::info('OAuth2 Connection Saved', [
            'connection_id' => $connection->id,
            'saved' => $connection->exists,
        ]);

        return $connection;
    }

    /**
     * Refresh tokens for a connection (if refresh_token available).
     */
    public function refreshToken(string $integrationKey, IntegrationConnection $connection): IntegrationConnection
    {
        $cfg = $this->getProviderConfig($integrationKey);
        $oauth = ($connection->credentials['oauth'] ?? []);
        $refreshToken = $oauth['refresh_token'] ?? null;
        if (!$refreshToken) {
            throw new \RuntimeException('Kein refresh_token vorhanden.');
        }

        // Token URL dynamisch bauen (für Meta mit api_version)
        $tokenUrl = $cfg['token_url'] ?? null;
        if (!$tokenUrl && isset($cfg['token_url_template'])) {
            $version = $cfg['api_version'] ?? '21.0';
            // Entferne "v" am Anfang, falls vorhanden (Template fügt es hinzu)
            $version = ltrim($version, 'vV');
            $tokenUrl = str_replace('{version}', $version, $cfg['token_url_template']);
        }

        if (!$tokenUrl) {
            throw new \RuntimeException("token_url fehlt für '{$integrationKey}'.");
        }

        $resp = Http::asForm()->post($tokenUrl, [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $cfg['client_id'],
            'client_secret' => $cfg['client_secret'] ?? null,
        ]);

        if (!$resp->successful()) {
            throw new \RuntimeException('Token Refresh fehlgeschlagen: ' . $resp->body());
        }

        $payload = $resp->json();
        $expiresIn = isset($payload['expires_in']) ? (int) $payload['expires_in'] : null;
        $expiresAt = $expiresIn ? now()->addSeconds($expiresIn)->timestamp : null;

        $credentials = $connection->credentials ?? [];
        $credentials['oauth'] = array_merge($credentials['oauth'] ?? [], [
            'access_token' => $payload['access_token'] ?? null,
            'refresh_token' => $payload['refresh_token'] ?? $refreshToken,
            'token_type' => $payload['token_type'] ?? ($credentials['oauth']['token_type'] ?? 'Bearer'),
            'scope' => $payload['scope'] ?? ($credentials['oauth']['scope'] ?? null),
            'expires_at' => $expiresAt,
        ]);

        $connection->credentials = $credentials;
        $connection->status = 'active';
        $connection->last_error = null;
        $connection->save();

        return $connection;
    }

    public function newState(): string
    {
        return Str::random(32);
    }

    protected function getProviderConfig(string $integrationKey): array
    {
        $providers = (array) config('integrations.oauth2.providers', []);
        $cfg = $providers[$integrationKey] ?? null;
        
        if (!$cfg || empty($cfg['client_id'])) {
            throw new \RuntimeException("OAuth2 Provider-Konfiguration fehlt für '{$integrationKey}'.");
        }

        // Prüfe ob authorize_url oder authorize_url_template vorhanden ist
        $hasAuthorizeUrl = !empty($cfg['authorize_url']) || !empty($cfg['authorize_url_template']);
        $hasTokenUrl = !empty($cfg['token_url']) || !empty($cfg['token_url_template']);
        
        if (!$hasAuthorizeUrl || !$hasTokenUrl) {
            throw new \RuntimeException("OAuth2 Provider-Konfiguration unvollständig für '{$integrationKey}'. authorize_url/token_url oder Templates fehlen.");
        }

        return $cfg;
    }
}
