<?php

namespace Platform\Integrations\Console\Commands;

use Illuminate\Console\Command;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Models\Integration;
use Platform\Integrations\Services\IntegrationMetaResourceService;

class SyncMetaResources extends Command
{
    protected $signature = 'integrations:sync-meta-resources
                            {--connection-id= : Specific connection ID to sync}
                            {--dry-run : Show what would be synced without actually doing it}';

    protected $description = 'Synchronize Meta resources (Facebook Pages + Instagram Accounts) as shareable resources';

    public function handle(IntegrationMetaResourceService $service)
    {
        $isDryRun = $this->option('dry-run');
        $connectionId = $this->option('connection-id');

        if ($isDryRun) {
            $this->info('DRY-RUN Modus - keine Daten werden synchronisiert');
        }

        $this->info('Starte Meta Ressourcen-Synchronisation (Facebook Pages + Instagram Accounts)...');
        $this->newLine();

        $metaIntegration = Integration::where('key', 'meta')->first();

        if (!$metaIntegration) {
            $this->error('Meta Integration nicht gefunden. Bitte zuerst "php artisan integrations:seed" ausführen.');
            return Command::FAILURE;
        }

        $query = IntegrationConnection::query()
            ->where('integration_id', $metaIntegration->id)
            ->where('status', 'active');

        if ($connectionId) {
            $query->where('id', $connectionId);
        }

        $connections = $query->with(['ownerUser'])->get();

        if ($connections->isEmpty()) {
            $this->warn('Keine aktiven Meta Connections gefunden.');
            return Command::SUCCESS;
        }

        $this->info("{$connections->count()} Meta Connection(s) gefunden:");
        $this->newLine();

        $syncedCount = 0;
        $skippedCount = 0;

        foreach ($connections as $connection) {
            $user = $connection->ownerUser;
            $this->info("  Verarbeite Connection #{$connection->id} (User: {$user->email})");

            if ($isDryRun) {
                $this->info("     Würde Meta Ressourcen synchronisieren");
                $syncedCount++;
                continue;
            }

            try {
                $result = $service->syncResources($connection);

                $pages = $result['facebook_pages'];
                $accounts = $result['instagram_accounts'];

                $this->info("     Facebook Pages: {$pages['synced']} synchronisiert, {$pages['deactivated']} deaktiviert");
                $this->info("     Instagram Accounts: {$accounts['synced']} synchronisiert, {$accounts['deactivated']} deaktiviert");
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
