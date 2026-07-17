<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Seed-Migration für die necta.one Integration.
 *
 * necta.one (Warenwirtschaft / Catering-ERP) stellt eine Read-Only Raw-API unter
 * /rawapi/* bereit (300+ Ressourcen: products, customers, orders, suppliers,
 * invoices, …). Authentifizierung erfolgt per API-Key im Header X-Api-Key.
 * Alle Endpunkte sind pflicht-paginiert (pageNumber 1-basiert + pageSize).
 *
 * Credentials (im generischen credentialsJson der Connection):
 *   - api_key  : vom necta-Systemadmin ausgestellter API-Key
 *   - base_url : Root-URL der necta.one-Instanz (ohne /rawapi), z.B.
 *                https://firma.necta.one
 *
 * Auth-Header: X-Api-Key: <api_key>
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('integrations')->updateOrInsert(
            ['key' => 'necta'],
            [
                'name' => 'necta.one',
                'is_enabled' => true,
                'supported_auth_schemes' => json_encode(['api_key'], JSON_THROW_ON_ERROR),
                'meta' => json_encode([
                    'description' => 'necta.one Warenwirtschaft — Read-Only Raw-API (/rawapi/*, API-Key via X-Api-Key). '
                        . 'Credentials: api_key + base_url (Instanz-Root ohne /rawapi).',
                    'icon' => 'heroicon-o-cube',
                ], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        Log::info('necta.one integration seeded successfully');
    }

    public function down(): void
    {
        DB::table('integrations')->where('key', 'necta')->delete();

        Log::info('necta.one integration removed');
    }
};
