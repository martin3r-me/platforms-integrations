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

        $params = [
            'response_type' => 'code',
            'client_id' => $cfg['client_id'],
            'redirect_uri' => $this->redirectUri($integrationKey),
            'scope' => is_array($scopes) ? implode(' ', $scopes) : (string) $scopes,
            'state' => $state,
        ];

        return rtrim($cfg['authorize_url'], '?') . '?' . http_build_query($params);
    }

    public function redirectUri(string $integrationKey): string
    {
        return route('integrations.oauth2.callback', ['integrationKey' => $integrationKey]);
    }

    /**
     * Exchange authorization code for tokens and persist in Connection.
     *
     * Expected session keys (set by Controller):
     * - integrations.oauth2.state
     * - integrations.oauth2.owner_type (team|user)
     * - integrations.oauth2.owner_id
     */
    public function handleCallback(Request $request, string $integrationKey): IntegrationConnection
    {
        $cfg = $this->getProviderConfig($integrationKey);

        $state = (string) $request->query('state', '');
        $expectedState = (string) $request->session()->pull('integrations.oauth2.state');
        if (!$state || !$expectedState || !hash_equals($expectedState, $state)) {
            throw new \RuntimeException('Ungültiger OAuth state.');
        }

        $code = (string) $request->query('code', '');
        if (!$code) {
            $err = (string) $request->query('error', 'OAuth error');
            throw new \RuntimeException('OAuth Callback ohne code: ' . $err);
        }

        $ownerType = (string) $request->session()->pull('integrations.oauth2.owner_type');
        $ownerId = (int) $request->session()->pull('integrations.oauth2.owner_id');
        if (!in_array($ownerType, ['team', 'user'], true) || $ownerId <= 0) {
            throw new \RuntimeException('Owner-Kontext fehlt (team/user).');
        }

        $integration = Integration::query()->where('key', $integrationKey)->firstOrFail();

        $resp = Http::asForm()->post($cfg['token_url'], [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri($integrationKey),
            'client_id' => $cfg['client_id'],
            'client_secret' => $cfg['client_secret'] ?? null,
        ]);

        if (!$resp->successful()) {
            throw new \RuntimeException('Token Exchange fehlgeschlagen: ' . $resp->body());
        }

        $payload = $resp->json();
        $expiresIn = isset($payload['expires_in']) ? (int) $payload['expires_in'] : null;
        $expiresAt = $expiresIn ? now()->addSeconds($expiresIn)->timestamp : null;

        $connQuery = IntegrationConnection::query()->where('integration_id', $integration->id);
        if ($ownerType === 'team') {
            $connQuery->where('owner_team_id', $ownerId);
        } else {
            $connQuery->where('owner_user_id', $ownerId);
        }

        $connection = $connQuery->first() ?? new IntegrationConnection([
            'integration_id' => $integration->id,
            'owner_team_id' => $ownerType === 'team' ? $ownerId : null,
            'owner_user_id' => $ownerType === 'user' ? $ownerId : null,
        ]);

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
        $connection->save();

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

        $resp = Http::asForm()->post($cfg['token_url'], [
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
        if (!$cfg || empty($cfg['authorize_url']) || empty($cfg['token_url']) || empty($cfg['client_id'])) {
            throw new \RuntimeException("OAuth2 Provider-Konfiguration fehlt für '{$integrationKey}'.");
        }

        return $cfg;
    }
}

