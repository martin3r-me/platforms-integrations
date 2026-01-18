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

            // Nur User-Grants (kein Team-Bezug)
            $table->foreignId('grantee_user_id')->constrained('users')->cascadeOnDelete();

            // optional: feinere Rechte/Scopes
            $table->json('permissions')->nullable();

            $table->timestamps();

            $table->unique(['connection_id', 'grantee_user_id'], 'icg_connection_user_uniq');
            $table->index(['grantee_user_id'], 'icg_grantee_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_connection_grants');
    }
};
