<?php

namespace Platform\Integrations\Services;

use Platform\Core\Models\User;
use Platform\Integrations\Models\Integration;
use Platform\Integrations\Models\IntegrationConnection;
use Illuminate\Support\Facades\Log;

/**
 * Helper-Service für Plausible Analytics Integrationen
 *
 * Plausible verwendet API-Key-Authentifizierung (Bearer Token).
 * Die Credentials werden in credentials.api_key gespeichert,
 * die Base-URL in credentials.base_url (Self-Hosted-Support).
 */
class PlausibleIntegrationService
{
    protected IntegrationConnectionResolver $resolver;

    public function __construct(IntegrationConnectionResolver $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * Ruft die Plausible-IntegrationConnection für einen User ab
     */
    public function getConnectionForUser(User $user): ?IntegrationConnection
    {
        return $this->resolver->resolveForUser('plausible', $user);
    }

    /**
     * Gibt den API-Key aus den Credentials zurück
     */
    public function getApiKey(IntegrationConnection $connection): ?string
    {
        $credentials = $connection->credentials ?? [];
        return $credentials['api_key'] ?? null;
    }

    /**
     * Gibt die Base-URL aus den Credentials oder Config zurück
     */
    public function getBaseUrl(IntegrationConnection $connection): string
    {
        $credentials = $connection->credentials ?? [];
        $baseUrl = $credentials['base_url']
            ?? config('integrations.plausible.api_base_url', 'https://plausible.io');

        // Trailing-Slash entfernen — sonst entsteht beim Anhängen der Pfade
        // ein doppelter Slash (https://host//api/v1/...), was manche Plausible-
        // Routen (z.B. /api/v1/sites) mit 404/406 abweisen.
        return rtrim(trim($baseUrl), '/');
    }

    /**
     * Prüft, ob die Connection einen gültigen API-Key hat
     */
    public function hasValidCredentials(IntegrationConnection $connection): bool
    {
        return !empty($this->getApiKey($connection));
    }

    /**
     * Erstellt oder aktualisiert eine Plausible-IntegrationConnection für einen User.
     *
     * @param int|null $connectionId Wenn gesetzt: Update dieser Connection; null = neue Connection
     */
    public function createOrUpdateConnectionForUser(User $user, string $apiKey, ?string $baseUrl = null, ?int $connectionId = null): IntegrationConnection
    {
        $integration = Integration::firstOrCreate(
            ['key' => 'plausible'],
            [
                'name' => 'Plausible Analytics',
                'is_enabled' => true,
                'supported_auth_schemes' => ['api_key'],
                'meta' => [
                    'description' => 'Plausible Analytics Integration für Traffic-Daten und Statistiken.',
                    'icon' => 'heroicon-o-chart-bar',
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
        $credentials['api_key'] = $apiKey;
        if ($baseUrl) {
            $credentials['base_url'] = rtrim($baseUrl, '/');
        }

        $connection->credentials = $credentials;
        $connection->last_error = null;
        $connection->save();

        Log::info('Plausible connection created/updated', [
            'connection_id' => $connection->id,
            'user_id' => $user->id,
        ]);

        return $connection;
    }

    /**
     * Testet die Plausible API-Verbindung
     *
     * @return array{success: bool, message: string}
     */
    /**
     * Prüft die Plausible-Verbindung. Maßgeblich ist die Stats-API, weil das
     * SEO-Modul ausschließlich diese nutzt.
     *
     * - Mit $siteId: echter Stats-Probe (aussagekräftigster Check).
     * - Ohne $siteId: Sites-API als Bonus. Viele self-hosted-Instanzen
     *   exponieren die Sites-Provisioning-API nicht (404/406) — das ist dann
     *   KEIN echter Verbindungsfehler, sondern ein Hinweis, mit site_id zu prüfen.
     */
    public function testConnection(IntegrationConnection $connection, ?string $siteId = null): array
    {
        $apiKey = $this->getApiKey($connection);

        if (!$apiKey) {
            return ['success' => false, 'message' => 'Kein Plausible API-Key vorhanden.'];
        }

        $baseUrl = $this->getBaseUrl($connection);

        try {
            // 1) Stats-API-Vollcheck, wenn eine site_id vorliegt.
            if ($siteId) {
                $response = \Illuminate\Support\Facades\Http::withToken($apiKey)
                    ->timeout(10)
                    ->withHeaders(['Accept' => 'application/json'])
                    ->get($baseUrl . '/api/v1/stats/aggregate', [
                        'site_id' => $siteId,
                        'period' => '7d',
                        'metrics' => 'visitors',
                    ]);

                if ($response->successful()) {
                    return $this->markTested($connection, true, "Stats-API OK für '{$siteId}'.");
                }

                return $this->markTested($connection, false,
                    "Stats-API-Fehler für '{$siteId}': HTTP {$response->status()} — "
                    . ($response->json()['error'] ?? $response->body()));
            }

            // 2) Ohne site_id: Sites-API versuchen (nur als Bonus).
            $response = \Illuminate\Support\Facades\Http::withToken($apiKey)
                ->timeout(10)
                ->withHeaders(['Accept' => 'application/json'])
                ->get($baseUrl . '/api/v1/sites');

            if ($response->successful()) {
                $count = count($response->json()['sites'] ?? []);
                return $this->markTested($connection, true, "Verbindung OK — Sites-API verfügbar ({$count} Sites).");
            }

            return $this->markTested($connection, false,
                "API-Key gesetzt, aber Sites-API nicht verfügbar (HTTP {$response->status()}). "
                . "Bei self-hosted-Instanzen ist das normal — für einen echten Check bitte eine site_id "
                . "angeben; dann wird die Stats-API geprüft, die das SEO-Modul tatsächlich nutzt.");
        } catch (\Exception $e) {
            return $this->markTested($connection, false, 'Verbindungsfehler: ' . $e->getMessage());
        }
    }

    /**
     * Persistiert das Testergebnis auf der Connection und gibt es zurück.
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
