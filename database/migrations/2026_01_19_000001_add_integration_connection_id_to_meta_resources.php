<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $connection = Schema::getConnection();
        $databaseName = $connection->getDatabaseName();

        // Facebook Pages: integration_connection_id hinzufügen
        $facebookPagesTable = 'integrations_facebook_pages';
        if (Schema::hasTable($facebookPagesTable)) {
            Schema::table($facebookPagesTable, function (Blueprint $table) use ($databaseName) {
                // Prüfe ob Spalte bereits existiert
                $columnExists = DB::select(
                    "SELECT COUNT(*) as count FROM information_schema.columns 
                     WHERE table_schema = ? AND table_name = ? AND column_name = ?",
                    [$databaseName, 'integrations_facebook_pages', 'integration_connection_id']
                );

                if ($columnExists[0]->count == 0) {
                    $table->foreignId('integration_connection_id')
                        ->nullable()
                        ->after('user_id')
                        ->constrained('integration_connections', 'id', 'ifp_conn_id_fk')
                        ->onDelete('cascade');
                    
                    $table->index(['integration_connection_id'], 'ifp_connection_id_idx');
                }
            });
        }

        // Instagram Accounts: integration_connection_id hinzufügen
        $instagramAccountsTable = 'integrations_instagram_accounts';
        if (Schema::hasTable($instagramAccountsTable)) {
            Schema::table($instagramAccountsTable, function (Blueprint $table) use ($databaseName) {
                // Prüfe ob Spalte bereits existiert
                $columnExists = DB::select(
                    "SELECT COUNT(*) as count FROM information_schema.columns 
                     WHERE table_schema = ? AND table_name = ? AND column_name = ?",
                    [$databaseName, 'integrations_instagram_accounts', 'integration_connection_id']
                );

                if ($columnExists[0]->count == 0) {
                    $table->foreignId('integration_connection_id')
                        ->nullable()
                        ->after('user_id')
                        ->constrained('integration_connections', 'id', 'iia_conn_id_fk')
                        ->onDelete('cascade');
                    
                    $table->index(['integration_connection_id'], 'iia_connection_id_idx');
                }
            });
        }

        // WhatsApp Accounts: integration_connection_id hinzufügen
        $whatsappAccountsTable = 'integrations_whatsapp_accounts';
        if (Schema::hasTable($whatsappAccountsTable)) {
            Schema::table($whatsappAccountsTable, function (Blueprint $table) use ($databaseName) {
                // Prüfe ob Spalte bereits existiert
                $columnExists = DB::select(
                    "SELECT COUNT(*) as count FROM information_schema.columns 
                     WHERE table_schema = ? AND table_name = ? AND column_name = ?",
                    [$databaseName, 'integrations_whatsapp_accounts', 'integration_connection_id']
                );

                if ($columnExists[0]->count == 0) {
                    $table->foreignId('integration_connection_id')
                        ->nullable()
                        ->after('user_id')
                        ->constrained('integration_connections', 'id', 'iwa_conn_id_fk')
                        ->onDelete('cascade');
                    
                    $table->index(['integration_connection_id'], 'iwa_connection_id_idx');
                }
            });
        }

        // Bestehende Einträge mit der Meta-Connection verknüpfen
        // Finde alle Meta-Connections und verknüpfe die entsprechenden Ressourcen
        $metaConnections = DB::table('integration_connections')
            ->join('integrations', 'integration_connections.integration_id', '=', 'integrations.id')
            ->where('integrations.key', 'meta')
            ->select('integration_connections.id', 'integration_connections.owner_user_id')
            ->get();

        foreach ($metaConnections as $metaConnection) {
            // Facebook Pages verknüpfen
            DB::table('integrations_facebook_pages')
                ->where('user_id', $metaConnection->owner_user_id)
                ->whereNull('integration_connection_id')
                ->update(['integration_connection_id' => $metaConnection->id]);

            // Instagram Accounts verknüpfen
            DB::table('integrations_instagram_accounts')
                ->where('user_id', $metaConnection->owner_user_id)
                ->whereNull('integration_connection_id')
                ->update(['integration_connection_id' => $metaConnection->id]);

            // WhatsApp Accounts verknüpfen
            DB::table('integrations_whatsapp_accounts')
                ->where('user_id', $metaConnection->owner_user_id)
                ->whereNull('integration_connection_id')
                ->update(['integration_connection_id' => $metaConnection->id]);
        }
    }

    public function down(): void
    {
        // Foreign Keys und Indizes entfernen
        $connection = Schema::getConnection();
        $databaseName = $connection->getDatabaseName();

        // Facebook Pages
        if (Schema::hasTable('integrations_facebook_pages')) {
            Schema::table('integrations_facebook_pages', function (Blueprint $table) use ($databaseName) {
                $indexExists = DB::select(
                    "SELECT COUNT(*) as count FROM information_schema.statistics 
                     WHERE table_schema = ? AND table_name = ? AND index_name = ?",
                    [$databaseName, 'integrations_facebook_pages', 'ifp_connection_id_idx']
                );

                if ($indexExists[0]->count > 0) {
                    $table->dropIndex('ifp_connection_id_idx');
                }

                $columnExists = DB::select(
                    "SELECT COUNT(*) as count FROM information_schema.columns 
                     WHERE table_schema = ? AND table_name = ? AND column_name = ?",
                    [$databaseName, 'integrations_facebook_pages', 'integration_connection_id']
                );

                if ($columnExists[0]->count > 0) {
                    $table->dropForeign('ifp_conn_id_fk');
                    $table->dropColumn('integration_connection_id');
                }
            });
        }

        // Instagram Accounts
        if (Schema::hasTable('integrations_instagram_accounts')) {
            Schema::table('integrations_instagram_accounts', function (Blueprint $table) use ($databaseName) {
                $indexExists = DB::select(
                    "SELECT COUNT(*) as count FROM information_schema.statistics 
                     WHERE table_schema = ? AND table_name = ? AND index_name = ?",
                    [$databaseName, 'integrations_instagram_accounts', 'iia_connection_id_idx']
                );

                if ($indexExists[0]->count > 0) {
                    $table->dropIndex('iia_connection_id_idx');
                }

                $columnExists = DB::select(
                    "SELECT COUNT(*) as count FROM information_schema.columns 
                     WHERE table_schema = ? AND table_name = ? AND column_name = ?",
                    [$databaseName, 'integrations_instagram_accounts', 'integration_connection_id']
                );

                if ($columnExists[0]->count > 0) {
                    $table->dropForeign('iia_conn_id_fk');
                    $table->dropColumn('integration_connection_id');
                }
            });
        }

        // WhatsApp Accounts
        if (Schema::hasTable('integrations_whatsapp_accounts')) {
            Schema::table('integrations_whatsapp_accounts', function (Blueprint $table) use ($databaseName) {
                $indexExists = DB::select(
                    "SELECT COUNT(*) as count FROM information_schema.statistics 
                     WHERE table_schema = ? AND table_name = ? AND index_name = ?",
                    [$databaseName, 'integrations_whatsapp_accounts', 'iwa_connection_id_idx']
                );

                if ($indexExists[0]->count > 0) {
                    $table->dropIndex('iwa_connection_id_idx');
                }

                $columnExists = DB::select(
                    "SELECT COUNT(*) as count FROM information_schema.columns 
                     WHERE table_schema = ? AND table_name = ? AND column_name = ?",
                    [$databaseName, 'integrations_whatsapp_accounts', 'integration_connection_id']
                );

                if ($columnExists[0]->count > 0) {
                    $table->dropForeign('iwa_conn_id_fk');
                    $table->dropColumn('integration_connection_id');
                }
            });
        }
    }
};
