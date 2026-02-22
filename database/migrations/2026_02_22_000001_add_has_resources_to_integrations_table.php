<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fügt das `has_resources` Flag zur `integrations` Tabelle hinzu.
 *
 * has_resources = true  → Die Integration hat Kinder-Ressourcen, die granular geteilt werden können
 *                         (z.B. Meta → Instagram Accounts, Facebook Pages / GitHub → Repos)
 * has_resources = false → Die Connection selbst ist die Ressource (z.B. LexOffice, DataForSEO)
 *
 * Dieses Flag ist die Grundlage für das spätere Ressourcen-Sharing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->boolean('has_resources')->default(false)->after('is_enabled');
        });

        // Initiale Werte setzen
        DB::table('integrations')->where('key', 'meta')->update(['has_resources' => true]);
        DB::table('integrations')->where('key', 'github')->update(['has_resources' => true]);
        // lexoffice, dataforseo, sipgate bleiben bei default false
    }

    public function down(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            $table->dropColumn('has_resources');
        });
    }
};
