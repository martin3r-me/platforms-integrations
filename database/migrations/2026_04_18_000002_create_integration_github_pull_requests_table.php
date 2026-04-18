<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_github_pull_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repo_id')->constrained('integration_github_repos')->cascadeOnDelete();
            $table->unsignedBigInteger('github_pr_id');
            $table->unsignedInteger('number');
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('state', 20)->default('open');
            $table->string('author_login')->nullable();
            $table->string('head_ref')->nullable();
            $table->string('base_ref')->nullable();
            $table->string('merge_commit_sha', 40)->nullable();
            $table->boolean('is_merged')->default(false);
            $table->boolean('is_draft')->default(false);
            $table->integer('additions')->nullable();
            $table->integer('deletions')->nullable();
            $table->integer('changed_files')->nullable();
            $table->integer('comments_count')->default(0);
            $table->string('url')->nullable();
            $table->timestamp('github_created_at')->nullable();
            $table->timestamp('github_updated_at')->nullable();
            $table->timestamp('merged_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['repo_id', 'github_pr_id']);
            $table->index(['repo_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_github_pull_requests');
    }
};
