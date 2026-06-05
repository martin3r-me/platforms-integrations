<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Seed-Migration für die DATEV Integration
 *
 * Registriert DATEV als verfügbare Integration mit OAuth2-Unterstützung (OpenID Connect).
 * Confidential Client mit 2-Jahres-Dauertoken (gleitend).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('integrations')->updateOrInsert(
            ['key' => 'datev'],
            [
                'name' => 'DATEV',
                'is_enabled' => true,
                'supported_auth_schemes' => json_encode(['oauth2'], JSON_THROW_ON_ERROR),
                'meta' => json_encode([
                    'description' => 'DATEV Integration für Buchhaltung, Steuerberatung und Mandantenverwaltung. Zugriff über OpenID Connect (Confidential Client).',
                    'icon' => 'heroicon-o-calculator',
                ], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        Log::info('DATEV integration seeded successfully');
    }

    public function down(): void
    {
        DB::table('integrations')->where('key', 'datev')->delete();

        Log::info('DATEV integration removed');
    }
};
