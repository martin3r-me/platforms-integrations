<?php

namespace Platform\Integrations\Console\Commands;

use Illuminate\Console\Command;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Models\Integration;
use Platform\Integrations\Services\IntegrationGithubRepoService;

class SyncGithubRepos extends Command
{
    protected $signature = 'integrations:sync-github-repos
                            {--connection-id= : Specific connection ID to sync}
                            {--dry-run : Show what would be synced without actually doing it}';

    protected $description = 'Synchronize GitHub Repos as shareable resources (integration_github_repos)';

    public function handle(IntegrationGithubRepoService $service)
    {
        $isDryRun = $this->option('dry-run');
        $connectionId = $this->option('connection-id');

        if ($isDryRun) {
            $this->info('DRY-RUN Modus - keine Daten werden synchronisiert');
        }

        $this->info('Starte GitHub Repos Ressourcen-Synchronisation...');
        $this->newLine();

        $githubIntegration = Integration::where('key', 'github')->first();

        if (!$githubIntegration) {
            $this->error('GitHub Integration nicht gefunden. Bitte zuerst "php artisan integrations:seed" ausführen.');
            return Command::FAILURE;
        }

        $query = IntegrationConnection::query()
            ->where('integration_id', $githubIntegration->id)
            ->where('status', 'active');

        if ($connectionId) {
            $query->where('id', $connectionId);
        }

        $connections = $query->with(['ownerUser'])->get();

        if ($connections->isEmpty()) {
            $this->warn('Keine aktiven GitHub Connections gefunden.');
            return Command::SUCCESS;
        }

        $this->info("{$connections->count()} GitHub Connection(s) gefunden:");
        $this->newLine();

        $syncedCount = 0;
        $skippedCount = 0;

        foreach ($connections as $connection) {
            $user = $connection->ownerUser;
            $this->info("  Verarbeite Connection #{$connection->id} (User: {$user->email})");

            if ($isDryRun) {
                $this->info("     Würde GitHub Repos synchronisieren");
                $syncedCount++;
                continue;
            }

            try {
                $result = $service->syncRepos($connection);
                $this->info("     {$result['synced']} Repo(s) synchronisiert, {$result['deactivated']} deaktiviert");
                $syncedCount++;
            } catch (\Exception $e) {
                $this->error("     Fehler: {$e->getMessage()}");
                $skippedCount++;
            }
        }

        $this->newLine();

        if ($isDryRun) {
            $this->warn("DRY-RUN: {$syncedCount} Connection(s) würden synchronisiert, {$skippedCount} übersprungen");
        } else {
            $this->info("{$syncedCount} Connection(s) erfolgreich synchronisiert, {$skippedCount} übersprungen");
        }

        return Command::SUCCESS;
    }
}
