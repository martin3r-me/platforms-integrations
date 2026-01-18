<?php

namespace Platform\Integrations\Console\Commands;

use Illuminate\Console\Command;
use Platform\Integrations\Models\IntegrationsMetaToken;
use Platform\Core\Models\User;
use Platform\Integrations\Services\IntegrationsFacebookPageService;

class SyncFacebookPages extends Command
{
    protected $signature = 'integrations:sync-facebook-pages 
                            {--user-id= : Specific user ID to sync}
                            {--dry-run : Show what would be synced without actually doing it}';

    protected $description = 'Synchronize Facebook Pages for users (Integrations module)';

    public function handle(IntegrationsFacebookPageService $service)
    {
        $isDryRun = $this->option('dry-run');
        $userId = $this->option('user-id');

        if ($isDryRun) {
            $this->info('🔍 DRY-RUN Modus - keine Daten werden synchronisiert');
        }

        $this->info('🔄 Starte Facebook Pages Synchronisation...');
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
                $this->info("     🔍 Würde Facebook Pages synchronisieren");
                $syncedCount++;
                continue;
            }

            try {
                $result = $service->syncFacebookPagesForUser($metaToken);
                $pagesCount = count($result);
                $this->info("     ✅ {$pagesCount} Facebook Page(s) synchronisiert");
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
