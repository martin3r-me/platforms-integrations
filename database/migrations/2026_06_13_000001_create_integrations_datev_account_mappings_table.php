<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
 * @see 2026_06_13_000000_create_integrations_lexoffice_datev_bridges_table.php
 */
return new class extends Migration
{
    public function up(): void
    {
        $tableName = 'integrations_datev_account_mappings';

        if (Schema::hasTable($tableName)) {
            return;
        }

        Schema::create($tableName, function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();

            $table->foreignId('bridge_id')
                ->constrained('integrations_lexoffice_datev_bridges')
                ->cascadeOnDelete();

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

    public function down(): void
    {
        Schema::dropIfExists('integrations_datev_account_mappings');
    }
};
