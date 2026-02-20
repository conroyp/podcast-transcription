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
        Schema::table('transcript_segments', function (Blueprint $table) {
            // Remove the redundant embedding JSON column
            // This field was replaced by embedding_vector (PostgreSQL vector type)
            $table->dropColumn('embedding');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transcript_segments', function (Blueprint $table) {
            // Restore the embedding JSON column if rollback is needed
            $table->json('embedding')->nullable()->comment('Legacy JSON embedding storage - replaced by embedding_vector');
        });
    }
};
