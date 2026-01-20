<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = 'integrations_meta_business_accounts';
        
        if (!Schema::hasTable($tableName)) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->string('uuid')->unique();
                $table->string('external_id'); // Meta Business Account ID
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('timezone')->nullable();
                $table->string('vertical')->nullable(); // Business vertical (z.B. "RETAIL", "E_COMMERCE")
                $table->json('metadata')->nullable(); // Weitere Meta-Daten (z.B. primary_page_id, etc.)
                $table->foreignId('integration_connection_id')
                    ->constrained('integration_connections')
                    ->onDelete('cascade');
                $table->foreignId('user_id')
                    ->constrained('users')
                    ->onDelete('cascade');
                $table->timestamps();
                
                // Indizes
                $table->index(['user_id'], 'imba_user_id_idx');
                $table->index(['integration_connection_id'], 'imba_connection_id_idx');
                $table->index(['external_id'], 'imba_external_id_idx');
                $table->unique(['external_id', 'user_id'], 'imba_external_user_uniq');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations_meta_business_accounts');
    }
};
