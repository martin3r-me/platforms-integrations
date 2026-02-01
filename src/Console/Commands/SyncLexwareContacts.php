<?php

namespace Platform\Integrations\Console\Commands;

use Illuminate\Console\Command;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Models\Integration;
use Platform\Core\Models\User;
use Platform\Integrations\Services\IntegrationsLexwareContactService;

class SyncLexwareContacts extends Command
{
    protected $signature = 'integrations:sync-lexware-contacts
                            {--user-id= : Specific user ID to sync}
                            {--dry-run : Show what would be synced without actually doing it}';

    protected $description = 'Synchronize Lexware Contacts for users (Integrations module)';

    public function handle(IntegrationsLexwareContactService $service)
    {
        $isDryRun = $this->option('dry-run');
        $userId = $this->option('user-id');

        if ($isDryRun) {
            $this->info('DRY-RUN Modus - keine Daten werden synchronisiert');
        }

        $this->info('Starte Lexware Kontakte Synchronisation...');
        $this->newLine();

        // Lexware Integration finden
        $lexwareIntegration = Integration::where('key', 'lexoffice')->first();

        if (!$lexwareIntegration) {
            $this->error('Lexware Integration nicht gefunden. Bitte zuerst "php artisan integrations:seed" ausfuehren.');
            return Command::FAILURE;
        }

        // Lexware Connections finden
        $query = IntegrationConnection::query()
            ->where('integration_id', $lexwareIntegration->id)
            ->where('status', 'active');

        if ($userId) {
            $query->where('owner_user_id', $userId);
        }

        $connections = $query->with(['ownerUser'])->get();

        if ($connections->isEmpty()) {
            $this->warn('Keine aktiven Lexware Connections gefunden.');
            return Command::SUCCESS;
        }

        $this->info("{$connections->count()} Lexware Connection(s) gefunden:");
        $this->newLine();

        $syncedCount = 0;
        $skippedCount = 0;

        foreach ($connections as $connection) {
            $user = $connection->ownerUser;

            $this->info("  Verarbeite User: '{$user->email}' (ID: {$user->id})");

            if ($isDryRun) {
                $this->info("     Wuerde Lexware Kontakte synchronisieren");
                $syncedCount++;
                continue;
            }

            try {
                $result = $service->syncContactsForUser($connection);
                $contactsCount = count($result);
                $this->info("     {$contactsCount} Lexware Kontakt(e) synchronisiert");
                $syncedCount++;
            } catch (\Exception $e) {
                $this->error("     Fehler: {$e->getMessage()}");
                $skippedCount++;
            }
        }

        $this->newLine();

        if ($isDryRun) {
            $this->warn("DRY-RUN: {$syncedCount} User(s) wuerden synchronisiert, {$skippedCount} uebersprungen");
        } else {
            $this->info("{$syncedCount} User(s) erfolgreich synchronisiert, {$skippedCount} uebersprungen");
        }

        return Command::SUCCESS;
    }
}
