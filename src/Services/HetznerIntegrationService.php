<?php

namespace Platform\Integrations\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Core\Models\User;
use Platform\Integrations\Models\Integration;
use Platform\Integrations\Models\IntegrationConnection;

/**
 * Helper-Service für Hetzner-Cloud-Integrationen (API v1).
 *
 * Hetzner Cloud nutzt ein projektbezogenes API-Token (Bearer):
 *   Authorization: Bearer <api_key>
 *
 * Das Token wird in der Hetzner Cloud Console erzeugt:
 *   Projekt → Security → API Tokens → "Generate API token"
 * Es gilt IMMER nur für genau ein Projekt und wird nur einmal angezeigt.
 * Für mehrere Projekte wird je Projekt eine eigene Connection angelegt.
 *
 * Base-URL ist fix: https://api.hetzner.cloud/v1
 *
 * @see https://docs.hetzner.cloud/reference/cloud
 */
class HetznerIntegrationService
{
    public const INTEGRATION_KEY = 'hetzner';

    protected IntegrationConnectionResolver $resolver;

    public function __construct(IntegrationConnectionResolver $resolver)
    {
        $this->resolver = $resolver;
    }

    public function getConnectionForUser(User $user): ?IntegrationConnection
    {
        return $this->resolver->resolveForUser(self::INTEGRATION_KEY, $user);
    }

    public function getApiTokenForUser(User $user): ?string
    {
        $connection = $this->getConnectionForUser($user);

        return $connection ? $this->getApiToken($connection) : null;
    }

    public function getApiToken(IntegrationConnection $connection): ?string
    {
        $token = ($connection->credentials ?? [])['api_key'] ?? null;

        return $token !== null ? trim((string) $token) : null;
    }

    public function hasValidApiToken(IntegrationConnection $connection): bool
    {
        return !empty($this->getApiToken($connection));
    }

    /**
     * Frei wählbares Projekt-Label — rein informativ, da das Token das Projekt
     * bereits eindeutig festlegt. Hilft beim Unterscheiden mehrerer Connections.
     */
    public function getProjectLabel(IntegrationConnection $connection): ?string
    {
        $label = ($connection->credentials ?? [])['project'] ?? null;
        $label = $label !== null ? trim((string) $label) : '';

        return $label !== '' ? $label : null;
    }

    public function getBaseUrl(IntegrationConnection $connection): string
    {
        $baseUrl = ($connection->credentials ?? [])['base_url']
            ?? config('integrations.hetzner.api_base_url', 'https://api.hetzner.cloud/v1');

        return rtrim(trim((string) $baseUrl), '/');
    }

    public function updateApiToken(IntegrationConnection $connection, string $apiToken): void
    {
        $credentials = $connection->credentials ?? [];
        $credentials['api_key'] = trim($apiToken);

        $connection->credentials = $credentials;
        $connection->status = 'active';
        $connection->last_error = null;
        $connection->save();

        Log::info('Hetzner API-Token aktualisiert', ['connection_id' => $connection->id]);
    }

    /**
     * Erstellt oder aktualisiert eine Hetzner-Cloud-Connection für einen User.
     *
     * @param int|null $connectionId Wenn gesetzt: Update dieser Connection; null = neue Connection
     */
    public function createOrUpdateConnectionForUser(
        User $user,
        string $apiToken,
        ?string $projectLabel = null,
        ?int $connectionId = null
    ): IntegrationConnection {
        $integration = Integration::firstOrCreate(
            ['key' => self::INTEGRATION_KEY],
            [
                'name' => 'Hetzner Cloud',
                'is_enabled' => true,
                'supported_auth_schemes' => ['api_key'],
                'meta' => [
                    'description' => 'Hetzner Cloud Integration (API v1) für Server, Volumes, Netzwerke und DNS-Zonen.',
                    'icon' => 'heroicon-o-cloud',
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
        $credentials['api_key'] = trim($apiToken);

        if ($projectLabel !== null) {
            $projectLabel = trim($projectLabel);
            if ($projectLabel !== '') {
                $credentials['project'] = $projectLabel;
            } else {
                unset($credentials['project']);
            }
        }

        $connection->credentials = $credentials;
        $connection->last_error = null;
        $connection->save();

        // Projekt-Label zusätzlich als Connection-Name führen, damit mehrere
        // Hetzner-Projekte in der Übersicht unterscheidbar sind.
        if (!empty($credentials['project']) && $connection->name !== $credentials['project']) {
            $connection->name = $credentials['project'];
            $connection->save();
        }

        Log::info('Hetzner connection created/updated', [
            'connection_id' => $connection->id,
            'user_id' => $user->id,
        ]);

        return $connection;
    }

    /**
     * Testet die Hetzner-Verbindung über GET /servers?per_page=1.
     *
     * Der Endpunkt ist leichtgewichtig, erfordert nur Leserechte und liefert
     * über meta.pagination.total_entries direkt die Server-Anzahl des Projekts.
     *
     * @return array{success: bool, message: string, server_count?: int}
     */
    public function testConnection(IntegrationConnection $connection): array
    {
        $token = $this->getApiToken($connection);

        if (!$token) {
            return $this->markTested($connection, false, 'Kein Hetzner-Cloud API-Token hinterlegt.');
        }

        $baseUrl = $this->getBaseUrl($connection);
        $timeout = (int) config('integrations.hetzner.timeout.default', 30);
        $connectTimeout = (int) config('integrations.hetzner.timeout.connect', 10);

        try {
            $response = Http::withToken($token)
                ->timeout($timeout)
                ->connectTimeout($connectTimeout)
                ->withHeaders(['Accept' => 'application/json'])
                ->get($baseUrl . '/servers', ['per_page' => 1]);

            if ($response->status() === 401) {
                return $this->markTested($connection, false, 'Token ungültig oder abgelaufen (HTTP 401).');
            }

            if ($response->status() === 403) {
                return $this->markTested($connection, false, 'Token hat keine Berechtigung (HTTP 403).');
            }

            if (!$response->successful()) {
                $body = $response->json();
                $message = $body['error']['message'] ?? $response->body();

                return $this->markTested(
                    $connection,
                    false,
                    'Hetzner-API-Fehler: HTTP ' . $response->status() . ' — ' . $message
                );
            }

            $total = $response->json('meta.pagination.total_entries');
            $label = $this->getProjectLabel($connection);

            $message = 'Verbindung OK';
            $message .= $label ? " — Projekt '{$label}'" : '';
            $message .= $total !== null ? ", {$total} Server im Projekt." : '.';

            $result = $this->markTested($connection, true, $message);

            if ($total !== null) {
                $result['server_count'] = (int) $total;
            }

            return $result;
        } catch (\Throwable $e) {
            Log::warning('Hetzner: Verbindungstest fehlgeschlagen', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
            ]);

            return $this->markTested($connection, false, 'Verbindungsfehler: ' . $e->getMessage());
        }
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
     * @return array{success: bool, message: string}
     */
    private function markTested(IntegrationConnection $connection, bool $ok, string $message): array
    {
        $connection->status = $ok ? 'active' : 'error';
        $connection->last_error = $ok ? null : $message;
        $connection->last_tested_at = now();
        $connection->save();

        return ['success' => $ok, 'message' => $message];
    }
}
