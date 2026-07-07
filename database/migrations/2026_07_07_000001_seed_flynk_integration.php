<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Seed-Migration für die FLYNK Integration.
 *
 * FLYNK (Client Portal & Agency Management) bietet eine REST-API unter /api/*
 * mit Laravel-Sanctum-Authentifizierung (Bearer-Token). Alle Ressourcen-IDs
 * sind UUIDs.
 *
 * Credentials (im generischen credentialsJson der Connection):
 *   - api_key  : Sanctum Personal-Access-Token
 *   - base_url : Root-URL der FLYNK-Instanz (ohne /api), z.B. https://app.flynk.example
 *
 * Auth-Header: Authorization: Bearer <api_key>
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('integrations')->updateOrInsert(
            ['key' => 'flynk'],
            [
                'name' => 'FLYNK',
                'is_enabled' => true,
                'supported_auth_schemes' => json_encode(['api_key'], JSON_THROW_ON_ERROR),
                'meta' => json_encode([
                    'description' => 'FLYNK Client Portal & Agency Management — REST-API (Sanctum Bearer-Token). '
                        . 'Credentials: api_key (Sanctum-Token) + base_url (Instanz-Root ohne /api).',
                    'icon' => 'heroicon-o-arrows-right-left',
                ], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        Log::info('FLYNK integration seeded successfully');
    }

    public function down(): void
    {
        DB::table('integrations')->where('key', 'flynk')->delete();

        Log::info('FLYNK integration removed');
    }
};
