<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('integration_account_links', function (Blueprint $table) {
            $table->id();
            $table->string('linkable_type'); // z.B. 'BrandsBrand'
            $table->unsignedBigInteger('linkable_id');
            $table->string('account_type'); // 'facebook_page' oder 'instagram_account'
            $table->unsignedBigInteger('account_id'); // ID der Facebook Page oder Instagram Account
            $table->foreignId('team_id')->nullable()->constrained('teams')->onDelete('cascade');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            // Eindeutige Kombination: Jeder Account kann nur einmal verknüpft werden
            $table->unique(['account_type', 'account_id'], 'ial_account_uniq');
            
            // Polymorphic Index
            $table->index(['linkable_type', 'linkable_id'], 'ial_linkable_idx');
            
            // Weitere Indizes
            $table->index(['account_type', 'account_id'], 'ial_account_type_id_idx');
            $table->index(['team_id'], 'ial_team_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integration_account_links');
    }
};
