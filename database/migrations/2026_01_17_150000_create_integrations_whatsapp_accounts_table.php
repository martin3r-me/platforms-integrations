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
        
        // Tabelle erstellen, falls sie nicht existiert
        if (!Schema::hasTable($tableName)) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->string('uuid')->unique();
                $table->string('external_id')->nullable(); // WhatsApp Business Account ID
                $table->string('phone_number')->nullable();
                $table->string('phone_number_id')->nullable();
                $table->string('title');
                $table->text('description')->nullable();
                $table->boolean('active')->default(true);
                $table->text('access_token')->nullable(); // Token for this specific WABA
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->timestamps();

                $table->index(['user_id'], 'iwa_user_id_idx');
                $table->index(['external_id'], 'iwa_external_id_idx');
            });
        } else {
            // Tabelle existiert bereits - nur Indizes/Constraints hinzufügen, falls sie nicht existieren
            $connection = Schema::getConnection();
            $databaseName = $connection->getDatabaseName();
            
            // Indizes hinzufügen, falls nicht vorhanden
            $indexes = [
                [['user_id'], 'iwa_user_id_idx'],
                [['external_id'], 'iwa_external_id_idx'],
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
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations_whatsapp_accounts');
    }
};
