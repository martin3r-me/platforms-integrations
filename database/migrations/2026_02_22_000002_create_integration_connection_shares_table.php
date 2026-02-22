<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_connection_shares', function (Blueprint $table) {
            $table->id();

            $table->foreignId('connection_id')
                ->constrained('integration_connections')
                ->cascadeOnDelete();

            // Wildcard-Konzept:
            // team_id NULL  = gilt für alle Teams
            // user_id NULL  = gilt für alle User
            // Beides NULL   = vollständig öffentlich innerhalb des Parent-Kontexts
            $table->foreignId('team_id')
                ->nullable()
                ->constrained('teams')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->cascadeOnDelete();

            $table->timestamps();

            // Eindeutigkeit: Pro Connection nur eine Share-Regel pro Team+User Kombination
            // NULL-Werte werden von den meisten DBs als unterschiedlich behandelt,
            // daher brauchen wir einen funktionalen Index oder Application-Level Validierung
            $table->unique(['connection_id', 'team_id', 'user_id'], 'ics_connection_team_user_uniq');

            $table->index(['team_id'], 'ics_team_idx');
            $table->index(['user_id'], 'ics_user_idx');
            $table->index(['connection_id'], 'ics_connection_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_connection_shares');
    }
};
