<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_connections', function (Blueprint $table) {
            $table->id();

            $table->foreignId('integration_id')
                ->constrained('integrations')
                ->cascadeOnDelete();

            // Owner: User-zentriert (kein Team-Bezug)
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();

            $table->string('auth_scheme')->default('oauth2'); // oauth2|api_key|basic|bearer|custom
            $table->string('status')->default('draft'); // draft|active|disabled|error

            // Verschlüsselte Credentials (JSON): Tokens, API-Key, username/pass, etc.
            // WICHTIG: longText, nicht json, da verschlüsselte Werte keine gültigen JSON-Strings sind
            $table->longText('credentials')->nullable();
            $table->string('credentials_hash')->nullable(); // optional: später für Change-Detection

            $table->timestamp('last_tested_at')->nullable();
            $table->text('last_error')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Eindeutigkeit pro Integration + User
            $table->unique(['integration_id', 'owner_user_id'], 'ic_integration_user_uniq');

            $table->index(['integration_id', 'status'], 'ic_integration_status_idx');
            $table->index(['owner_user_id'], 'ic_owner_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_connections');
    }
};
