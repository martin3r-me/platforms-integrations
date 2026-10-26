<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Seed-Migration für die NinjaOne Integration (Public API 2.0)
 *
 * Registriert NinjaOne als verfügbare Integration. Unterstützt werden beide
 * OAuth2-Wege: client_credentials (Maschine-zu-Maschine, ohne Browser) und
 * authorization_code (Browser-Flow mit registrierter Redirect-URI).
 *
 * Pro Verbindung liegen in credentials: client_id, client_secret, region
 * (us/eu/oc/ca), scopes und — nach der ersten Anmeldung — das Token unter
 * credentials.oauth.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('integrations')->updateOrInsert(
            ['key' => 'ninja'],
            [
                'name' => 'NinjaOne',
                'is_enabled' => true,
                'supported_auth_schemes' => json_encode(['client_credentials', 'oauth2'], JSON_THROW_ON_ERROR),
                'meta' => json_encode([
                    'description' => 'NinjaOne RMM (Public API 2.0) für Geräteverwaltung, Monitoring, '
                        . 'Patch-Management und Automatisierung. Die Verbindung wird je NinjaOne-Instanz '
                        . 'angelegt — die Region (us/eu/oc/ca) bestimmt API- und OAuth-Endpunkte.',
                    'icon' => 'heroicon-o-computer-desktop',
                    'website' => 'https://www.ninjaone.com',
                    'documentation' => 'https://app.ninjarmm.com/apidocs/',
                    'specification' => 'https://app.ninjarmm.com/apidocs-beta/NinjaRMM-API-v2.yaml',
                    'api_version' => '2.0.9-draft',
                    'regions' => ['us', 'eu', 'oc', 'ca'],
                    'scopes' => [
                        'monitoring' => 'lesender Zugriff',
                        'management' => 'verändernder Zugriff',
                        'control' => 'Fernsteuerung',
                    ],
                    'features' => [
                        'devices' => 'Geräte auflisten, Details und 20 Unterressourcen abrufen (Software, Datenträger, Patches, Dienste)',
                        'organizations' => 'Organisationen und ihre Geräte abrufen',
                        'alerts' => 'Aktive Alarme flottenweit oder je Gerät',
                        'queries' => '24 flottenweite Auswertungen (Gerätegesundheit, Patch-Stand, Inventar, Virenschutz)',
                        'actions' => 'Neustart, Wartungsfenster, Patch-Scan und -Installation, Skriptlauf — jeweils mit Bestätigung',
                        'call' => 'Generischer Zugriff auf alle 315 Endpunkte der API 2.0',
                    ],
                ], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        Log::info('NinjaOne integration seeded successfully');
    }

    public function down(): void
    {
        DB::table('integrations')->where('key', 'ninja')->delete();

        Log::info('NinjaOne integration removed');
    }
};
