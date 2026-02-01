<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = 'integrations_lexware_contacts';

        if (!Schema::hasTable($tableName)) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->string('uuid')->unique();
                $table->string('external_id'); // Lexware Contact ID
                $table->string('contact_number')->nullable(); // Kundennummer
                $table->enum('contact_type', ['customer', 'vendor', 'both'])->default('customer');
                $table->string('company_name')->nullable();
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->text('note')->nullable();
                $table->boolean('is_archived')->default(false);
                $table->timestamp('lexware_created_at')->nullable();
                $table->timestamp('lexware_updated_at')->nullable();
                $table->json('metadata')->nullable(); // Weitere Lexware-Daten (Adressen, etc.)
                $table->foreignId('integration_connection_id')
                    ->nullable();

                $table->foreign('integration_connection_id', 'ilc_conn_id_fk')
                    ->references('id')
                    ->on('integration_connections')
                    ->onDelete('cascade');
                $table->foreignId('user_id')
                    ->constrained('users')
                    ->onDelete('cascade');
                $table->timestamps();

                // Indizes
                $table->index(['user_id'], 'ilc_user_id_idx');
                $table->index(['integration_connection_id'], 'ilc_connection_id_idx');
                $table->index(['external_id'], 'ilc_external_id_idx');
                $table->index(['contact_number'], 'ilc_contact_number_idx');
                $table->index(['email'], 'ilc_email_idx');
                $table->unique(['external_id', 'user_id'], 'ilc_external_user_uniq');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations_lexware_contacts');
    }
};
