<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Ändert den Unique-Constraint, damit ein Account (z.B. GitHub Repository)
     * mit mehreren verschiedenen Objekten verknüpft werden kann.
     * Der Constraint verhindert nur doppelte Verknüpfungen zwischen demselben
     * Account und demselben Objekt.
     */
    public function up(): void
    {
        $tableName = 'integration_account_links';
        $connection = Schema::getConnection();
        $databaseName = $connection->getDatabaseName();
        
        // Prüfe ob der alte Constraint existiert
        $constraintExists = DB::select(
            "SELECT COUNT(*) as count FROM information_schema.table_constraints 
             WHERE table_schema = ? AND table_name = ? AND constraint_name = ?",
            [$databaseName, $tableName, 'ial_account_uniq']
        );
        
        if ($constraintExists[0]->count > 0) {
            // Entferne den alten Unique-Constraint
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropUnique('ial_account_uniq');
            });
        }
        
        // Prüfe ob der neue Constraint bereits existiert
        $newConstraintExists = DB::select(
            "SELECT COUNT(*) as count FROM information_schema.table_constraints 
             WHERE table_schema = ? AND table_name = ? AND constraint_name = ?",
            [$databaseName, $tableName, 'ial_account_linkable_uniq']
        );
        
        if ($newConstraintExists[0]->count == 0) {
            // Erstelle neuen Unique-Constraint: Account + Linkable muss eindeutig sein
            Schema::table($tableName, function (Blueprint $table) {
                $table->unique(
                    ['account_type', 'account_id', 'linkable_type', 'linkable_id'],
                    'ial_account_linkable_uniq'
                );
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = 'integration_account_links';
        $connection = Schema::getConnection();
        $databaseName = $connection->getDatabaseName();
        
        // Prüfe ob der neue Constraint existiert
        $newConstraintExists = DB::select(
            "SELECT COUNT(*) as count FROM information_schema.table_constraints 
             WHERE table_schema = ? AND table_name = ? AND constraint_name = ?",
            [$databaseName, $tableName, 'ial_account_linkable_uniq']
        );
        
        if ($newConstraintExists[0]->count > 0) {
            // Entferne den neuen Constraint
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropUnique('ial_account_linkable_uniq');
            });
        }
        
        // Prüfe ob der alte Constraint bereits existiert
        $oldConstraintExists = DB::select(
            "SELECT COUNT(*) as count FROM information_schema.table_constraints 
             WHERE table_schema = ? AND table_name = ? AND constraint_name = ?",
            [$databaseName, $tableName, 'ial_account_uniq']
        );
        
        if ($oldConstraintExists[0]->count == 0) {
            // Stelle den alten Constraint wieder her
            Schema::table($tableName, function (Blueprint $table) {
                $table->unique(['account_type', 'account_id'], 'ial_account_uniq');
            });
        }
    }
};
