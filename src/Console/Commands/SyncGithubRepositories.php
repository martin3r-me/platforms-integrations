<?php

namespace Platform\Integrations\Console\Commands;

use Illuminate\Console\Command;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Models\Integration;
use Platform\Core\Models\User;
use Platform\Integrations\Services\IntegrationsGithubRepositoryService;

class SyncGithubRepositories extends Command
{
    protected $signature = 'integrations:sync-github-repositories 
                            {--user-id= : Specific user ID to sync}
                            {--dry-run : Show what would be synced without actually doing it}';

    protected $description = 'Synchronize GitHub Repositories for users (Integrations module)';

    public function handle(IntegrationsGithubRepositoryService $service)
    {
        $isDryRun = $this->option('dry-run');
        $userId = $this->option('user-id');

        if ($isDryRun) {
            $this->info('🔍 DRY-RUN Modus - keine Daten werden synchronisiert');
        }

        $this->info('🔄 Starte GitHub Repositories Synchronisation...');
        $this->newLine();

        // GitHub Integration finden
        $githubIntegration = Integration::where('key', 'github')->first();
        
        if (!$githubIntegration) {
            $this->error('⚠️  GitHub Integration nicht gefunden. Bitte zuerst "php artisan integrations:seed" ausführen.');
            return Command::FAILURE;
        }

        // GitHub Connections finden
        $query = IntegrationConnection::query()
            ->where('integration_id', $githubIntegration->id);

        if ($userId) {
            $query->where('owner_user_id', $userId);
        }

        $connections = $query->with(['ownerUser'])->get();

        if ($connections->isEmpty()) {
            $this->warn('⚠️  Keine GitHub Connections gefunden.');
            return Command::SUCCESS;
        }

        $this->info("📋 {$connections->count()} GitHub Connection(s) gefunden:");
        $this->newLine();

        $syncedCount = 0;
        $skippedCount = 0;

        foreach ($connections as $connection) {
            $user = $connection->ownerUser;
            
            $this->info("  📝 Verarbeite User: '{$user->email}' (ID: {$user->id})");

            if ($isDryRun) {
                $this->info("     🔍 Würde GitHub Repositories synchronisieren");
                $syncedCount++;
                continue;
            }

            try {
                $result = $service->syncGithubRepositoriesForUser($connection);
                $reposCount = count($result);
                $this->info("     ✅ {$reposCount} GitHub Repository/Repositories synchronisiert");
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
