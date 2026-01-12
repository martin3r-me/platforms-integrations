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

            // Owner: genau eins von beiden (enforced application-level; unique indexes sichern Eindeutigkeit)
            $table->foreignId('owner_team_id')->nullable()->constrained('teams')->cascadeOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->cascadeOnDelete();

            $table->string('auth_scheme')->default('oauth2'); // oauth2|api_key|basic|bearer|custom
            $table->string('status')->default('draft'); // draft|active|disabled|error

            // Verschlüsselte Credentials (JSON): Tokens, API-Key, username/pass, etc.
            $table->json('credentials')->nullable();
            $table->string('credentials_hash')->nullable(); // optional: später für Change-Detection

            $table->timestamp('last_tested_at')->nullable();
            $table->text('last_error')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Eindeutigkeit pro Integration + Owner
            $table->unique(['integration_id', 'owner_team_id'], 'uniq_integration_owner_team');
            $table->unique(['integration_id', 'owner_user_id'], 'uniq_integration_owner_user');

            $table->index(['integration_id', 'status']);
            $table->index(['owner_team_id']);
            $table->index(['owner_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_connections');
    }
};

