<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Konten-Mappings innerhalb einer Lexoffice↔DATEV-Bridge.
 *
 * Polymorph über `mapping_type`:
 * - contact: Lexoffice-Kontakt-ID → Personenkonto (Debitor/Kreditor)
 * - posting_category: Lexoffice-Posting-Category → Sachkonto
 * - cost_center: Lexoffice-Kontext (Projekt/Tag/etc.) → DATEV-Kostenstelle
 *
 * Eindeutigkeit pro Bridge + Typ + Quell-Key. DATEV-Mandant und beide
 * Connections kommen über die Bridge.
 *
 * Idempotent (analog Bridges-Migration): Tabelle + jeder Foreign-Key
 * werden einzeln geprüft. Kurze FK-Namen (≤64 Zeichen).
 *
 * @see 2026_06_13_000000_create_integrations_lexoffice_datev_bridges_table.php
 */
return new class extends Migration
{
    private const TABLE = 'integrations_datev_account_mappings';

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            Schema::create(self::TABLE, function (Blueprint $table) {
                $table->id();
                $table->string('uuid')->unique();

                $table->unsignedBigInteger('bridge_id');

                $table->enum('mapping_type', ['contact', 'posting_category', 'cost_center']);

                $table->string('source_key');
                $table->string('source_label')->nullable();

                $table->string('datev_account_number');

                $table->enum('account_kind', ['debitor', 'kreditor', 'sachkonto', 'kostenstelle']);

                $table->string('cost_center_1')->nullable();
                $table->string('cost_center_2')->nullable();
                $table->string('tax_key')->nullable();

                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();

                $table->timestamps();

                $table->unique(
                    ['bridge_id', 'mapping_type', 'source_key'],
                    'idam_bridge_type_src_uniq'
                );

                $table->index(['bridge_id', 'mapping_type'], 'idam_bridge_type_idx');
                $table->index(['source_key'], 'idam_source_key_idx');
            });
        }

        $this->ensureForeignKey('idam_bridge_fk', 'bridge_id', 'integrations_lexoffice_datev_bridges', 'id', 'cascade');
    }

    public function down(): void
    {
        Schema::dropIfExists(self::TABLE);
    }

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
