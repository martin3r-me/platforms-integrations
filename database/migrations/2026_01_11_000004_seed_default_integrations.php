<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Minimaler Bootstrap: mindestens Lexoffice als Beispiel
        DB::table('integrations')->updateOrInsert(
            ['key' => 'lexoffice'],
            [
                'name' => 'Lexoffice',
                'is_enabled' => true,
                'supported_auth_schemes' => json_encode(['oauth2', 'api_key'], JSON_THROW_ON_ERROR),
                'meta' => json_encode([
                    'description' => 'Buchhaltung/Rechnungen (Beispiel-Integration)',
                ], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('integrations')->where('key', 'lexoffice')->delete();
    }
};

