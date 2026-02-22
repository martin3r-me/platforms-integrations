<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_meta_facebook_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')
                ->constrained('integration_connections')
                ->cascadeOnDelete();
            $table->string('page_id');
            $table->string('name');
            $table->text('access_token');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indizes
            $table->index(['connection_id'], 'imfp_connection_id_idx');
            $table->index(['page_id'], 'imfp_page_id_idx');
            $table->index(['is_active'], 'imfp_is_active_idx');
            $table->unique(['connection_id', 'page_id'], 'imfp_connection_page_uniq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_meta_facebook_pages');
    }
};
