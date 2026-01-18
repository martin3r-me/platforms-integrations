<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = 'integrations_facebook_pages';
        
        // Tabelle erstellen, falls sie nicht existiert
        if (!Schema::hasTable($tableName)) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->string('uuid')->unique();
                $table->string('external_id');
                $table->string('name');
                $table->text('description')->nullable();
                $table->text('access_token')->nullable();
                $table->text('refresh_token')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->string('token_type')->nullable()->default('Bearer');
                $table->json('scopes')->nullable();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->timestamps();
                
                // Indizes mit expliziten, kurzen Namen
                $table->index(['user_id'], 'ifp_user_id_idx');
                $table->index(['external_id'], 'ifp_external_id_idx');
                $table->index(['expires_at'], 'ifp_expires_at_idx');
                $table->unique(['external_id', 'user_id'], 'ifp_external_user_uniq');
            });
        } else {
            // Tabelle existiert bereits - nur Indizes hinzufügen, falls sie nicht existieren
            $connection = Schema::getConnection();
            $databaseName = $connection->getDatabaseName();
            
            // Indizes hinzufügen, falls nicht vorhanden
            $indexes = [
                [['user_id'], 'ifp_user_id_idx'],
                [['external_id'], 'ifp_external_id_idx'],
                [['expires_at'], 'ifp_expires_at_idx'],
            ];
            
            foreach ($indexes as [$columns, $indexName]) {
                $indexExists = DB::select(
                    "SELECT COUNT(*) as count FROM information_schema.statistics 
                     WHERE table_schema = ? AND table_name = ? AND index_name = ?",
                    [$databaseName, $tableName, $indexName]
                );
                
                if ($indexExists[0]->count == 0) {
                    Schema::table($tableName, function (Blueprint $table) use ($columns, $indexName) {
                        $table->index($columns, $indexName);
                    });
                }
            }
            
            // Unique Constraint hinzufügen, falls nicht vorhanden
            $uniqueExists = DB::select(
                "SELECT COUNT(*) as count FROM information_schema.table_constraints 
                 WHERE table_schema = ? AND table_name = ? AND constraint_name = ?",
                [$databaseName, $tableName, 'ifp_external_user_uniq']
            );
            
            if ($uniqueExists[0]->count == 0) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->unique(['external_id', 'user_id'], 'ifp_external_user_uniq');
                });
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations_facebook_pages');
    }
};
