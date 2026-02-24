<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop unique constraint if it exists (defensive check)
        $databaseName = DB::getDatabaseName();
        $indexExists = DB::select(
            'SELECT COUNT(*) as count FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$databaseName, 'integration_connections', 'ic_integration_user_uniq']
        );

        if ($indexExists[0]->count > 0) {
            Schema::table('integration_connections', function (Blueprint $table) {
                $table->dropUnique('ic_integration_user_uniq');
            });
        }

        Schema::table('integration_connections', function (Blueprint $table) {
            // Add name and is_default columns
            $table->string('name')->nullable()->after('owner_user_id');
            $table->boolean('is_default')->default(false)->after('name');

            // New composite index for default-connection lookups
            $table->index(['integration_id', 'owner_user_id', 'is_default'], 'ic_integration_user_default_idx');
        });

        // Data migration: mark all existing connections as default and set name from integration
        DB::table('integration_connections')
            ->whereNull('deleted_at')
            ->update(['is_default' => true]);

        // Set name from integration name for existing connections
        DB::table('integration_connections as ic')
            ->join('integrations as i', 'ic.integration_id', '=', 'i.id')
            ->whereNull('ic.deleted_at')
            ->whereNull('ic.name')
            ->update(['ic.name' => DB::raw('i.name')]);
    }

    public function down(): void
    {
        Schema::table('integration_connections', function (Blueprint $table) {
            $table->dropIndex('ic_integration_user_default_idx');
            $table->dropColumn(['name', 'is_default']);

            // Restore unique constraint
            $table->unique(['integration_id', 'owner_user_id'], 'ic_integration_user_uniq');
        });
    }
};
