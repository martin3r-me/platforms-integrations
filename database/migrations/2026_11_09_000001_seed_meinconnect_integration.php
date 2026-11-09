<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed-Migration für die MeinConnect-Integration (CoolCooking-Boxen).
 *
 * MeinConnect hat keine offizielle API. Gelesen wird eine Gastfreigabe
 * (https://meinconnect.app/freigabe/{token}); der Link ist das Zugangsgeheimnis.
 *
 * Credentials (verschlüsselt):
 *   - share_url : vollständiger Gastfreigabe-Link
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('integrations')->updateOrInsert(
            ['key' => 'meinconnect'],
            [
                'name' => 'MeinConnect',
                'is_enabled' => true,
                'supported_auth_schemes' => json_encode(['api_key'], JSON_THROW_ON_ERROR),
                'meta' => json_encode([
                    'description' => 'MeinConnect (CoolCooking): Status freigegebener Geräte (Modus, Temperaturen, Tür, '
                        . 'Fehler, Heizzeiten) über einen Gastfreigabe-Link. Lesend, keine offizielle API.',
                    'icon' => 'heroicon-o-fire',
                ], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('integrations')->where('key', 'meinconnect')->delete();
    }
};
