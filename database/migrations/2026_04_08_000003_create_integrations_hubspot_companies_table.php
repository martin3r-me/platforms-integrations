<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = 'integrations_hubspot_companies';

        if (!Schema::hasTable($tableName)) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('external_id'); // HubSpot Company ID
                $table->string('name')->nullable();
                $table->string('domain')->nullable();
                $table->string('industry')->nullable();
                $table->string('phone')->nullable();
                $table->string('city')->nullable();
                $table->string('country')->nullable();
                $table->string('owner_id')->nullable();
                $table->timestamp('hubspot_created_at')->nullable();
                $table->timestamp('hubspot_updated_at')->nullable();
                $table->json('metadata')->nullable();

                $table->foreignId('integration_connection_id')->nullable();
                $table->foreign('integration_connection_id', 'ihco_conn_id_fk')
                    ->references('id')
                    ->on('integration_connections')
                    ->onDelete('cascade');

                $table->foreignId('user_id')
                    ->constrained('users')
                    ->onDelete('cascade');

                $table->timestamps();

                $table->index(['user_id'], 'ihco_user_id_idx');
                $table->index(['integration_connection_id'], 'ihco_connection_id_idx');
                $table->index(['external_id'], 'ihco_external_id_idx');
                $table->index(['domain'], 'ihco_domain_idx');
                $table->unique(['external_id', 'user_id'], 'ihco_external_user_uniq');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations_hubspot_companies');
    }
};
