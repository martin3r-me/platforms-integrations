<?php

namespace Platform\Integrations\Console\Commands;

use Illuminate\Console\Command;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Models\Integration;
use Platform\Integrations\Services\IntegrationGithubRepoService;
use Platform\Integrations\Services\IntegrationGithubDataService;

class SyncGithubData extends Command
{
    protected $signature = 'integrations:sync-github-data
                            {--connection-id= : Specific connection ID to sync}
                            {--skip-repos : Skip repo sync, only fetch commits and PRs}
                            {--dry-run : Show what would be synced without actually doing it}';

    protected $description = 'Synchronize GitHub repos, commits and pull requests';

    public function handle(IntegrationGithubRepoService $repoService, IntegrationGithubDataService $dataService)
    {
        $isDryRun = $this->option('dry-run');
        $skipRepos = $this->option('skip-repos');
        $connectionId = $this->option('connection-id');

        if ($isDryRun) {
            $this->info('DRY-RUN Modus - keine Daten werden synchronisiert');
        }

        $this->info('Starte GitHub Synchronisation (Repos + Commits + PRs)...');
        $this->newLine();

        $githubIntegration = Integration::where('key', 'github')->first();

        if (!$githubIntegration) {
            $this->error('GitHub Integration nicht gefunden.');
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

        foreach ($connections as $connection) {
            $user = $connection->ownerUser;
            $this->info("  Connection #{$connection->id} (User: {$user->email})");

            if ($isDryRun) {
                $this->info("     Würde Repos, Commits und PRs synchronisieren");
                continue;
            }

            // Step 1: Sync repos
            if (!$skipRepos) {
                try {
                    $repoResult = $repoService->syncRepos($connection);
                    $this->info("     Repos: {$repoResult['synced']} synchronisiert, {$repoResult['deactivated']} deaktiviert");
                } catch (\Exception $e) {
                    $this->error("     Repos-Fehler: {$e->getMessage()}");
                    continue;
                }
            }

            // Step 2: Sync commits + PRs
            try {
                $dataResult = $dataService->syncAll($connection);
                $this->info("     Commits: {$dataResult['commits_synced']} synchronisiert");
                $this->info("     PRs: {$dataResult['prs_synced']} synchronisiert");
                $this->info("     Repos verarbeitet: {$dataResult['repos_processed']}");
            } catch (\Exception $e) {
                $this->error("     Data-Fehler: {$e->getMessage()}");
            }

            $this->newLine();
        }

        $this->info('GitHub Synchronisation abgeschlossen.');

        return Command::SUCCESS;
    }
}
