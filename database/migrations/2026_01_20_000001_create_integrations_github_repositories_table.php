<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = 'integrations_github_repositories';
        
        if (!Schema::hasTable($tableName)) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->string('uuid')->unique();
                $table->string('external_id'); // GitHub Repository ID
                $table->string('full_name'); // z.B. "username/repo-name"
                $table->string('name'); // Repository Name
                $table->string('owner'); // Repository Owner (username)
                $table->text('description')->nullable();
                $table->string('url'); // HTML URL
                $table->string('clone_url')->nullable(); // Git Clone URL
                $table->string('default_branch')->nullable();
                $table->boolean('is_private')->default(false);
                $table->boolean('is_fork')->default(false);
                $table->boolean('is_archived')->default(false);
                $table->string('language')->nullable(); // Primary language
                $table->integer('stars_count')->default(0);
                $table->integer('forks_count')->default(0);
                $table->integer('open_issues_count')->default(0);
                $table->timestamp('github_created_at')->nullable();
                $table->timestamp('github_updated_at')->nullable();
                $table->timestamp('github_pushed_at')->nullable();
                $table->json('metadata')->nullable(); // Weitere GitHub-Daten
                $table->foreignId('integration_connection_id')
                    ->nullable();
                
                $table->foreign('integration_connection_id', 'ighr_conn_id_fk')
                    ->references('id')
                    ->on('integration_connections')
                    ->onDelete('cascade');
                $table->foreignId('user_id')
                    ->constrained('users')
                    ->onDelete('cascade');
                $table->timestamps();
                
                // Indizes
                $table->index(['user_id'], 'ighr_user_id_idx');
                $table->index(['integration_connection_id'], 'ighr_connection_id_idx');
                $table->index(['external_id'], 'ighr_external_id_idx');
                $table->index(['full_name'], 'ighr_full_name_idx');
                $table->unique(['external_id', 'user_id'], 'ighr_external_user_uniq');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations_github_repositories');
    }
};
