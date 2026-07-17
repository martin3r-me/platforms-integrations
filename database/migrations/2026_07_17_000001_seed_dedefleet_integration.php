<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Seed-Migration für die DedeFleet Integration (Ortung & Tourenplanung).
 *
 * DedeFleet stellt eine REST-API (RPC-Stil, /{Resource}/{Action}) unter einer
 * festen Base-URL bereit: https://ortung.dedefleet.de/data/api/2
 * Authentifizierung per Bearer-Dauertoken (Typ "Api Vollzugriff", "Permanent"),
 * erzeugt im Portal unter Systemeinstellungen → Benutzer.
 *
 * Credentials (im generischen credentialsJson der Connection):
 *   - api_key : Bearer-Dauertoken
 *
 * Auth-Header: Authorization: Bearer <api_key>
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('integrations')->updateOrInsert(
            ['key' => 'dedefleet'],
            [
                'name' => 'DedeFleet',
                'is_enabled' => true,
                'supported_auth_schemes' => json_encode(['api_key'], JSON_THROW_ON_ERROR),
                'meta' => json_encode([
                    'description' => 'DedeFleet Ortung & Tourenplanung — REST-API (Bearer-Dauertoken). '
                        . 'Aufträge, Touren, Kunden, Mitarbeiter, Standorte, Fahrzeugprofile & GPS-Ortung. '
                        . 'Base-URL: https://ortung.dedefleet.de/data/api/2 (fest, Mandant via Token).',
                    'icon' => 'heroicon-o-truck',
                ], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        Log::info('DedeFleet integration seeded successfully');
    }

    public function down(): void
    {
        DB::table('integrations')->where('key', 'dedefleet')->delete();

        Log::info('DedeFleet integration removed');
    }
};
