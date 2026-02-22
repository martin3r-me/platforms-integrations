<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Seed-Migration für die DataForSEO Integration
 *
 * Registriert DataForSEO als verfügbare Integration mit Basic-Auth-Unterstützung.
 * Fix für #360: Ohne diesen Seed-Eintrag schlug das Speichern von Credentials fehl
 * mit "No query results for model [Integration]".
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('integrations')->updateOrInsert(
            ['key' => 'dataforseo'],
            [
                'name' => 'DataForSEO',
                'is_enabled' => true,
                'has_resources' => false,
                'supported_auth_schemes' => json_encode(['basic'], JSON_THROW_ON_ERROR),
                'meta' => json_encode([
                    'description' => 'DataForSEO Integration für SEO-Keyword-Daten (Suchvolumen, verwandte Keywords, Keyword-Vorschläge). Verbindung erfolgt über API-Credentials (Login/Password).',
                    'icon' => 'heroicon-o-magnifying-glass',
                ], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        Log::info('DataForSEO integration seeded successfully');
    }

    public function down(): void
    {
        DB::table('integrations')->where('key', 'dataforseo')->delete();

        Log::info('DataForSEO integration removed');
    }
};
