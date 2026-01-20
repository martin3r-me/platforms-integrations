<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = 'integrations_whatsapp_accounts';
        $connection = Schema::getConnection();
        $databaseName = $connection->getDatabaseName();

        if (Schema::hasTable($tableName) && Schema::hasTable('integrations_meta_business_accounts')) {
            Schema::table($tableName, function (Blueprint $table) use ($databaseName) {
                // Prüfe ob Spalte bereits existiert
                $columnExists = DB::select(
                    "SELECT COUNT(*) as count FROM information_schema.columns 
                     WHERE table_schema = ? AND table_name = ? AND column_name = ?",
                    [$databaseName, $tableName, 'meta_business_account_id']
                );

                if ($columnExists[0]->count == 0) {
                    $table->foreignId('meta_business_account_id')
                        ->nullable()
                        ->after('integration_connection_id')
                        ->constrained('integrations_meta_business_accounts', 'id', 'iwa_mba_id_fk')
                        ->onDelete('set null');
                    
                    $table->index(['meta_business_account_id'], 'iwa_business_account_id_idx');
                }
            });
        }
    }

    public function down(): void
    {
        $tableName = 'integrations_whatsapp_accounts';
        $connection = Schema::getConnection();
        $databaseName = $connection->getDatabaseName();

        if (Schema::hasTable($tableName)) {
            Schema::table($tableName, function (Blueprint $table) use ($databaseName) {
                $indexExists = DB::select(
                    "SELECT COUNT(*) as count FROM information_schema.statistics 
                     WHERE table_schema = ? AND table_name = ? AND index_name = ?",
                    [$databaseName, $tableName, 'iwa_business_account_id_idx']
                );

                if ($indexExists[0]->count > 0) {
                    $table->dropIndex('iwa_business_account_id_idx');
                }

                $columnExists = DB::select(
                    "SELECT COUNT(*) as count FROM information_schema.columns 
                     WHERE table_schema = ? AND table_name = ? AND column_name = ?",
                    [$databaseName, $tableName, 'meta_business_account_id']
                );

                if ($columnExists[0]->count > 0) {
                    $table->dropForeign('iwa_mba_id_fk');
                    $table->dropColumn('meta_business_account_id');
                }
            });
        }
    }
};
