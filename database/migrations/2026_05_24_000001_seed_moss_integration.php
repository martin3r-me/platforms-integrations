<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Seed-Migration für die Moss Integration
 *
 * Registriert Moss (Spend-Management) als verfügbare Integration mit Basic-Auth-Unterstützung.
 * Moss verwendet OAuth2 Client Credentials (client_id + client_secret → Bearer Token).
 * In der DB wird auth_scheme 'basic' verwendet (client_id = login, client_secret = password).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('integrations')->updateOrInsert(
            ['key' => 'moss'],
            [
                'name' => 'Moss',
                'is_enabled' => true,
                'supported_auth_schemes' => json_encode(['basic'], JSON_THROW_ON_ERROR),
                'meta' => json_encode([
                    'description' => 'Moss Spend-Management Integration für Expenses, Suppliers, Users, Bank Accounts, Dimensions und Payment Terms. Verbindung erfolgt über Client Credentials (Client ID/Client Secret).',
                    'icon' => 'heroicon-o-banknotes',
                ], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        Log::info('Moss integration seeded successfully');
    }

    public function down(): void
    {
        DB::table('integrations')->where('key', 'moss')->delete();

        Log::info('Moss integration removed');
    }
};
