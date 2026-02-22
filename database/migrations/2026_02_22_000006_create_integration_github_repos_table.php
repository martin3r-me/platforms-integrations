<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_github_repos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')
                ->constrained('integration_connections')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('github_repo_id');
            $table->string('full_name');
            $table->string('name');
            $table->string('owner');
            $table->boolean('is_private')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indizes
            $table->index(['connection_id'], 'igr_connection_id_idx');
            $table->index(['github_repo_id'], 'igr_repo_id_idx');
            $table->index(['is_active'], 'igr_is_active_idx');
            $table->unique(['connection_id', 'github_repo_id'], 'igr_connection_repo_uniq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_github_repos');
    }
};
