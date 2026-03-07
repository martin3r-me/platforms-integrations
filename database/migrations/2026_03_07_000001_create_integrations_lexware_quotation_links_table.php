<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('integrations_lexware_quotation_links');
        Schema::create('integrations_lexware_quotation_links', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();

            // Polymorph: Welches Model ist verlinkt (z.B. SalesDeal, PlannerTask, etc.)
            $table->string('linkable_type');
            $table->unsignedBigInteger('linkable_id');

            // Lexoffice-Daten
            $table->string('quotation_external_id');       // Lexoffice UUID
            $table->string('quotation_number')->nullable(); // z.B. "AG-2025-001"
            $table->string('voucher_status')->nullable();   // draft, open, accepted, rejected
            $table->date('voucher_date')->nullable();
            $table->date('expiration_date')->nullable();
            $table->decimal('total_amount', 12, 2)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->string('contact_name')->nullable();     // Kundenname aus dem Angebot

            // Connection-Referenz
            $table->foreignId('integration_connection_id')
                ->nullable()
                ->constrained('integration_connections', indexName: 'ilql_connection_id_foreign')
                ->onDelete('set null');

            // Metadaten (JSON für zusätzliche Infos)
            $table->json('metadata')->nullable();

            // Multi-Tenancy & Audit
            $table->foreignId('team_id')->nullable()->constrained('teams')->onDelete('cascade');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->onDelete('set null');

            $table->timestamps();

            // Indices
            $table->index(['linkable_type', 'linkable_id'], 'ilql_linkable_idx');
            $table->index(['quotation_external_id'], 'ilql_quotation_ext_idx');
            $table->index(['team_id'], 'ilql_team_idx');

            // Ein Angebot kann nur einmal pro linkable verlinkt werden
            $table->unique(
                ['linkable_type', 'linkable_id', 'quotation_external_id'],
                'ilql_linkable_quotation_uniq'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations_lexware_quotation_links');
    }
};
