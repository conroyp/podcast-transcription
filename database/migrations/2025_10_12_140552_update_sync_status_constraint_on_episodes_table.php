<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the old constraint
        DB::statement('ALTER TABLE episodes DROP CONSTRAINT IF EXISTS episodes_sync_status_check');

        // Add new constraint with updated values
        DB::statement("
            ALTER TABLE episodes
            ADD CONSTRAINT episodes_sync_status_check
            CHECK (sync_status IN ('idle', 'queued', 'syncing', 'completed', 'failed', 'pending', 'synced', 'deleted'))
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the new constraint
        DB::statement('ALTER TABLE episodes DROP CONSTRAINT IF EXISTS episodes_sync_status_check');

        // Restore old constraint
        DB::statement("
            ALTER TABLE episodes
            ADD CONSTRAINT episodes_sync_status_check
            CHECK (sync_status IN ('pending', 'synced', 'failed', 'deleted'))
        ");
    }
};
