<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Seed-Migration für die Easybill Integration
 *
 * Registriert easybill als verfügbare Integration mit API-Key-Authentifizierung.
 * Easybill bietet Bearer-Token-Auth: Authorization: Bearer <api_key>.
 *
 * @see https://www.easybill.de/api/
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('integrations')->updateOrInsert(
            ['key' => 'easybill'],
            [
                'name' => 'easybill',
                'is_enabled' => true,
                'supported_auth_schemes' => json_encode(['api_key'], JSON_THROW_ON_ERROR),
                'meta' => json_encode([
                    'description' => 'easybill Integration für Rechnungen, Belege, Kunden und Positionen über die REST API (Bearer Token).',
                    'icon' => 'heroicon-o-document-text',
                ], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        Log::info('easybill integration seeded successfully');
    }

    public function down(): void
    {
        DB::table('integrations')->where('key', 'easybill')->delete();

        Log::info('easybill integration removed');
    }
};
