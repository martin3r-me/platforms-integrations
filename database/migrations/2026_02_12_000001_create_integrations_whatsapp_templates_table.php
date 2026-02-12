<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = 'integrations_whatsapp_templates';

        if (!Schema::hasTable($tableName)) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->string('uuid')->unique();
                $table->string('external_id'); // Template ID von Meta
                $table->string('name');
                $table->string('language', 10); // z.B. de, en_US
                $table->string('status')->default('PENDING'); // APPROVED, PENDING, REJECTED
                $table->string('category')->nullable(); // MARKETING, UTILITY, AUTHENTICATION
                $table->json('components')->nullable(); // Header, Body, Footer, Buttons
                $table->json('metadata')->nullable(); // Weitere Meta-Daten
                $table->foreignId('whatsapp_account_id')->constrained('integrations_whatsapp_accounts')->onDelete('cascade');
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->timestamps();

                $table->index(['whatsapp_account_id'], 'iwt_whatsapp_account_id_idx');
                $table->index(['user_id'], 'iwt_user_id_idx');
                $table->index(['external_id'], 'iwt_external_id_idx');
                $table->index(['status'], 'iwt_status_idx');
                $table->unique(['external_id', 'language', 'whatsapp_account_id'], 'iwt_external_lang_account_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations_whatsapp_templates');
    }
};
