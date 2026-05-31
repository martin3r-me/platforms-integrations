<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Seed-Migration für die Plausible Analytics Integration
 *
 * Registriert Plausible als verfügbare Integration mit API-Key-Unterstützung.
 * Self-hosted-fähig: Base-URL pro Connection konfigurierbar.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('integrations')->updateOrInsert(
            ['key' => 'plausible'],
            [
                'name' => 'Plausible Analytics',
                'is_enabled' => true,
                'supported_auth_schemes' => json_encode(['api_key'], JSON_THROW_ON_ERROR),
                'meta' => json_encode([
                    'description' => 'Plausible Analytics Integration für Traffic-Daten, Realtime-Besucher und Statistiken. Self-hosted-fähig über konfigurierbare Base-URL. Verbindung über API-Key.',
                    'icon' => 'heroicon-o-chart-bar',
                    'website' => 'https://plausible.io',
                    'documentation' => 'https://plausible.io/docs/stats-api',
                    'features' => [
                        'sites' => 'Sites auflisten',
                        'realtime' => 'Echtzeit-Besucher abrufen',
                        'aggregate' => 'Aggregierte Statistiken (Visits, Pageviews, etc.)',
                        'timeseries' => 'Zeitreihen-Daten für Trends',
                        'breakdown' => 'Aufschlüsselung nach Dimensionen (Source, Page, Country, etc.)',
                    ],
                ], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        Log::info('Plausible integration seeded successfully');
    }

    public function down(): void
    {
        DB::table('integrations')->where('key', 'plausible')->delete();

        Log::info('Plausible integration removed');
    }
};
