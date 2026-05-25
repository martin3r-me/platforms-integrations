<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Seed-Migration für die Google Search Console Integration
 *
 * Registriert Google Search Console als verfügbare Integration mit OAuth2-Unterstützung.
 * Read-Only-Zugriff auf Sites, Search Analytics, Sitemaps und URL Inspection.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('integrations')->updateOrInsert(
            ['key' => 'google_search_console'],
            [
                'name' => 'Google Search Console',
                'is_enabled' => true,
                'supported_auth_schemes' => json_encode(['oauth2'], JSON_THROW_ON_ERROR),
                'meta' => json_encode([
                    'description' => 'Google Search Console Integration für Search Analytics, Sitemaps und URL Inspection. Read-Only-Zugriff über Google OAuth2.',
                    'icon' => 'heroicon-o-magnifying-glass-circle',
                ], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        Log::info('Google Search Console integration seeded successfully');
    }

    public function down(): void
    {
        DB::table('integrations')->where('key', 'google_search_console')->delete();

        Log::info('Google Search Console integration removed');
    }
};
