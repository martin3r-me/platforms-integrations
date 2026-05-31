<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Seed-Migration für die RingCentral Integration
 *
 * Registriert RingCentral als verfügbare Integration mit OAuth2-Unterstützung.
 * Telefonie, Call Logs, Extensions — parallel zu Sipgate.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('integrations')->updateOrInsert(
            ['key' => 'ringcentral'],
            [
                'name' => 'RingCentral',
                'is_enabled' => true,
                'supported_auth_schemes' => json_encode(['oauth2'], JSON_THROW_ON_ERROR),
                'meta' => json_encode([
                    'description' => 'RingCentral Telefonie Integration für Call Logs, Extensions und Account-Informationen. Verbindung über OAuth2.',
                    'icon' => 'heroicon-o-phone',
                    'website' => 'https://www.ringcentral.com',
                    'documentation' => 'https://developers.ringcentral.com',
                    'features' => [
                        'call_log' => 'Anrufhistorie und Call Logs',
                        'active_calls' => 'Aktive Anrufe einsehen',
                        'extensions' => 'Extensions/Nebenstellen auflisten',
                        'account_info' => 'Account-Informationen abrufen',
                        'user_info' => 'Benutzerinformationen abrufen',
                    ],
                    'required_scopes' => [
                        'ReadCallLog',
                        'ReadAccounts',
                    ],
                ], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        Log::info('RingCentral integration seeded successfully');
    }

    public function down(): void
    {
        DB::table('integrations')->where('key', 'ringcentral')->delete();

        Log::info('RingCentral integration removed');
    }
};
