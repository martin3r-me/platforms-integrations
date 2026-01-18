<?php

namespace Platform\Integrations\Console\Commands;

use Illuminate\Console\Command;
use Platform\Integrations\Models\IntegrationsMetaToken;
use Platform\Integrations\Services\IntegrationsWhatsAppAccountService;

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

        // Meta Tokens finden
        $query = IntegrationsMetaToken::query();

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $metaTokens = $query->with(['user'])->get();

        if ($metaTokens->isEmpty()) {
            $this->warn('⚠️  Keine Meta Tokens gefunden.');
            return Command::SUCCESS;
        }

        $this->info("📋 {$metaTokens->count()} Meta Token(s) gefunden:");
        $this->newLine();

        $syncedCount = 0;
        $skippedCount = 0;

        foreach ($metaTokens as $metaToken) {
            $user = $metaToken->user;
            
            $this->info("  📝 Verarbeite User: '{$user->email}' (ID: {$user->id})");

            if ($isDryRun) {
                $this->info("     🔍 Würde WhatsApp Business Accounts synchronisieren");
                $syncedCount++;
                continue;
            }

            try {
                $result = $service->syncWhatsAppAccountsForUser($metaToken);
                $accountsCount = count($result);
                $this->info("     ✅ {$accountsCount} WhatsApp Business Account(s) synchronisiert");
                $syncedCount++;
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
