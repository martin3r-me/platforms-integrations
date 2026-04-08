<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = 'integrations_hubspot_contacts';

        if (!Schema::hasTable($tableName)) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('external_id'); // HubSpot Contact ID (vid)
                $table->string('email')->nullable();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('phone')->nullable();
                $table->string('company')->nullable();
                $table->string('lifecycle_stage')->nullable();
                $table->string('lead_status')->nullable();
                $table->string('owner_id')->nullable();
                $table->timestamp('hubspot_created_at')->nullable();
                $table->timestamp('hubspot_updated_at')->nullable();
                $table->json('metadata')->nullable();

                $table->foreignId('integration_connection_id')->nullable();
                $table->foreign('integration_connection_id', 'ihc_conn_id_fk')
                    ->references('id')
                    ->on('integration_connections')
                    ->onDelete('cascade');

                $table->foreignId('user_id')
                    ->constrained('users')
                    ->onDelete('cascade');

                $table->timestamps();

                $table->index(['user_id'], 'ihc_user_id_idx');
                $table->index(['integration_connection_id'], 'ihc_connection_id_idx');
                $table->index(['external_id'], 'ihc_external_id_idx');
                $table->index(['email'], 'ihc_email_idx');
                $table->unique(['external_id', 'user_id'], 'ihc_external_user_uniq');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations_hubspot_contacts');
    }
};
