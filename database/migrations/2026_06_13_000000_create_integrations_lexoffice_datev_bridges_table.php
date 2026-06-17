<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bridge / Sync-Profil: Paarung Lexoffice-Connection ↔ DATEV-Connection + Mandant.
 *
 * Mehrere Lexoffice-Accounts können auf mehrere DATEV-Mandanten gemappt sein
 * (N×M). Jeder Bridge-Eintrag definiert eine konkrete Paarung, an die dann
 * die Konten-Mappings (integrations_datev_account_mappings.bridge_id) hängen.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tableName = 'integrations_lexoffice_datev_bridges';

        if (Schema::hasTable($tableName)) {
            return;
        }

        Schema::create($tableName, function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();

            $table->string('name');

            $table->foreignId('lexoffice_connection_id')
                ->constrained('integration_connections')
                ->cascadeOnDelete();

            $table->foreignId('datev_connection_id')
                ->constrained('integration_connections')
                ->cascadeOnDelete();

            $table->string('datev_client_id');

            $table->foreignId('owner_user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(
                ['lexoffice_connection_id', 'datev_connection_id', 'datev_client_id'],
                'ildb_pairing_uniq'
            );

            $table->index(['owner_user_id'], 'ildb_owner_idx');
            $table->index(['datev_connection_id', 'datev_client_id'], 'ildb_datev_idx');
            $table->index(['lexoffice_connection_id'], 'ildb_lex_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations_lexoffice_datev_bridges');
    }
};
