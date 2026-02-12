<?php

namespace Platform\Integrations\Console\Commands;

use Illuminate\Console\Command;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Models\Integration;
use Platform\Integrations\Services\IntegrationsWhatsAppAccountService;
use Platform\Integrations\Events\WhatsAppAccountsSynced;

class SyncWhatsAppAccounts extends Command
{
    protected $signature = 'integrations:sync-whatsapp-accounts 
                            {--user-id= : Specific user ID to sync}
                            {--dry-run : Show what would be synced without actually doing it}';

    protected $description = 'Synchronize WhatsApp Business Accounts for users (Integrations module)';

    public function handle(IntegrationsWhatsAppAccountService $service)
    {
        $isDryRun = $this->option('dry-run');
        $userId = $this->option('user-id');

        if ($isDryRun) {
            $this->info('🔍 DRY-RUN Modus - keine Daten werden synchronisiert');
        }

        $this->info('🔄 Starte WhatsApp Business Accounts Synchronisation...');
        $this->newLine();

        // Meta Integration finden
        $metaIntegration = Integration::where('key', 'meta')->first();
        
        if (!$metaIntegration) {
            $this->error('⚠️  Meta Integration nicht gefunden. Bitte zuerst "php artisan integrations:seed" ausführen.');
            return Command::FAILURE;
        }

        // Meta Connections finden
        $query = IntegrationConnection::query()
            ->where('integration_id', $metaIntegration->id);

        if ($userId) {
            $query->where('owner_user_id', $userId);
        }

        $connections = $query->with(['ownerUser'])->get();

        if ($connections->isEmpty()) {
            $this->warn('⚠️  Keine Meta Connections gefunden.');
            return Command::SUCCESS;
        }

        $this->info("📋 {$connections->count()} Meta Connection(s) gefunden:");
        $this->newLine();

        $syncedCount = 0;
        $skippedCount = 0;

        foreach ($connections as $connection) {
            $user = $connection->ownerUser;
            
            $this->info("  📝 Verarbeite User: '{$user->email}' (ID: {$user->id})");

            if ($isDryRun) {
                $this->info("     🔍 Würde WhatsApp Business Accounts synchronisieren");
                $syncedCount++;
                continue;
            }

            try {
                $result = $service->syncWhatsAppAccountsForUser($connection);
                $accountsCount = count($result);
                $this->info("     ✅ {$accountsCount} WhatsApp Business Account(s) synchronisiert");
                $syncedCount++;

                // Event feuern für Comms Channel Sync
                WhatsAppAccountsSynced::dispatch($connection, collect($result));
            } catch (\Exception $e) {
                $this->error("     ❌ Fehler: {$e->getMessage()}");
                $skippedCount++;
            }
        }

        $this->newLine();

        if ($isDryRun) {
            $this->warn("🔍 DRY-RUN: {$syncedCount} User(s) würden synchronisiert, {$skippedCount} übersprungen");
        } else {
            $this->info("✅ {$syncedCount} User(s) erfolgreich synchronisiert, {$skippedCount} übersprungen");
        }

        return Command::SUCCESS;
    }
}
