<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bridge / Sync-Profil: Paarung Lexoffice-Connection ↔ DATEV-Connection + Mandant.
 *
 * Mehrere Lexoffice-Accounts können auf mehrere DATEV-Mandanten gemappt sein
 * (N×M). Jeder Bridge-Eintrag definiert eine konkrete Paarung, an die dann
 * die Konten-Mappings (integrations_datev_account_mappings.bridge_id) hängen.
 *
 * Idempotent: Tabelle und jeder Foreign-Key werden einzeln geprüft. Die FK-
 * Namen sind bewusst kurz (≤64 Zeichen, MySQL-Identifier-Limit) — die von
 * Laravel auto-generierten Namen wären zu lang.
 */
return new class extends Migration
{
    private const TABLE = 'integrations_lexoffice_datev_bridges';

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            Schema::create(self::TABLE, function (Blueprint $table) {
                $table->id();
                $table->string('uuid')->unique();

                $table->string('name');

                // FKs ohne ->constrained() — wir setzen sie unten manuell mit kurzen Namen.
                $table->unsignedBigInteger('lexoffice_connection_id');
                $table->unsignedBigInteger('datev_connection_id');
                $table->string('datev_client_id');
                $table->unsignedBigInteger('owner_user_id');

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

        $this->ensureForeignKey('ildb_lex_conn_fk', 'lexoffice_connection_id', 'integration_connections', 'id', 'cascade');
        $this->ensureForeignKey('ildb_datev_conn_fk', 'datev_connection_id', 'integration_connections', 'id', 'cascade');
        $this->ensureForeignKey('ildb_owner_user_fk', 'owner_user_id', 'users', 'id', 'cascade');
    }

    public function down(): void
    {
        Schema::dropIfExists(self::TABLE);
    }

    /**
     * Fügt einen Foreign Key hinzu, wenn er noch nicht existiert.
     * Prüft per information_schema, ob bereits ein FK auf der Spalte sitzt
     * (egal unter welchem Namen) — verhindert Duplikate bei wiederholten Migrationen.
     */
    private function ensureForeignKey(
        string $constraintName,
        string $column,
        string $foreignTable,
        string $foreignColumn,
        string $onDelete = 'restrict'
    ): void {
        $existing = DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL',
            [self::TABLE, $column]
        );

        if (!empty($existing)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) use ($constraintName, $column, $foreignTable, $foreignColumn, $onDelete) {
            $fk = $table->foreign($column, $constraintName)
                ->references($foreignColumn)
                ->on($foreignTable);

            if ($onDelete === 'cascade') {
                $fk->cascadeOnDelete();
            } elseif ($onDelete === 'set null') {
                $fk->nullOnDelete();
            }
        });
    }
};
