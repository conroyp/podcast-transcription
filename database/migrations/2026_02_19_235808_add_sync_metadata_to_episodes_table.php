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
        Schema::table('episodes', function (Blueprint $table) {
            if (! Schema::hasColumn('episodes', 'last_synced_at')) {
                $table->timestamp('last_synced_at')->nullable()->after('sync_progress');
                $table->index('last_synced_at');
            }

            if (! Schema::hasColumn('episodes', 'sync_hash')) {
                $table->string('sync_hash')->nullable()->after('last_synced_at');
            }

            if (! Schema::hasColumn('episodes', 'sync_attempts')) {
                $table->integer('sync_attempts')->default(0)->after('sync_hash');
            }

            if (! Schema::hasColumn('episodes', 'sync_error')) {
                $table->text('sync_error')->nullable()->after('sync_attempts');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('episodes', function (Blueprint $table) {
            $table->dropColumnIfExists('last_synced_at');
            $table->dropColumnIfExists('sync_hash');
            $table->dropColumnIfExists('sync_attempts');
            $table->dropColumnIfExists('sync_error');
        });
    }
};
