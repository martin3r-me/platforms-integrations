<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Seed-Migration für die Hetzner Cloud Integration (API v1)
 *
 * Registriert Hetzner Cloud als verfügbare Integration mit API-Key-Auth.
 * Das API-Token gilt immer für genau ein Projekt und wird pro Connection in
 * credentials.api_key gespeichert; ein optionales Projekt-Label liegt in
 * credentials.project.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('integrations')->updateOrInsert(
            ['key' => 'hetzner'],
            [
                'name' => 'Hetzner Cloud',
                'is_enabled' => true,
                'supported_auth_schemes' => json_encode(['api_key'], JSON_THROW_ON_ERROR),
                'meta' => json_encode([
                    'description' => 'Hetzner Cloud Integration (API v1) für Server, Volumes, Netzwerke, '
                        . 'Load Balancer, Firewalls und DNS-Zonen. Verbindung über ein projektbezogenes '
                        . 'API-Token — je Hetzner-Projekt wird eine eigene Verbindung angelegt.',
                    'icon' => 'heroicon-o-cloud',
                    'website' => 'https://www.hetzner.com/cloud',
                    'documentation' => 'https://docs.hetzner.cloud/reference/cloud',
                    'api_version' => 'v1',
                    'features' => [
                        'servers' => 'Server auflisten, abrufen, Metriken lesen und Aktionen ausführen',
                        'catalog' => 'Servertypen, Images, Standorte, Rechenzentren und Preise abrufen',
                        'storage' => 'Volumes auflisten',
                        'network' => 'Netzwerke, Firewalls, Load Balancer, Primary- und Floating-IPs',
                        'dns' => 'DNS-Zonen und RRSets auflisten',
                        'actions' => 'Asynchrone Aktionen verfolgen (GET /actions/{id})',
                        'call' => 'Generischer Zugriff auf alle 152 Endpunkte der API v1',
                    ],
                ], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        Log::info('Hetzner Cloud integration seeded successfully');
    }

    public function down(): void
    {
        DB::table('integrations')->where('key', 'hetzner')->delete();

        Log::info('Hetzner Cloud integration removed');
    }
};
