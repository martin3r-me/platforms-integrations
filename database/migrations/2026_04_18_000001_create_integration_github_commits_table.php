<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_github_commits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repo_id')->constrained('integration_github_repos')->cascadeOnDelete();
            $table->string('sha', 40)->index();
            $table->text('message');
            $table->string('author_name')->nullable();
            $table->string('author_email')->nullable();
            $table->string('author_login')->nullable();
            $table->string('committer_name')->nullable();
            $table->string('committer_login')->nullable();
            $table->timestamp('committed_at')->nullable();
            $table->string('url')->nullable();
            $table->integer('additions')->nullable();
            $table->integer('deletions')->nullable();
            $table->integer('changed_files')->nullable();
            $table->timestamps();

            $table->unique(['repo_id', 'sha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_github_commits');
    }
};
