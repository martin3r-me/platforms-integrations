<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integrations_time_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('team_id')->nullable();
            $table->date('date');
            $table->string('start_time', 5); // HH:MM
            $table->string('end_time', 5);   // HH:MM
            $table->unsignedInteger('duration_minutes');
            $table->unsignedBigInteger('project_id')->nullable();
            $table->string('project_name', 255)->nullable();
            $table->string('context', 255)->nullable();
            $table->text('description')->nullable();
            $table->string('type', 50)->default('work');
            $table->json('tags')->nullable();
            $table->string('source', 50)->default('manual'); // manual, bulk, api, tool
            $table->uuid('bulk_id')->nullable(); // Gruppen-ID für Bulk-Einträge
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
            $table->index('team_id');
            $table->index('date');
            $table->index('project_id');
            $table->index('bulk_id');
            $table->index(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations_time_entries');
    }
};
