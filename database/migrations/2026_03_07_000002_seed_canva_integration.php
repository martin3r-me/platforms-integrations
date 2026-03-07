<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('integrations')->updateOrInsert(
            ['key' => 'canva'],
            [
                'name' => 'Canva',
                'is_enabled' => true,
                'supported_auth_schemes' => json_encode(['oauth2']),
                'meta' => json_encode([
                    'description' => 'Canva Connect API – Design-Verwaltung, Exporte, Brand Templates',
                    'icon' => 'heroicon-o-paint-brush',
                ]),
            ]
        );
    }

    public function down(): void
    {
        DB::table('integrations')->where('key', 'canva')->delete();
    }
};
