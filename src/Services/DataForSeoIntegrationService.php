<?php

namespace Platform\Integrations\Services;

use Platform\Core\Models\User;
use Platform\Integrations\Models\Integration;
use Platform\Integrations\Models\IntegrationConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Helper-Service für DataForSEO-Integrationen
 *
 * DataForSEO verwendet Basic Auth (Login + Password).
 * Die Credentials werden in credentials.login und credentials.password gespeichert.
 */
class DataForSeoIntegrationService
{
    protected IntegrationConnectionResolver $resolver;

    public function __construct(IntegrationConnectionResolver $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * Ruft die DataForSEO-IntegrationConnection für einen User ab
     */
    public function getConnectionForUser(User $user): ?IntegrationConnection
    {
        return $this->resolver->resolveForUser('dataforseo', $user);
    }

    /**
     * Ruft die Credentials (Login + Password) für einen User ab
     *
     * @return array{login: string, password: string}|null
     */
    public function getCredentialsForUser(User $user): ?array
    {
        $connection = $this->getConnectionForUser($user);
        if ($connection) {
            return $this->getCredentials($connection);
        }
        return null;
    }

    /**
     * Ruft die Credentials aus einer DataForSEO-IntegrationConnection ab
     *
     * @return array{login: string, password: string}|null
     */
    public function getCredentials(IntegrationConnection $connection): ?array
    {
        $credentials = $connection->credentials ?? [];
        $login = $credentials['login'] ?? null;
        $password = $credentials['password'] ?? null;

        if ($login && $password) {
            return ['login' => $login, 'password' => $password];
        }

        return null;
    }

    /**
     * Prüft, ob die Connection gültige Credentials hat
     */
    public function hasValidCredentials(IntegrationConnection $connection): bool
    {
        $credentials = $this->getCredentials($connection);
        return $credentials !== null;
    }

    /**
     * Aktualisiert die Credentials einer DataForSEO-IntegrationConnection
     */
    public function updateCredentials(IntegrationConnection $connection, string $login, string $password): void
    {
        $credentials = $connection->credentials ?? [];
        $credentials['login'] = $login;
        $credentials['password'] = $password;

        $connection->credentials = $credentials;
        $connection->auth_scheme = 'basic';
        $connection->status = 'active';
        $connection->last_error = null;
        $connection->save();
    }

    /**
     * Erstellt oder aktualisiert eine DataForSEO-IntegrationConnection für einen User
     */
    public function createOrUpdateConnectionForUser(User $user, string $login, string $password): IntegrationConnection
    {
        $integration = Integration::where('key', 'dataforseo')->firstOrFail();

        $connection = IntegrationConnection::withTrashed()
            ->where('integration_id', $integration->id)
            ->where('owner_user_id', $user->id)
            ->first();

        if ($connection && $connection->trashed()) {
            $connection->restore();
            $connection->auth_scheme = 'basic';
            $connection->status = 'active';
        } elseif (!$connection) {
            $connection = new IntegrationConnection([
                'integration_id' => $integration->id,
                'owner_user_id' => $user->id,
                'auth_scheme' => 'basic',
                'status' => 'active',
            ]);
        }

        $this->updateCredentials($connection, $login, $password);

        Log::info('DataForSEO connection created/updated', [
            'connection_id' => $connection->id,
            'user_id' => $user->id,
        ]);

        return $connection;
    }

    /**
     * Testet die DataForSEO API-Verbindung
     *
     * Verwendet einen leichtgewichtigen API-Aufruf ohne Budget-Verbrauch.
     *
     * @return array{success: bool, message: string, data?: array}
     */
    public function testConnection(IntegrationConnection $connection): array
    {
        $credentials = $this->getCredentials($connection);

        if (!$credentials) {
            return [
                'success' => false,
                'message' => 'Keine DataForSEO Credentials (Login/Password) vorhanden.',
            ];
        }

        try {
            // DataForSEO bietet keinen dedizierten "ping" Endpoint.
            // Wir nutzen einen leeren POST an search_volume/live mit leerer Payload,
            // um die Auth zu testen. DataForSEO gibt bei gültigen Credentials einen
            // Fehler zurück (weil keine Keywords), aber keinen 401.
            $response = Http::withBasicAuth($credentials['login'], $credentials['password'])
                ->timeout(15)
                ->connectTimeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post(
                    config('integrations.dataforseo.api_base_url', 'https://api.dataforseo.com') . '/v3/keywords_data/google_ads/search_volume/live',
                    [] // Leere Payload – testet nur Auth
                );

            if ($response->status() === 401) {
                $connection->status = 'error';
                $connection->last_error = 'Ungültige Credentials';
                $connection->last_tested_at = now();
                $connection->save();

                return [
                    'success' => false,
                    'message' => 'Ungültige DataForSEO Credentials (Login oder Password falsch).',
                ];
            }

            // Status 200 mit error in body (z.B. "no data") ist ok – Auth funktioniert
            if ($response->successful()) {
                $data = $response->json() ?? [];

                $connection->status = 'active';
                $connection->last_error = null;
                $connection->last_tested_at = now();
                $connection->save();

                return [
                    'success' => true,
                    'message' => 'DataForSEO-Verbindung erfolgreich.',
                    'data' => [
                        'status_code' => $data['status_code'] ?? null,
                        'status_message' => $data['status_message'] ?? null,
                    ],
                ];
            }

            $error = $response->json()['status_message'] ?? 'HTTP ' . $response->status();

            $connection->status = 'error';
            $connection->last_error = $error;
            $connection->last_tested_at = now();
            $connection->save();

            return [
                'success' => false,
                'message' => 'API-Fehler: ' . $error,
            ];
        } catch (\Exception $e) {
            Log::error('DataForSEO connection test failed', [
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
     * Löscht die DataForSEO-IntegrationConnection für einen User
     */
    public function deleteConnectionForUser(User $user): bool
    {
        $connection = $this->getConnectionForUser($user);

        if ($connection) {
            $connection->delete();
            Log::info('DataForSEO connection deleted', [
                'user_id' => $user->id,
            ]);
            return true;
        }

        return false;
    }
}
