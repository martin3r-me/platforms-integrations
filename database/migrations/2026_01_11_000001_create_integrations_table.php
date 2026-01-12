<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // z.B. lexoffice
            $table->string('name');
            $table->boolean('is_enabled')->default(true);
            $table->json('supported_auth_schemes')->nullable(); // ["oauth2","api_key","basic","bearer"]
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations');
    }
};

