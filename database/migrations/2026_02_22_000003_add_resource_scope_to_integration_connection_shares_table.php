<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('integration_connection_shares', function (Blueprint $table) {
            // Ressourcen-Scope für granulare Freigaben bei has_resources=true Integrations.
            // resource_id NULL  = alle Ressourcen der Connection (Wildcard)
            // resource_id SET   = nur diese spezifische Ressource
            // resource_type     = Typ der Ressource (z.B. instagram_account, github_repo, facebook_page)
            // Bei has_resources=false wird resource_id ignoriert.
            $table->unsignedBigInteger('resource_id')
                ->nullable()
                ->after('user_id');

            $table->string('resource_type', 100)
                ->nullable()
                ->after('resource_id');

            // Unique-Constraint aktualisieren: Pro Connection nur eine Share-Regel pro Team+User+Resource
            $table->dropUnique('ics_connection_team_user_uniq');
            $table->unique(
                ['connection_id', 'team_id', 'user_id', 'resource_id', 'resource_type'],
                'ics_connection_team_user_resource_uniq'
            );

            $table->index(['resource_type', 'resource_id'], 'ics_resource_idx');
        });
    }

    public function down(): void
    {
        Schema::table('integration_connection_shares', function (Blueprint $table) {
            $table->dropIndex('ics_resource_idx');
            $table->dropUnique('ics_connection_team_user_resource_uniq');

            $table->unique(
                ['connection_id', 'team_id', 'user_id'],
                'ics_connection_team_user_uniq'
            );

            $table->dropColumn(['resource_id', 'resource_type']);
        });
    }
};
