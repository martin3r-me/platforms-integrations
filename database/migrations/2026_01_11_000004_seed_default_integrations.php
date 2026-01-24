<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Meta Integration (Facebook, Instagram, WhatsApp)
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

        // GitHub Integration
        DB::table('integrations')->updateOrInsert(
            ['key' => 'github'],
            [
                'name' => 'GitHub',
                'is_enabled' => true,
                'supported_auth_schemes' => json_encode(['oauth2'], JSON_THROW_ON_ERROR),
                'meta' => json_encode([
                    'description' => 'GitHub Integration für Repository-Verwaltung',
                    'icon' => 'heroicon-o-code-bracket',
                ], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        // Minimaler Bootstrap: mindestens Lexoffice als Beispiel
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
    }

    public function down(): void
    {
        DB::table('integrations')->where('key', 'meta')->delete();
        DB::table('integrations')->where('key', 'github')->delete();
        DB::table('integrations')->where('key', 'lexoffice')->delete();
    }
};

