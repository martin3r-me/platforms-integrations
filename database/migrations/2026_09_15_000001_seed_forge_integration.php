<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Seed-Migration für die Laravel Forge Integration (API v2)
 *
 * Registriert Laravel Forge als verfügbare Integration mit API-Key-Auth.
 * Das persönliche API-Token wird pro Connection in credentials.api_key
 * gespeichert, die Standard-Organisation in credentials.organization.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('integrations')->updateOrInsert(
            ['key' => 'forge'],
            [
                'name' => 'Laravel Forge',
                'is_enabled' => true,
                'supported_auth_schemes' => json_encode(['api_key'], JSON_THROW_ON_ERROR),
                'meta' => json_encode([
                    'description' => 'Laravel Forge Integration (API v2) für Server, Sites, Deployments und '
                        . 'Server-Dienste. Verbindung über persönliches API-Token. Die API ist '
                        . 'organisationsbezogen — pro Verbindung kann eine Standard-Organisation hinterlegt werden.',
                    'icon' => 'heroicon-o-server-stack',
                    'website' => 'https://forge.laravel.com',
                    'documentation' => 'https://laravel.com/forge/docs/api-reference/introduction',
                    'token_url' => 'https://forge.laravel.com/profile/api',
                    'api_version' => 'v2',
                    'features' => [
                        'organizations' => 'Organisationen auflisten und abrufen',
                        'servers' => 'Server auflisten, abrufen und Aktionen ausführen (Reboot, Dienste neu starten)',
                        'sites' => 'Sites organisationsweit oder je Server auflisten und abrufen',
                        'deployments' => 'Deployments auflisten, auslösen und Logs abrufen',
                        'events' => 'Organisations- und Server-Events als Audit-Trail',
                        'call' => 'Generischer Zugriff auf alle 160 Endpunkte der API v2',
                    ],
                ], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        Log::info('Laravel Forge integration seeded successfully');
    }

    public function down(): void
    {
        DB::table('integrations')->where('key', 'forge')->delete();

        Log::info('Laravel Forge integration removed');
    }
};
