<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('podcasts', function (Blueprint $table) {
            $table->timestamp('last_episode_at')->nullable()->after('last_checked_at');
            $table->json('metadata')->nullable()->after('last_episode_at');
        });

        // First update any null rss_url to empty string, then make it required and unique
        DB::table('podcasts')->whereNull('rss_url')->update(['rss_url' => '']);

        Schema::table('podcasts', function (Blueprint $table) {
            $table->string('rss_url')->nullable(false)->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('podcasts', function (Blueprint $table) {
            $table->dropColumn(['last_episode_at', 'metadata']);
            $table->dropUnique(['rss_url']);
            $table->string('rss_url')->nullable()->change();
        });
    }
};
