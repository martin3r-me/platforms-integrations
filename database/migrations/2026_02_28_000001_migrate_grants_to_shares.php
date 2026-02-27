<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $grants = DB::table('integration_connection_grants')->get();

        foreach ($grants as $grant) {
            DB::table('integration_connection_shares')->updateOrInsert(
                [
                    'connection_id' => $grant->connection_id,
                    'team_id' => null,
                    'user_id' => $grant->grantee_user_id,
                    'resource_id' => null,
                    'resource_type' => null,
                ],
                [
                    'created_at' => $grant->created_at,
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        // Grants-Tabelle bleibt erhalten – kein Rollback nötig.
        // Shares, die aus Grants migriert wurden, können manuell entfernt werden.
    }
};
