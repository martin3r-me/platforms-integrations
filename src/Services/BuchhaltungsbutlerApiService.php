<?php

namespace Platform\Integrations\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Core\Models\User;
use Platform\Integrations\Exceptions\BuchhaltungsbutlerApiException;
use Platform\Integrations\Models\IntegrationConnection;

/**
 * Service für die Kommunikation mit der BuchhaltungsButler API v1.
 *
 * Eigenheiten der API:
 * - Alle Endpoints sind POST mit JSON-Body — auch reine Read-Operationen
 * - HTTP Basic Auth: api_client (User) + api_secret (Password)
 * - api_key als Pflichtfeld im JSON-Body (kundenspezifisch)
 * - Rate-Limit: 100 Requests pro Kunde pro Minute
 *
 * @see https://app.buchhaltungsbutler.de/docs/api/v1/
 */
class BuchhaltungsbutlerApiService
{
    protected const BASE_URL = 'https://webapp.buchhaltungsbutler.de/api/v1';

    protected BuchhaltungsbutlerIntegrationService $integrationService;
    protected ?int $connectionIdOverride = null;

    public function __construct(BuchhaltungsbutlerIntegrationService $integrationService)
    {
        $this->integrationService = $integrationService;
    }

    public function forConnection(?int $connectionId): static
    {
        if ($connectionId === null) {
            return $this;
        }

        $clone = clone $this;
        $clone->connectionIdOverride = $connectionId;

        return $clone;
    }

    protected function resolveConnection(User $user): IntegrationConnection
    {
        if ($this->connectionIdOverride) {
            $resolver   = app(IntegrationConnectionResolver::class);
            $connection = $resolver->resolveById($this->connectionIdOverride, $user);
        } else {
            $connection = $this->integrationService->getConnectionForUser($user);
        }

        if (!$connection) {
            Log::warning('Buchhaltungsbutler API: Keine Connection für User', ['user_id' => $user->id]);
            throw BuchhaltungsbutlerApiException::noConnection();
        }

        return $connection;
    }

    /**
     * @return array{client: string, secret: string, key: string}
     */
    protected function credentialsFor(IntegrationConnection $connection): array
    {
        $creds  = $connection->credentials ?? [];
        $client = $creds['api_client'] ?? null;
        $secret = $creds['api_secret'] ?? null;
        $key    = $creds['api_key'] ?? null;

        if (!$client || !$secret || !$key) {
            throw BuchhaltungsbutlerApiException::missingCredentials();
        }

        return ['client' => $client, 'secret' => $secret, 'key' => $key];
    }

    /**
     * Führt einen POST-Request gegen die BuchhaltungsButler API aus.
     * api_key wird automatisch in den Body gemerged.
     */
    public function post(User $user, string $endpoint, array $data = []): array
    {
        $connection = $this->resolveConnection($user);
        $creds      = $this->credentialsFor($connection);

        $body = array_merge($data, ['api_key' => $creds['key']]);
        $url  = self::BASE_URL . '/' . ltrim($endpoint, '/');

        try {
            $response = Http::withBasicAuth($creds['client'], $creds['secret'])
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ])
                ->post($url, $body);
        } catch (\Throwable $e) {
            Log::error('BuchhaltungsButler request failed', [
                'endpoint' => $endpoint,
                'error'    => $e->getMessage(),
            ]);
            throw BuchhaltungsbutlerApiException::connectionError($e->getMessage());
        }

        return $this->handleResponse($response, $connection);
    }

    /**
     * Verarbeitet HTTP-Response.
     * - Erfolg: markiert Connection als active, gibt JSON-Body zurück
     * - 401/403: markiert Connection als error
     * - sonst: wirft Exception ohne Status-Update (transienter Fehler)
     */
    protected function handleResponse(Response $response, IntegrationConnection $connection): array
    {
        if ($response->successful()) {
            $connection->status         = 'active';
            $connection->last_error     = null;
            $connection->last_tested_at = now();
            $connection->saveQuietly();

            return $response->json() ?? [];
        }

        $data      = $response->json();
        $exception = BuchhaltungsbutlerApiException::fromResponse(
            $response->status(),
            is_array($data) ? $data : null
        );

        if (in_array($response->status(), [401, 403], true)) {
            $connection->status         = 'error';
            $connection->last_error     = $exception->getMessage();
            $connection->last_tested_at = now();
            $connection->saveQuietly();
        }

        throw $exception;
    }

    // =========================================================================
    // INVOICES (Rechnungen / Angebote / Gutschriften — alle als Entwurf)
    // =========================================================================

    /**
     * Erstellt einen Beleg-Entwurf.
     *
     * Hinweis: BuchhaltungsButler unterscheidet Rechnung/Angebot/Gutschrift
     * über das Feld 'type' im selben Endpoint /invoices/create/draft.
     *
     * @param string $type 'invoice' (Rechnung) | 'offer' (Angebot) | 'credit' (Gutschrift)
     * @param array  $data weitere Felder gemäß Buchhaltungsbutler Doku
     */
    public function createInvoiceDraft(User $user, string $type, array $data): array
    {
        $allowed = ['invoice', 'offer', 'credit'];
        if (!in_array($type, $allowed, true)) {
            throw new BuchhaltungsbutlerApiException(
                "Ungültiger type '{$type}'. Erlaubt: " . implode(', ', $allowed),
                422,
                'INVALID_TYPE'
            );
        }

        $data['type'] = $type;

        return $this->post($user, '/invoices/create/draft', $data);
    }

    // =========================================================================
    // SETTINGS — DEBTORS (Kunden / Debitorkonten)
    // =========================================================================

    public function getDebtors(User $user, int $limit = 25, int $offset = 0): array
    {
        return $this->post($user, '/settings/get/debtors', [
            'limit'  => $limit,
            'offset' => $offset,
        ]);
    }

    public function addDebtor(User $user, array $data): array
    {
        return $this->post($user, '/settings/add/debtor', $data);
    }

    public function updateDebtor(User $user, array $data): array
    {
        return $this->post($user, '/settings/update/debtor', $data);
    }

    /**
     * Verbindungstest: ruft /settings/get/debtors mit limit=1 auf.
     */
    public function testConnection(User $user): array
    {
        return $this->getDebtors($user, 1, 0);
    }
}
