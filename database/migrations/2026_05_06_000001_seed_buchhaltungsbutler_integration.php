<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('integrations')->updateOrInsert(
            ['key' => 'buchhaltungsbutler'],
            [
                'name' => 'BuchhaltungsButler',
                'is_enabled' => true,
                'supported_auth_schemes' => json_encode(['api_key'], JSON_THROW_ON_ERROR),
                'meta' => json_encode([
                    'description' => 'BuchhaltungsButler Integration für Rechnungen, Angebote und Gutschriften (Entwurf-Erstellung) sowie Debitorenverwaltung. HTTP Basic Auth (api_client + api_secret) plus kundenspezifischer api_key.',
                    'icon' => 'heroicon-o-banknotes',
                ], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        Log::info('BuchhaltungsButler integration seeded successfully');
    }

    public function down(): void
    {
        DB::table('integrations')->where('key', 'buchhaltungsbutler')->delete();

        Log::info('BuchhaltungsButler integration removed');
    }
};
