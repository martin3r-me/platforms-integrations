<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = 'integrations_hubspot_deals';

        if (!Schema::hasTable($tableName)) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('external_id'); // HubSpot Deal ID
                $table->string('dealname')->nullable();
                $table->decimal('amount', 15, 2)->nullable();
                $table->string('pipeline')->nullable();
                $table->string('dealstage')->nullable();
                $table->timestamp('close_date')->nullable();
                $table->string('owner_id')->nullable();
                $table->timestamp('hubspot_created_at')->nullable();
                $table->timestamp('hubspot_updated_at')->nullable();
                $table->json('metadata')->nullable();
                $table->json('associations')->nullable();

                $table->foreignId('integration_connection_id')->nullable();
                $table->foreign('integration_connection_id', 'ihd_conn_id_fk')
                    ->references('id')
                    ->on('integration_connections')
                    ->onDelete('cascade');

                $table->foreignId('user_id')
                    ->constrained('users')
                    ->onDelete('cascade');

                $table->timestamps();

                $table->index(['user_id'], 'ihd_user_id_idx');
                $table->index(['integration_connection_id'], 'ihd_connection_id_idx');
                $table->index(['external_id'], 'ihd_external_id_idx');
                $table->index(['dealstage'], 'ihd_dealstage_idx');
                $table->unique(['external_id', 'user_id'], 'ihd_external_user_uniq');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations_hubspot_deals');
    }
};
