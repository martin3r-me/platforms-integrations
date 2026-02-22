<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_meta_instagram_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')
                ->constrained('integration_connections')
                ->cascadeOnDelete();
            $table->string('instagram_account_id');
            $table->string('name');
            $table->string('username');
            $table->string('profile_picture_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indizes
            $table->index(['connection_id'], 'imia_connection_id_idx');
            $table->index(['instagram_account_id'], 'imia_account_id_idx');
            $table->index(['is_active'], 'imia_is_active_idx');
            $table->unique(['connection_id', 'instagram_account_id'], 'imia_connection_account_uniq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_meta_instagram_accounts');
    }
};
