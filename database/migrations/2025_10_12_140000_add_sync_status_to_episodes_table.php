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
        if (! Schema::hasColumn('episodes', 'sync_status')) {
            Schema::table('episodes', function (Blueprint $table) {
                $table->string('sync_status')->default('idle')->after('diarization_status');
                $table->index(['sync_status']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('episodes', 'sync_status')) {
            Schema::table('episodes', function (Blueprint $table) {
                $table->dropColumn('sync_status');
            });
        }
    }
};
