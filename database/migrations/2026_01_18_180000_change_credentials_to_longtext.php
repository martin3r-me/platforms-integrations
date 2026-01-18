<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Für MySQL/MariaDB: JSON-Spalte zu LONGTEXT ändern
        // (Verschlüsselte Werte sind keine gültigen JSON-Strings mehr)
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `integration_connections` MODIFY COLUMN `credentials` LONGTEXT NULL');
        } else {
            // Für andere Datenbanken (PostgreSQL, SQLite, etc.)
            Schema::table('integration_connections', function (Blueprint $table) {
                $table->longText('credentials')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // Rückgängig machen: LONGTEXT zu JSON
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `integration_connections` MODIFY COLUMN `credentials` JSON NULL');
        } else {
            Schema::table('integration_connections', function (Blueprint $table) {
                $table->json('credentials')->nullable()->change();
            });
        }
    }
};
