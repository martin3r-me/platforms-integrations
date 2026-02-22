<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Seed-Migration für die Sipgate Integration
 *
 * Registriert Sipgate als verfügbare Integration mit OAuth2-Unterstützung.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('integrations')->updateOrInsert(
            ['key' => 'sipgate'],
            [
                'name' => 'Sipgate',
                'is_enabled' => true,
                'supported_auth_schemes' => json_encode(['oauth2'], JSON_THROW_ON_ERROR),
                'meta' => json_encode([
                    'description' => 'Sipgate VoIP & Telefonie Integration für Anrufe, SMS, Fax und Voicemails.',
                    'icon' => 'heroicon-o-phone',
                    'website' => 'https://www.sipgate.de',
                    'documentation' => 'https://developer.sipgate.io',
                    'features' => [
                        'calls' => 'Anrufe initiieren und empfangen',
                        'sms' => 'SMS senden und empfangen',
                        'fax' => 'Faxe senden und empfangen',
                        'voicemail' => 'Voicemail-Nachrichten abrufen',
                        'webhooks' => 'Echtzeit-Benachrichtigungen bei Anrufen',
                        'history' => 'Anrufhistorie und Statistiken',
                    ],
                    'required_scopes' => [
                        'all',
                    ],
                ], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        Log::info('Sipgate integration seeded successfully');
    }

    public function down(): void
    {
        DB::table('integrations')->where('key', 'sipgate')->delete();

        Log::info('Sipgate integration removed');
    }
};
