<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Seed-Migration für die HubSpot Integration
 *
 * Registriert HubSpot als verfügbare Integration mit API-Key-Auth (Private App Token).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('integrations')->updateOrInsert(
            ['key' => 'hubspot'],
            [
                'name' => 'HubSpot',
                'is_enabled' => true,
                'supported_auth_schemes' => json_encode(['api_key'], JSON_THROW_ON_ERROR),
                'meta' => json_encode([
                    'description' => 'HubSpot CRM Integration via Private App Token. Synchronisiert Contacts, Companies, Deals und Engagements (Notes, Calls, Emails, Meetings, Tasks).',
                    'icon' => 'heroicon-o-users',
                ], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        Log::info('HubSpot integration seeded successfully');
    }

    public function down(): void
    {
        DB::table('integrations')->where('key', 'hubspot')->delete();

        Log::info('HubSpot integration removed');
    }
};
