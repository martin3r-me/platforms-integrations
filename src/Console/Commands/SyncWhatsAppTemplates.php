<?php

namespace Platform\Integrations\Console\Commands;

use Illuminate\Console\Command;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Models\Integration;
use Platform\Integrations\Services\IntegrationsWhatsAppAccountService;

class SyncWhatsAppTemplates extends Command
{
    protected $signature = 'integrations:sync-whatsapp-templates
                            {--user-id= : Specific user ID to sync}
                            {--dry-run : Show what would be synced without actually doing it}';

    protected $description = 'Synchronize WhatsApp Message Templates for all WhatsApp Business Accounts';

    public function handle(IntegrationsWhatsAppAccountService $service)
    {
        $isDryRun = $this->option('dry-run');
        $userId = $this->option('user-id');

        if ($isDryRun) {
            $this->info('DRY-RUN Modus - keine Daten werden synchronisiert');
        }

        $this->info('Starte WhatsApp Templates Synchronisation...');
        $this->newLine();

        // Meta Integration finden
        $metaIntegration = Integration::where('key', 'meta')->first();

        if (!$metaIntegration) {
            $this->error('Meta Integration nicht gefunden. Bitte zuerst "php artisan integrations:seed" ausführen.');
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
            $this->warn('Keine Meta Connections gefunden.');
            return Command::SUCCESS;
        }

        $this->info("{$connections->count()} Meta Connection(s) gefunden:");
        $this->newLine();

        $syncedCount = 0;
        $skippedCount = 0;

        foreach ($connections as $connection) {
            $user = $connection->ownerUser;

            $this->info("  Verarbeite User: '{$user->email}' (ID: {$user->id})");

            if ($isDryRun) {
                $this->info("     Würde WhatsApp Templates synchronisieren");
                $syncedCount++;
                continue;
            }

            try {
                $templates = $service->syncAllWhatsAppTemplates($connection);
                $templatesCount = count($templates);
                $this->info("     {$templatesCount} WhatsApp Template(s) synchronisiert");
                $syncedCount++;
            } catch (\Exception $e) {
                $this->error("     Fehler: {$e->getMessage()}");
                $skippedCount++;
            }
        }

        $this->newLine();

        if ($isDryRun) {
            $this->warn("DRY-RUN: {$syncedCount} User(s) würden synchronisiert, {$skippedCount} übersprungen");
        } else {
            $this->info("{$syncedCount} User(s) erfolgreich synchronisiert, {$skippedCount} übersprungen");
        }

        return Command::SUCCESS;
    }
}
