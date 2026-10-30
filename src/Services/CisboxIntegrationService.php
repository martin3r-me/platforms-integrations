<?php

namespace Platform\Integrations\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Core\Models\User;
use Platform\Integrations\Exceptions\CisboxApiException;
use Platform\Integrations\Models\Integration;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Support\HttpTransientFailure;

/**
 * Helper-Service für die cisbox Invoice API v1.
 *
 * cisbox authentifiziert mit einem technischen API-Key im Header:
 *   X-API-Key: <api_key>
 *
 * Der Key hängt NICHT an einem Benutzerkonto und läuft nicht durch die
 * Rechteverwaltung von cisbox: Er sieht alle Daten seiner Installation. Er ist
 * damit ein privilegiertes Geheimnis — und der Grund, weshalb hier je
 * Installation eine eigene Verbindung angelegt wird statt einer je Person.
 *
 * Base-URL ist in der Cloud fix https://api.cisbox.com/invoice/v1. Für
 * abweichende Installationen lässt sie sich je Verbindung in
 * credentials.base_url hinterlegen; ein optionales Label liegt in
 * credentials.instance.
 *
 * @see https://api.cisbox.com/docs/ui/invoice-api-v1
 */
class CisboxIntegrationService
{
    public const INTEGRATION_KEY = 'cisbox';

    /** Leichtgewichtiger Endpunkt für den Verbindungstest. */
    public const PROBE_PATH = '/invoices';

    protected IntegrationConnectionResolver $resolver;

    public function __construct(IntegrationConnectionResolver $resolver)
    {
        $this->resolver = $resolver;
    }

    public function getConnectionForUser(User $user): ?IntegrationConnection
    {
        return $this->resolver->resolveForUser(self::INTEGRATION_KEY, $user);
    }

    public function getApiKeyForUser(User $user): ?string
    {
        $connection = $this->getConnectionForUser($user);

        return $connection ? $this->getApiKey($connection) : null;
    }

    public function getApiKey(IntegrationConnection $connection): ?string
    {
        $key = ($connection->credentials ?? [])['api_key'] ?? null;
        $key = $key !== null ? trim((string) $key) : '';

        return $key !== '' ? $key : null;
    }

    public function hasValidApiKey(IntegrationConnection $connection): bool
    {
        return $this->getApiKey($connection) !== null;
    }

    /**
     * Frei wählbares Label der Installation — rein informativ, da der Key die
     * Installation bereits festlegt. Unterscheidet mehrere Verbindungen.
     */
    public function getInstanceLabel(IntegrationConnection $connection): ?string
    {
        $label = ($connection->credentials ?? [])['instance'] ?? null;
        $label = $label !== null ? trim((string) $label) : '';

        return $label !== '' ? $label : null;
    }

    public function getBaseUrl(IntegrationConnection $connection): string
    {
        $baseUrl = ($connection->credentials ?? [])['base_url'] ?? null;
        $baseUrl = $baseUrl !== null ? trim((string) $baseUrl) : '';

        if ($baseUrl === '') {
            $baseUrl = (string) config('integrations.cisbox.api_base_url', 'https://api.cisbox.com/invoice/v1');
        }

        return rtrim($baseUrl, '/');
    }

    /** Weicht die Verbindung von der Standard-Cloud ab? */
    public function hasCustomBaseUrl(IntegrationConnection $connection): bool
    {
        $configured = ($connection->credentials ?? [])['base_url'] ?? null;

        return $configured !== null && trim((string) $configured) !== '';
    }

    public function updateApiKey(IntegrationConnection $connection, string $apiKey): void
    {
        $credentials = $connection->credentials ?? [];
        $credentials['api_key'] = trim($apiKey);

        $connection->credentials = $credentials;
        $connection->status = 'active';
        $connection->last_error = null;
        $connection->save();

        Log::info('cisbox API-Key aktualisiert', ['connection_id' => $connection->id]);
    }

    /**
     * Erstellt oder aktualisiert eine cisbox-Verbindung für einen User.
     *
     * @param string|null $apiKey  null behält den gespeicherten Key — damit sich
     *                             beim Bearbeiten nur das Label ändern lässt,
     *                             ohne den Key erneut eintippen zu müssen.
     * @param int|null    $connectionId Wenn gesetzt: Update dieser Verbindung
     */
    public function createOrUpdateConnectionForUser(
        User $user,
        ?string $apiKey,
        ?string $instanceLabel = null,
        ?string $baseUrl = null,
        ?int $connectionId = null
    ): IntegrationConnection {
        $integration = Integration::firstOrCreate(
            ['key' => self::INTEGRATION_KEY],
            [
                'name' => 'cisbox',
                'is_enabled' => true,
                'supported_auth_schemes' => ['api_key'],
                'meta' => [
                    'description' => 'cisbox Invoice API v1 — Eingangsrechnungen, offene Posten, Kreditoren, '
                        . 'Lieferungen, Zahlungen und Buchhaltungs-Stammdaten (lesend).',
                    'icon' => 'heroicon-o-document-currency-euro',
                ],
            ]
        );

        if ($connectionId) {
            $connection = IntegrationConnection::withTrashed()
                ->where('id', $connectionId)
                ->where('owner_user_id', $user->id)
                ->first();

            if (!$connection) {
                throw new \RuntimeException("Connection #{$connectionId} nicht gefunden.");
            }

            if ($connection->trashed()) {
                $connection->restore();
            }

            $connection->auth_scheme = 'api_key';
            $connection->status = 'active';
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
                'auth_scheme' => 'api_key',
                'status' => 'active',
            ]);
        }

        $credentials = $connection->credentials ?? [];

        if ($apiKey !== null && trim($apiKey) !== '') {
            $credentials['api_key'] = trim($apiKey);
        }

        if ($instanceLabel !== null) {
            $instanceLabel = trim($instanceLabel);
            if ($instanceLabel !== '') {
                $credentials['instance'] = $instanceLabel;
            } else {
                unset($credentials['instance']);
            }
        }

        if ($baseUrl !== null) {
            $baseUrl = rtrim(trim($baseUrl), '/');
            if ($baseUrl !== '') {
                $credentials['base_url'] = $baseUrl;
            } else {
                unset($credentials['base_url']);
            }
        }

        $connection->credentials = $credentials;
        $connection->last_error = null;
        $connection->save();

        // Label zusätzlich als Verbindungsname führen, damit mehrere
        // Installationen in der Übersicht unterscheidbar sind.
        if (!empty($credentials['instance']) && $connection->name !== $credentials['instance']) {
            $connection->name = $credentials['instance'];
            $connection->save();
        }

        Log::info('cisbox connection created/updated', [
            'connection_id' => $connection->id,
            'user_id' => $user->id,
        ]);

        return $connection;
    }

    /**
     * Testet die Verbindung über GET /invoices?itemsPerPage=1.
     *
     * Der Endpunkt ist die Kernressource der API und liefert über
     * meta.totalItems zugleich die Anzahl der Rechnungen in der Installation —
     * damit bestätigt der Test nicht nur den Key, sondern auch, auf welchen
     * Datenbestand er zeigt.
     *
     * Ein Netzwerkfehler lässt den Status unberührt: Er sagt nichts über die
     * Gültigkeit des Keys aus, und eine geteilte Verbindung bliebe sonst für
     * alle anderen gesperrt.
     *
     * @return array{success: bool, message: string, invoice_count?: int, request_id?: string}
     */
    public function testConnection(IntegrationConnection $connection): array
    {
        $apiKey = $this->getApiKey($connection);

        if (!$apiKey) {
            return $this->markTested($connection, false, 'Kein cisbox API-Key hinterlegt.', true);
        }

        $timeout = (int) config('integrations.cisbox.timeout.default', 30);
        $baseUrl = $this->getBaseUrl($connection);

        try {
            $response = Http::withHeaders([
                'X-API-Key' => $apiKey,
                'Accept' => 'application/json',
            ])
                ->timeout($timeout)
                ->connectTimeout((int) config('integrations.cisbox.timeout.connect', 10))
                ->get($baseUrl . self::PROBE_PATH, ['itemsPerPage' => 1]);
        } catch (\Throwable $e) {
            $message = HttpTransientFailure::describe($e, 'cisbox', $timeout);

            Log::warning('cisbox: Verbindungstest fehlgeschlagen', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
                'transient' => HttpTransientFailure::isTransient($e),
            ]);

            return $this->markTested(
                $connection,
                false,
                'Verbindungsfehler: ' . $message,
                !HttpTransientFailure::isTransient($e)
            );
        }

        $requestId = $response->header('X-Request-Id') ?: null;

        if ($response->successful()) {
            $total = $response->json('meta.totalItems');
            $label = $this->getInstanceLabel($connection);

            $message = 'Verbindung OK';
            $message .= $label ? " — {$label}" : '';
            $message .= $total !== null ? ', ' . number_format((int) $total, 0, ',', '.') . ' Rechnungen erreichbar.' : '.';

            $result = $this->markTested($connection, true, $message, false);

            if ($total !== null) {
                $result['invoice_count'] = (int) $total;
            }

            if ($requestId) {
                $result['request_id'] = $requestId;
            }

            return $result;
        }

        $message = CisboxApiException::fromResponse(
            $response->status(),
            is_array($response->json()) ? $response->json() : [],
            $response->body(),
            $requestId
        )->getMessage();

        // Nur 401/403 sagen etwas über den Key aus. 404, 422 und 5xx sind
        // fachliche oder serverseitige Fehler und dürfen die Verbindung nicht
        // für alle Mitnutzer stilllegen.
        $broken = in_array($response->status(), [401, 403], true);

        Log::warning('cisbox testConnection failed', [
            'connection_id' => $connection->id,
            'http_status' => $response->status(),
            'request_id' => $requestId,
            'marked_broken' => $broken,
        ]);

        $result = $this->markTested($connection, false, $message, $broken);

        if ($requestId) {
            $result['request_id'] = $requestId;
        }

        return $result;
    }

    public function deleteConnectionForUser(User $user): bool
    {
        $connection = $this->getConnectionForUser($user);

        if (!$connection || !$connection->isOwner($user)) {
            return false;
        }

        return (bool) $connection->delete();
    }

    /**
     * Hält das Testergebnis fest.
     *
     * @param bool $markBroken Nur bei echten Zugangsfehlern — sonst bleibt der
     *                         bisherige Status erhalten.
     *
     * @return array{success: bool, message: string}
     */
    private function markTested(
        IntegrationConnection $connection,
        bool $ok,
        string $message,
        bool $markBroken
    ): array {
        if ($ok) {
            $connection->status = 'active';
        } elseif ($markBroken) {
            $connection->status = 'error';
        }

        $connection->last_error = $ok ? null : $message;
        $connection->last_tested_at = now();
        $connection->save();

        return ['success' => $ok, 'message' => $message];
    }
}
