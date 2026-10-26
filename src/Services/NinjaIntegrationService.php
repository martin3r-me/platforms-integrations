<?php

namespace Platform\Integrations\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Core\Models\User;
use Platform\Integrations\Exceptions\NinjaApiException;
use Platform\Integrations\Models\Integration;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Support\HttpTransientFailure;

/**
 * Helper-Service für NinjaOne-Integrationen (Public API 2.0).
 *
 * REGION
 * NinjaOne betreibt getrennte Instanzen; die Region gehört zur Verbindung und
 * bestimmt sowohl die API- als auch die OAuth-Endpunkte:
 *   us  → https://app.ninjarmm.com     eu → https://eu.ninjarmm.com
 *   oc  → https://oc.ninjarmm.com      ca → https://ca.ninjarmm.com
 *
 * AUTHENTIFIZIERUNG
 * Beide OAuth2-Wege werden unterstützt, erkennbar an auth_scheme:
 *
 *   client_credentials — App-Typ "API Services (machine-to-machine)". Client-ID
 *     und Secret liegen in der Verbindung, der Token wird bei Bedarf selbst
 *     geholt und zwischengespeichert. Kein Browser, keine Redirect-URI.
 *
 *   oauth2 — App-Typ "Web"/"Single Page". Der Nutzer autorisiert im Browser
 *     über {@see OAuth2Service}; Access- und Refresh-Token landen in
 *     credentials.oauth. Erfordert eine registrierte Redirect-URI.
 *
 * In beiden Fällen liefert {@see getAccessToken()} ein gültiges Bearer-Token
 * und erneuert es selbstständig, bevor es abläuft.
 *
 * SCOPES
 *   monitoring  lesend        management  verändernd        control  Fernsteuerung
 *
 * @see https://app.ninjarmm.com/apidocs/
 */
class NinjaIntegrationService
{
    public const INTEGRATION_KEY = 'ninja';

    /** Verfügbare Regionen mit ihrer Host-Basis. */
    public const REGIONS = [
        'us' => 'https://app.ninjarmm.com',
        'eu' => 'https://eu.ninjarmm.com',
        'oc' => 'https://oc.ninjarmm.com',
        'ca' => 'https://ca.ninjarmm.com',
    ];

    public const DEFAULT_REGION = 'us';

    /** Alle von der API angebotenen Scopes. */
    public const SCOPES = ['monitoring', 'management', 'control'];

    /**
     * Sicherheitsabstand, bevor ein Token als abgelaufen gilt. Verhindert, dass
     * ein Request mit einem Token startet, der während der Laufzeit verfällt.
     */
    protected const EXPIRY_BUFFER_SECONDS = 60;

    protected IntegrationConnectionResolver $resolver;

    public function __construct(IntegrationConnectionResolver $resolver)
    {
        $this->resolver = $resolver;
    }

    // =========================================================================
    // CONNECTION
    // =========================================================================

    public function getConnectionForUser(User $user): ?IntegrationConnection
    {
        return $this->resolver->resolveForUser(self::INTEGRATION_KEY, $user);
    }

    // =========================================================================
    // REGION / URLS
    // =========================================================================

    /** Region-Kürzel der Verbindung (us/eu/oc/ca), mit Fallback auf die Konfiguration. */
    public function getRegion(IntegrationConnection $connection): string
    {
        $region = ($connection->credentials ?? [])['region'] ?? null;
        $region = mb_strtolower(trim((string) $region));

        if ($region === '' || !isset(self::REGIONS[$region])) {
            $region = (string) config('integrations.ninja.region', self::DEFAULT_REGION);
            $region = isset(self::REGIONS[$region]) ? $region : self::DEFAULT_REGION;
        }

        return $region;
    }

    /**
     * Host-Basis der Verbindung, ohne abschließenden Slash.
     *
     * Eine explizit hinterlegte base_url gewinnt — für Sonderfälle wie eine
     * neue Region, die hier noch nicht eingetragen ist.
     */
    public function getBaseUrl(IntegrationConnection $connection): string
    {
        $explicit = ($connection->credentials ?? [])['base_url'] ?? null;
        if (is_string($explicit) && trim($explicit) !== '') {
            return rtrim(trim($explicit), '/');
        }

        return self::REGIONS[$this->getRegion($connection)];
    }

    public function getAuthorizeUrl(IntegrationConnection $connection): string
    {
        return $this->getBaseUrl($connection) . '/oauth/authorize';
    }

    public function getTokenUrl(IntegrationConnection $connection): string
    {
        return $this->getBaseUrl($connection) . '/oauth/token';
    }

    // =========================================================================
    // CREDENTIALS
    // =========================================================================

    public function getClientId(IntegrationConnection $connection): ?string
    {
        $value = ($connection->credentials ?? [])['client_id'] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    public function getClientSecret(IntegrationConnection $connection): ?string
    {
        $value = ($connection->credentials ?? [])['client_secret'] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /** @return array<int, string> Die für diese Verbindung angeforderten Scopes. */
    public function getScopes(IntegrationConnection $connection): array
    {
        $scopes = ($connection->credentials ?? [])['scopes'] ?? null;

        if (is_string($scopes)) {
            $scopes = preg_split('/[\s,]+/', trim($scopes)) ?: [];
        }

        if (!is_array($scopes) || $scopes === []) {
            $scopes = (array) config('integrations.ninja.scopes', ['monitoring']);
        }

        $scopes = array_values(array_intersect(self::SCOPES, array_map('strval', $scopes)));

        return $scopes !== [] ? $scopes : ['monitoring'];
    }

    /** 'client_credentials' (Maschine-zu-Maschine) oder 'oauth2' (Browser-Flow). */
    public function getAuthScheme(IntegrationConnection $connection): string
    {
        $scheme = $connection->auth_scheme ?: (($connection->credentials ?? [])['auth_scheme'] ?? null);

        return $scheme === 'oauth2' ? 'oauth2' : 'client_credentials';
    }

    // =========================================================================
    // TOKEN
    // =========================================================================

    /**
     * Liefert ein gültiges Bearer-Token und besorgt bzw. erneuert es bei Bedarf.
     *
     * @throws NinjaApiException
     */
    public function getAccessToken(IntegrationConnection $connection): string
    {
        $oauth = ($connection->credentials ?? [])['oauth'] ?? [];
        $token = $oauth['access_token'] ?? null;

        if (is_string($token) && $token !== '' && !$this->isExpired($oauth)) {
            return $token;
        }

        return $this->getAuthScheme($connection) === 'oauth2'
            ? $this->refreshAuthorizationCodeToken($connection)
            : $this->fetchClientCredentialsToken($connection);
    }

    /** @param array<string, mixed> $oauth */
    protected function isExpired(array $oauth): bool
    {
        $expiresAt = $oauth['expires_at'] ?? null;

        // Ohne Ablaufzeitpunkt gilt das Token als gültig — NinjaOne liefert
        // expires_in zwar immer mit, aber ein fehlender Wert darf nicht dazu
        // führen, dass bei jedem Aufruf ein neues Token geholt wird.
        if (!is_numeric($expiresAt)) {
            return false;
        }

        return (int) $expiresAt - self::EXPIRY_BUFFER_SECONDS <= time();
    }

    /**
     * Holt ein Token über grant_type=client_credentials.
     *
     * @throws NinjaApiException
     */
    protected function fetchClientCredentialsToken(IntegrationConnection $connection): string
    {
        $clientId = $this->getClientId($connection);
        $clientSecret = $this->getClientSecret($connection);

        if (!$clientId || !$clientSecret) {
            throw NinjaApiException::missingCredentials();
        }

        $payload = $this->requestToken($connection, [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'scope' => implode(' ', $this->getScopes($connection)),
        ]);

        return $this->storeToken($connection, $payload);
    }

    /**
     * Erneuert ein Token des Browser-Flows über seinen refresh_token.
     *
     * @throws NinjaApiException
     */
    protected function refreshAuthorizationCodeToken(IntegrationConnection $connection): string
    {
        $oauth = ($connection->credentials ?? [])['oauth'] ?? [];
        $refreshToken = $oauth['refresh_token'] ?? null;

        if (!is_string($refreshToken) || $refreshToken === '') {
            // Ohne Refresh-Token hilft nur eine neue Autorisierung im Browser.
            throw new NinjaApiException(
                'Das NinjaOne-Token ist abgelaufen und es liegt kein Refresh-Token vor. '
                . 'Bitte die Verbindung unter /integrations neu autorisieren.',
                401,
                'REFRESH_TOKEN_MISSING'
            );
        }

        $clientId = $this->getClientId($connection);
        $clientSecret = $this->getClientSecret($connection);

        $payload = $this->requestToken($connection, array_filter([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ], static fn ($v) => $v !== null && $v !== ''));

        return $this->storeToken($connection, $payload);
    }

    /**
     * Ruft den Token-Endpunkt auf und gibt die dekodierte Antwort zurück.
     *
     * @param  array<string, string> $form
     * @return array<string, mixed>
     *
     * @throws NinjaApiException
     */
    protected function requestToken(IntegrationConnection $connection, array $form): array
    {
        $url = $this->getTokenUrl($connection);

        try {
            $response = Http::asForm()
                ->withHeaders(['Accept' => 'application/json'])
                ->connectTimeout((int) config('integrations.ninja.timeout.connect', 10))
                ->timeout((int) config('integrations.ninja.timeout.default', 30))
                ->post($url, $form);
        } catch (\Exception $e) {
            Log::error('NinjaOne: Token-Endpunkt nicht erreichbar', [
                'connection_id' => $connection->id,
                'grant_type' => $form['grant_type'] ?? null,
                'error' => $e->getMessage(),
                'transient' => HttpTransientFailure::isTransient($e),
            ]);

            throw NinjaApiException::connectionError(HttpTransientFailure::describe(
                $e,
                'NinjaOne',
                (int) config('integrations.ninja.timeout.default', 30)
            ));
        }

        $data = $response->json();
        $data = is_array($data) ? $data : [];

        if (!$response->successful()) {
            // 401/403 heißt hier: Client-ID, Secret oder Scope stimmen nicht —
            // also ein echtes Zugangsproblem, das die Verbindung unbrauchbar macht.
            if (in_array($response->status(), [400, 401, 403], true)) {
                $this->markBroken($connection, NinjaApiException::fromResponse(
                    $response->status(),
                    $data,
                    $response->body()
                )->getMessage());
            }

            throw NinjaApiException::fromResponse($response->status(), $data, $response->body());
        }

        if (empty($data['access_token'])) {
            throw NinjaApiException::tokenRequestFailed('Antwort enthielt kein access_token.');
        }

        return $data;
    }

    /**
     * Legt das Token in den Credentials ab und gibt es zurück.
     *
     * @param array<string, mixed> $payload
     */
    protected function storeToken(IntegrationConnection $connection, array $payload): string
    {
        $credentials = $connection->credentials ?? [];
        $oauth = $credentials['oauth'] ?? [];

        $oauth['access_token'] = (string) $payload['access_token'];
        $oauth['token_type'] = $payload['token_type'] ?? 'Bearer';

        if (!empty($payload['refresh_token'])) {
            $oauth['refresh_token'] = (string) $payload['refresh_token'];
        }

        if (!empty($payload['scope'])) {
            $oauth['scope'] = (string) $payload['scope'];
        }

        if (!empty($payload['expires_in']) && is_numeric($payload['expires_in'])) {
            $oauth['expires_at'] = time() + (int) $payload['expires_in'];
        }

        $credentials['oauth'] = $oauth;

        $connection->credentials = $credentials;
        $connection->status = 'active';
        $connection->last_error = null;
        $connection->save();

        Log::info('NinjaOne: Token erneuert', [
            'connection_id' => $connection->id,
            'expires_in' => $payload['expires_in'] ?? null,
        ]);

        return $oauth['access_token'];
    }

    /** Markiert die Verbindung als defekt — nur bei echten Zugangsfehlern. */
    protected function markBroken(IntegrationConnection $connection, string $error): void
    {
        $connection->status = 'error';
        $connection->last_error = $error;
        $connection->last_tested_at = now();
        $connection->save();
    }

    // =========================================================================
    // CONNECTION ANLEGEN / AKTUALISIEREN
    // =========================================================================

    /**
     * Erstellt oder aktualisiert eine NinjaOne-Verbindung.
     *
     * @param array<int, string> $scopes
     */
    public function createOrUpdateConnectionForUser(
        User $user,
        string $clientId,
        ?string $clientSecret,
        string $region = self::DEFAULT_REGION,
        array $scopes = ['monitoring'],
        string $authScheme = 'client_credentials',
        ?int $connectionId = null
    ): IntegrationConnection {
        $integration = Integration::firstOrCreate(
            ['key' => self::INTEGRATION_KEY],
            [
                'name' => 'NinjaOne',
                'is_enabled' => true,
                'supported_auth_schemes' => ['client_credentials', 'oauth2'],
                'meta' => [
                    'description' => 'NinjaOne RMM (Public API 2.0) für Geräte, Organisationen, Alarme und Automatisierung.',
                    'icon' => 'heroicon-o-computer-desktop',
                ],
            ]
        );

        $region = isset(self::REGIONS[mb_strtolower($region)]) ? mb_strtolower($region) : self::DEFAULT_REGION;
        $scopes = array_values(array_intersect(self::SCOPES, $scopes)) ?: ['monitoring'];
        $authScheme = $authScheme === 'oauth2' ? 'oauth2' : 'client_credentials';

        $connection = $connectionId
            ? IntegrationConnection::query()
                ->where('id', $connectionId)
                ->where('owner_user_id', $user->id)
                ->first()
            : null;

        if (!$connection) {
            $connection = new IntegrationConnection();
            $connection->integration_id = $integration->id;
            $connection->owner_user_id = $user->id;
            $connection->is_default = !IntegrationConnection::query()
                ->where('integration_id', $integration->id)
                ->where('owner_user_id', $user->id)
                ->exists();
        }

        $credentials = $connection->credentials ?? [];
        $credentials['client_id'] = trim($clientId);
        $credentials['region'] = $region;
        $credentials['scopes'] = $scopes;
        $credentials['auth_scheme'] = $authScheme;

        // Ein leer gelassenes Secret bedeutet "unverändert lassen" — das Feld
        // wird beim Bearbeiten nie vorbefüllt, damit es nicht im DOM steht.
        if ($clientSecret !== null && trim($clientSecret) !== '') {
            $credentials['client_secret'] = trim($clientSecret);
        }

        // Zugangsdaten geändert → bisheriges Token verwerfen, sonst liefe der
        // nächste Aufruf noch mit dem Token der alten App.
        unset($credentials['oauth']);

        $connection->credentials = $credentials;
        $connection->auth_scheme = $authScheme;
        $connection->name = $connection->name ?: ('NinjaOne (' . mb_strtoupper($region) . ')');
        $connection->status = $authScheme === 'oauth2' ? 'draft' : 'active';
        $connection->last_error = null;
        $connection->save();

        Log::info('NinjaOne: Verbindung gespeichert', [
            'connection_id' => $connection->id,
            'region' => $region,
            'auth_scheme' => $authScheme,
            'scopes' => $scopes,
        ]);

        return $connection;
    }

    // =========================================================================
    // VERBINDUNGSTEST
    // =========================================================================

    /**
     * Testet die Verbindung: Token besorgen und einen günstigen, lesenden
     * Endpunkt abrufen (GET /v2/organizations mit pageSize=1).
     *
     * Netzwerkfehler lassen den Status unangetastet — nur echte Zugangsfehler
     * markieren die Verbindung als defekt, damit ein kurzer Ausfall nicht
     * geteilte Verbindungen für alle Kollegen sperrt.
     *
     * @return array{success: bool, message: string, data?: array}
     */
    public function testConnection(IntegrationConnection $connection): array
    {
        $timeout = (int) config('integrations.ninja.timeout.default', 30);

        try {
            $token = $this->getAccessToken($connection);
        } catch (NinjaApiException $e) {
            $connection->last_error = $e->getMessage();
            $connection->last_tested_at = now();
            $connection->save();

            return ['success' => false, 'message' => 'Token konnte nicht bezogen werden: ' . $e->getMessage()];
        }

        try {
            $response = Http::withToken($token)
                ->withHeaders(['Accept' => 'application/json'])
                ->connectTimeout((int) config('integrations.ninja.timeout.connect', 10))
                ->timeout($timeout)
                ->get($this->getBaseUrl($connection) . '/v2/organizations', ['pageSize' => 1]);
        } catch (\Exception $e) {
            $message = HttpTransientFailure::describe($e, 'NinjaOne', $timeout);

            if (!HttpTransientFailure::isTransient($e)) {
                $connection->status = 'error';
            }
            $connection->last_error = $message;
            $connection->last_tested_at = now();
            $connection->save();

            return ['success' => false, 'message' => 'Verbindungsfehler: ' . $message];
        }

        $scopes = implode(', ', $this->getScopes($connection));
        $region = mb_strtoupper($this->getRegion($connection));

        if ($response->successful()) {
            $connection->status = 'active';
            $connection->last_error = null;
            $connection->last_tested_at = now();
            $connection->save();

            $organizations = $response->json();
            $count = is_array($organizations) ? count($organizations) : 0;

            return [
                'success' => true,
                'message' => "Verbindung erfolgreich. Region {$region} · Scopes: {$scopes}"
                    . ($count > 0 ? ' · Organisationen erreichbar' : ' · keine Organisationen sichtbar'),
                'data' => ['region' => $region, 'scopes' => $this->getScopes($connection)],
            ];
        }

        $error = NinjaApiException::fromResponse(
            $response->status(),
            is_array($response->json()) ? $response->json() : [],
            $response->body()
        )->getMessage();

        // 403 heißt hier typischerweise: Token gültig, aber Scope fehlt.
        if (in_array($response->status(), [401, 403], true)) {
            $connection->status = 'error';
        }
        $connection->last_error = $error;
        $connection->last_tested_at = now();
        $connection->save();

        Log::warning('NinjaOne testConnection failed', [
            'connection_id' => $connection->id,
            'http_status' => $response->status(),
            'marked_broken' => in_array($response->status(), [401, 403], true),
        ]);

        return ['success' => false, 'message' => $error];
    }

    public function deleteConnectionForUser(User $user): bool
    {
        $connection = $this->getConnectionForUser($user);

        if ($connection) {
            $connection->delete();
            Log::info('NinjaOne: Verbindung gelöscht', ['user_id' => $user->id]);

            return true;
        }

        return false;
    }
}
