<?php

namespace Platform\Integrations\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Platform\Integrations\Exceptions\SipgateApiException;
use Platform\Integrations\Models\Integration;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Models\IntegrationsSipgateAccount;
use Platform\Integrations\Services\SipgateApiService;
use Platform\Integrations\Services\SipgateIntegrationService;

/**
 * Command zum Synchronisieren von Sipgate Accounts
 *
 * Synchronisiert Account-Informationen, Telefonnummern, Geräte und Guthaben
 * für alle aktiven Sipgate-Verbindungen.
 */
class SyncSipgateAccounts extends Command
{
    protected $signature = 'integrations:sync-sipgate-accounts
        {--user-id= : Spezifische User-ID zum Synchronisieren}
        {--connection-id= : Spezifische Connection-ID zum Synchronisieren}
        {--dry-run : Zeigt was synchronisiert würde, ohne es zu tun}';

    protected $description = 'Synchronisiert Sipgate Account-Informationen für alle aktiven Verbindungen';

    public function __construct(
        protected SipgateApiService $apiService,
        protected SipgateIntegrationService $integrationService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Starte Sipgate Account-Synchronisierung...');

        $connections = $this->getConnections();

        if ($connections->isEmpty()) {
            $this->warn('Keine aktiven Sipgate-Verbindungen gefunden.');
            return self::SUCCESS;
        }

        $this->info("Gefundene Verbindungen: {$connections->count()}");

        $synced = 0;
        $errors = 0;

        foreach ($connections as $connection) {
            try {
                $this->syncConnection($connection);
                $synced++;
            } catch (\Exception $e) {
                $errors++;
                $this->error("Fehler bei Connection {$connection->id}: {$e->getMessage()}");

                Log::error('Sipgate sync error', [
                    'connection_id' => $connection->id,
                    'user_id' => $connection->owner_user_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Synchronisierung abgeschlossen: {$synced} erfolgreich, {$errors} Fehler");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function getConnections()
    {
        $integration = Integration::where('key', 'sipgate')->first();

        if (!$integration) {
            return collect();
        }

        $query = IntegrationConnection::query()
            ->where('integration_id', $integration->id)
            ->where('status', 'active');

        if ($this->option('connection-id')) {
            $query->where('id', $this->option('connection-id'));
        }

        if ($this->option('user-id')) {
            $query->where('owner_user_id', $this->option('user-id'));
        }

        return $query->get();
    }

    protected function syncConnection(IntegrationConnection $connection): void
    {
        $user = $connection->ownerUser;

        if (!$user) {
            $this->warn("Connection {$connection->id}: Kein User gefunden, überspringe.");
            return;
        }

        $this->line("Synchronisiere Connection {$connection->id} für User {$user->email}...");

        if ($this->option('dry-run')) {
            $this->info("  [DRY-RUN] Würde Account-Daten abrufen...");
            return;
        }

        // 1. User-Info abrufen
        try {
            $userInfo = $this->apiService->getUserInfo($user);
            $this->line("  User-Info abgerufen: {$userInfo['sub'] ?? 'N/A'}");
        } catch (SipgateApiException $e) {
            throw $e;
        }

        // 2. Account-Info abrufen
        try {
            $accountInfo = $this->apiService->getAccount($user);
            $this->line("  Account-Info abgerufen: {$accountInfo['company'] ?? 'N/A'}");
        } catch (SipgateApiException $e) {
            $this->warn("  Account-Info konnte nicht abgerufen werden: {$e->getMessage()}");
            $accountInfo = [];
        }

        // 3. Guthaben abrufen
        try {
            $balance = $this->apiService->getBalance($user);
            $this->line("  Guthaben abgerufen: {$balance['amount'] ?? 0} {$balance['currency'] ?? 'EUR'}");
        } catch (SipgateApiException $e) {
            $this->warn("  Guthaben konnte nicht abgerufen werden: {$e->getMessage()}");
            $balance = [];
        }

        // 4. Telefonnummern abrufen
        try {
            $numbers = $this->apiService->getNumbers($user);
            $numberCount = count($numbers['items'] ?? []);
            $this->line("  Telefonnummern abgerufen: {$numberCount}");
        } catch (SipgateApiException $e) {
            $this->warn("  Telefonnummern konnten nicht abgerufen werden: {$e->getMessage()}");
            $numbers = [];
        }

        // 5. Geräte abrufen
        try {
            $devices = $this->apiService->getDevices($user);
            $deviceCount = count($devices['items'] ?? []);
            $this->line("  Geräte abgerufen: {$deviceCount}");
        } catch (SipgateApiException $e) {
            $this->warn("  Geräte konnten nicht abgerufen werden: {$e->getMessage()}");
            $devices = [];
        }

        // 6. Account speichern/aktualisieren
        $sipgateUserId = $userInfo['sub'] ?? $userInfo['id'] ?? null;

        if (!$sipgateUserId) {
            $this->warn("  Keine Sipgate User-ID gefunden, überspringe Speicherung.");
            return;
        }

        $sipgateAccount = IntegrationsSipgateAccount::updateOrCreate(
            [
                'sipgate_user_id' => $sipgateUserId,
                'integration_connection_id' => $connection->id,
            ],
            [
                'user_id' => $user->id,
                'email' => $userInfo['email'] ?? null,
                'firstname' => $userInfo['firstname'] ?? $accountInfo['firstname'] ?? null,
                'lastname' => $userInfo['lastname'] ?? $accountInfo['lastname'] ?? null,
                'company' => $accountInfo['company'] ?? null,
                'locale' => $userInfo['locale'] ?? null,
                'timezone' => $userInfo['timezone'] ?? null,
                'admin' => $userInfo['admin'] ?? false,
                'active' => true,
                'phone_numbers' => $numbers['items'] ?? null,
                'devices' => $devices['items'] ?? null,
                'balance' => isset($balance['amount']) ? $balance['amount'] / 10000 : null, // Sipgate gibt Cents * 100
                'balance_currency' => $balance['currency'] ?? 'EUR',
                'last_synced_at' => now(),
                'sync_status' => 'synced',
            ]
        );

        $this->info("  Account gespeichert (ID: {$sipgateAccount->id})");

        Log::info('Sipgate account synced', [
            'connection_id' => $connection->id,
            'user_id' => $user->id,
            'sipgate_user_id' => $sipgateUserId,
            'account_id' => $sipgateAccount->id,
        ]);
    }
}
