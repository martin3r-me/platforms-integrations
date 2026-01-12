<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_connection_grants', function (Blueprint $table) {
            $table->id();

            $table->foreignId('connection_id')
                ->constrained('integration_connections')
                ->cascadeOnDelete();

            // polymorph-light: team|user
            $table->string('grantee_type'); // team|user
            $table->unsignedBigInteger('grantee_id');

            // optional: feinere Rechte/Scopes
            $table->json('permissions')->nullable();

            $table->timestamps();

            $table->unique(['connection_id', 'grantee_type', 'grantee_id'], 'uniq_connection_grantee');
            $table->index(['grantee_type', 'grantee_id'], 'idx_grantee_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_connection_grants');
    }
};

