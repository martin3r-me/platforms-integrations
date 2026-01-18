<?php

namespace Platform\Integrations\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SeedIntegrations extends Command
{
    protected $signature = 'integrations:seed';
    protected $description = 'Seed default integrations (Meta, etc.)';

    public function handle()
    {
        if (!Schema::hasTable('integrations')) {
            $this->error('Tabelle "integrations" existiert nicht. Bitte zuerst Migrationen ausführen.');
            return Command::FAILURE;
        }

        $this->info('Seeding default integrations...');

        // Meta Integration
        DB::table('integrations')->updateOrInsert(
            ['key' => 'meta'],
            [
                'name' => 'Meta (Facebook, Instagram, WhatsApp)',
                'is_enabled' => true,
                'supported_auth_schemes' => json_encode(['oauth2'], JSON_THROW_ON_ERROR),
                'meta' => json_encode([
                    'description' => 'Meta Platform Integration für Facebook Pages, Instagram Accounts und WhatsApp Business Accounts',
                    'icon' => 'heroicon-o-globe-alt',
                ], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $this->info('✅ Meta Integration angelegt/aktualisiert');

        // Lexoffice Integration (Beispiel)
        DB::table('integrations')->updateOrInsert(
            ['key' => 'lexoffice'],
            [
                'name' => 'Lexoffice',
                'is_enabled' => true,
                'supported_auth_schemes' => json_encode(['oauth2', 'api_key'], JSON_THROW_ON_ERROR),
                'meta' => json_encode([
                    'description' => 'Buchhaltung/Rechnungen (Beispiel-Integration)',
                ], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $this->info('✅ Lexoffice Integration angelegt/aktualisiert');

        $this->info('✅ Alle Integrations wurden erfolgreich angelegt!');

        return Command::SUCCESS;
    }
}
