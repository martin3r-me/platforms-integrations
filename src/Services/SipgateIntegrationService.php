<?php

namespace Platform\Integrations\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Core\Models\User;
use Platform\Integrations\Exceptions\SipgateApiException;
use Platform\Integrations\Models\Integration;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Models\IntegrationsSipgateToken;

/**
 * Service für Sipgate Integration Management
 *
 * Dieser Service verwaltet:
 * - OAuth-Token-Verwaltung (Access Token, Refresh Token)
 * - Token-Rotation und automatische Erneuerung
 * - Token-Revoke
 * - Connection-Verwaltung
 * - Token-Audit-Logging
 *
 * OAuth-Flow gemäß Sipgate API Dokumentation:
 * @see https://developer.sipgate.io/authentication/oauth2
 */
class SipgateIntegrationService
{
    protected IntegrationConnectionResolver $resolver;

    public function __construct(IntegrationConnectionResolver $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * Ruft die Sipgate-IntegrationConnection für einen User ab
     */
    public function getConnectionForUser(User $user): ?IntegrationConnection
    {
        return $this->resolver->resolveForUser('sipgate', $user);
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

        // Prüfe ob Token existiert
        $accessToken = $this->getAccessToken($connection);
        if (!$accessToken) {
            return null;
        }

        // Prüfe ob Token abgelaufen ist
        if ($autoRefresh && $this->isTokenExpired($connection)) {
            try {
                $connection = $this->refreshToken($connection);
                return $this->getAccessToken($connection);
            } catch (SipgateApiException $e) {
                Log::error('Sipgate token refresh failed', [
                    'user_id' => $user->id,
                    'connection_id' => $connection->id,
                    'error' => $e->getMessage(),
                ]);

                // Bei Refresh-Fehler Token als ungültig markieren
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
            // Wenn kein Ablaufdatum bekannt ist, als nicht abgelaufen behandeln
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
     * @throws SipgateApiException
     */
    public function refreshToken(IntegrationConnection $connection): IntegrationConnection
    {
        $refreshToken = $this->getRefreshToken($connection);

        if (!$refreshToken) {
            throw SipgateApiException::unauthorized('Kein Refresh-Token vorhanden. Bitte erneut verbinden.');
        }

        $config = $this->getOAuthConfig();
        $requestId = $this->generateRequestId();

        Log::info('Sipgate token refresh started', [
            'connection_id' => $connection->id,
            'request_id' => $requestId,
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
                $this->logTokenEvent($connection, 'error', [
                    'error_code' => 'REFRESH_FAILED',
                    'error_message' => $response->body(),
                    'request_id' => $requestId,
                ]);

                throw SipgateApiException::fromResponse(
                    $response->status(),
                    $response->json(),
                    $requestId
                );
            }

            $payload = $response->json();
            $connection = $this->updateConnectionTokens($connection, $payload);

            // Token-Event loggen
            $this->logTokenEvent($connection, 'refreshed', [
                'expires_in' => $payload['expires_in'] ?? null,
                'request_id' => $requestId,
            ]);

            Log::info('Sipgate token refresh successful', [
                'connection_id' => $connection->id,
                'request_id' => $requestId,
            ]);

            return $connection;
        } catch (\Exception $e) {
            if (!$e instanceof SipgateApiException) {
                throw SipgateApiException::connectionError($e->getMessage(), $requestId);
            }
            throw $e;
        }
    }

    /**
     * Widerruft den Token (Logout/Disconnect)
     *
     * @throws SipgateApiException
     */
    public function revokeToken(IntegrationConnection $connection): bool
    {
        $accessToken = $this->getAccessToken($connection);
        $refreshToken = $this->getRefreshToken($connection);
        $config = $this->getOAuthConfig();
        $requestId = $this->generateRequestId();

        Log::info('Sipgate token revoke started', [
            'connection_id' => $connection->id,
            'request_id' => $requestId,
        ]);

        $success = true;

        // Revoke Access Token
        if ($accessToken) {
            try {
                $response = Http::asForm()
                    ->timeout(30)
                    ->post($config['revoke_url'], [
                        'token' => $accessToken,
                        'token_type_hint' => 'access_token',
                        'client_id' => $config['client_id'],
                        'client_secret' => $config['client_secret'],
                    ]);

                if (!$response->successful()) {
                    Log::warning('Sipgate access token revoke failed', [
                        'connection_id' => $connection->id,
                        'status' => $response->status(),
                        'request_id' => $requestId,
                    ]);
                    $success = false;
                }
            } catch (\Exception $e) {
                Log::warning('Sipgate access token revoke error', [
                    'connection_id' => $connection->id,
                    'error' => $e->getMessage(),
                    'request_id' => $requestId,
                ]);
                $success = false;
            }
        }

        // Revoke Refresh Token
        if ($refreshToken) {
            try {
                $response = Http::asForm()
                    ->timeout(30)
                    ->post($config['revoke_url'], [
                        'token' => $refreshToken,
                        'token_type_hint' => 'refresh_token',
                        'client_id' => $config['client_id'],
                        'client_secret' => $config['client_secret'],
                    ]);

                if (!$response->successful()) {
                    Log::warning('Sipgate refresh token revoke failed', [
                        'connection_id' => $connection->id,
                        'status' => $response->status(),
                        'request_id' => $requestId,
                    ]);
                    $success = false;
                }
            } catch (\Exception $e) {
                Log::warning('Sipgate refresh token revoke error', [
                    'connection_id' => $connection->id,
                    'error' => $e->getMessage(),
                    'request_id' => $requestId,
                ]);
                $success = false;
            }
        }

        // Token-Event loggen
        $this->logTokenEvent($connection, 'revoked', [
            'success' => $success,
            'request_id' => $requestId,
        ]);

        // Credentials löschen
        $this->clearConnectionCredentials($connection);

        Log::info('Sipgate token revoke completed', [
            'connection_id' => $connection->id,
            'success' => $success,
            'request_id' => $requestId,
        ]);

        return $success;
    }

    /**
     * Aktualisiert die Token-Daten in der Connection
     */
    public function updateConnectionTokens(IntegrationConnection $connection, array $tokenPayload): IntegrationConnection
    {
        $credentials = $connection->credentials ?? [];
        $expiresIn = isset($tokenPayload['expires_in']) ? (int) $tokenPayload['expires_in'] : null;
        $expiresAt = $expiresIn ? now()->addSeconds($expiresIn)->timestamp : null;

        // Scopes verarbeiten
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

        // Sub-Account-ID falls vorhanden
        if (isset($tokenPayload['sub'])) {
            $credentials['oauth']['sipgate_sub'] = $tokenPayload['sub'];
        }

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
     * Testet die Sipgate API-Verbindung
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
            // Test mit dem /authorization/userinfo Endpoint
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Accept' => 'application/json',
            ])->timeout(30)->get('https://api.sipgate.com/v2/authorization/userinfo');

            if ($response->successful()) {
                $data = $response->json();

                // Connection als aktiv markieren
                $connection->status = 'active';
                $connection->last_error = null;
                $connection->last_tested_at = now();
                $connection->save();

                return [
                    'success' => true,
                    'message' => 'Verbindung erfolgreich.',
                    'data' => $data,
                ];
            }

            $error = $response->json()['message'] ?? 'Unbekannter Fehler';

            $connection->status = 'error';
            $connection->last_error = $error;
            $connection->last_tested_at = now();
            $connection->save();

            return [
                'success' => false,
                'message' => 'API-Fehler: ' . $error,
            ];
        } catch (\Exception $e) {
            Log::error('Sipgate connection test failed', [
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
     * Erstellt oder aktualisiert eine Sipgate-Connection für einen User
     */
    public function createOrUpdateConnectionForUser(User $user, array $tokenPayload): IntegrationConnection
    {
        $integration = Integration::where('key', 'sipgate')->firstOrFail();

        $connection = IntegrationConnection::withTrashed()
            ->where('integration_id', $integration->id)
            ->where('owner_user_id', $user->id)
            ->first();

        if ($connection && $connection->trashed()) {
            $connection->restore();
            $connection->auth_scheme = 'oauth2';
            $connection->status = 'active';
            $connection->save();
        } elseif (!$connection) {
            $connection = new IntegrationConnection([
                'integration_id' => $integration->id,
                'owner_user_id' => $user->id,
                'auth_scheme' => 'oauth2',
                'status' => 'active',
            ]);
            $connection->save();
        }

        $connection = $this->updateConnectionTokens($connection, $tokenPayload);

        // Token-Event loggen
        $this->logTokenEvent($connection, 'created', [
            'expires_in' => $tokenPayload['expires_in'] ?? null,
            'scopes' => $tokenPayload['scope'] ?? null,
        ]);

        Log::info('Sipgate connection created/updated', [
            'connection_id' => $connection->id,
            'user_id' => $user->id,
        ]);

        return $connection;
    }

    /**
     * Löscht die Sipgate-Connection für einen User (mit Token-Revoke)
     */
    public function deleteConnectionForUser(User $user): bool
    {
        $connection = $this->getConnectionForUser($user);

        if ($connection) {
            // Token widerrufen
            try {
                $this->revokeToken($connection);
            } catch (\Exception $e) {
                Log::warning('Sipgate token revoke failed during deletion', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Connection löschen (Soft Delete)
            $connection->delete();

            Log::info('Sipgate connection deleted', [
                'user_id' => $user->id,
            ]);

            return true;
        }

        return false;
    }

    /**
     * Loggt Token-Events für Audit-Zwecke
     */
    protected function logTokenEvent(IntegrationConnection $connection, string $eventType, array $meta = []): void
    {
        try {
            $credentials = $connection->credentials ?? [];
            $accessToken = $credentials['oauth']['access_token'] ?? null;
            $refreshToken = $credentials['oauth']['refresh_token'] ?? null;

            IntegrationsSipgateToken::create([
                'integration_connection_id' => $connection->id,
                'user_id' => $connection->owner_user_id,
                'event_type' => $eventType,
                'token_hash' => $accessToken ? hash('sha256', $accessToken) : null,
                'refresh_token_hash' => $refreshToken ? hash('sha256', $refreshToken) : null,
                'expires_in' => $meta['expires_in'] ?? null,
                'expires_at' => isset($credentials['oauth']['expires_at'])
                    ? \Carbon\Carbon::createFromTimestamp($credentials['oauth']['expires_at'])
                    : null,
                'issued_at' => isset($credentials['oauth']['token_issued_at'])
                    ? \Carbon\Carbon::createFromTimestamp($credentials['oauth']['token_issued_at'])
                    : null,
                'scopes' => isset($meta['scopes']) ? (is_array($meta['scopes']) ? $meta['scopes'] : explode(' ', $meta['scopes'])) : null,
                'trigger_source' => $meta['trigger_source'] ?? 'api',
                'error_message' => $meta['error_message'] ?? null,
                'error_code' => $meta['error_code'] ?? null,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'request_id' => $meta['request_id'] ?? null,
                'meta' => array_diff_key($meta, array_flip(['expires_in', 'scopes', 'trigger_source', 'error_message', 'error_code', 'request_id'])),
            ]);
        } catch (\Exception $e) {
            // Token-Audit-Logging sollte nicht den Hauptflow blockieren
            Log::error('Failed to log Sipgate token event', [
                'connection_id' => $connection->id,
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Gibt die OAuth-Konfiguration zurück
     *
     * @throws SipgateApiException
     */
    protected function getOAuthConfig(): array
    {
        $providers = config('integrations.oauth2.providers', []);
        $config = $providers['sipgate'] ?? null;

        if (!$config || empty($config['client_id'])) {
            throw SipgateApiException::noConnection();
        }

        return [
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'token_url' => $config['token_url'] ?? 'https://api.sipgate.com/login/oauth/token',
            'revoke_url' => $config['revoke_url'] ?? 'https://api.sipgate.com/login/oauth/revoke',
        ];
    }

    /**
     * Generiert eine eindeutige Request-ID für Tracing
     */
    protected function generateRequestId(): string
    {
        return 'sipgate-' . bin2hex(random_bytes(16));
    }

    /**
     * Gibt die gewährten Scopes einer Connection zurück
     */
    public function getGrantedScopes(IntegrationConnection $connection): array
    {
        $credentials = $connection->credentials ?? [];
        return $credentials['oauth']['scope_array'] ?? [];
    }

    /**
     * Prüft, ob ein bestimmter Scope gewährt wurde
     */
    public function hasScope(IntegrationConnection $connection, string $scope): bool
    {
        $grantedScopes = $this->getGrantedScopes($connection);
        return in_array($scope, $grantedScopes) || in_array('all', $grantedScopes);
    }

    /**
     * Holt die Token-History für eine Connection
     */
    public function getTokenHistory(IntegrationConnection $connection, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return IntegrationsSipgateToken::where('integration_connection_id', $connection->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Berechnet Token-Metriken für Monitoring
     */
    public function getTokenMetrics(IntegrationConnection $connection): array
    {
        $history = IntegrationsSipgateToken::where('integration_connection_id', $connection->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->get();

        return [
            'total_events' => $history->count(),
            'refresh_count' => $history->where('event_type', 'refreshed')->count(),
            'error_count' => $history->where('event_type', 'error')->count(),
            'last_refresh' => $history->where('event_type', 'refreshed')->first()?->created_at,
            'last_error' => $history->where('event_type', 'error')->first()?->created_at,
            'current_token_age_hours' => $this->getTokenAgeInHours($connection),
            'is_healthy' => $this->hasValidToken($connection),
        ];
    }

    /**
     * Berechnet das Alter des aktuellen Tokens in Stunden
     */
    protected function getTokenAgeInHours(IntegrationConnection $connection): ?float
    {
        $credentials = $connection->credentials ?? [];
        $issuedAt = $credentials['oauth']['token_issued_at'] ?? null;

        if (!$issuedAt) {
            return null;
        }

        return round((now()->timestamp - $issuedAt) / 3600, 2);
    }
}
