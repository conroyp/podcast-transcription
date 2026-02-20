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
            $table->enum('embedding_status', ['pending', 'processing', 'completed', 'failed', 'partial'])->default('pending')->after('diarization_status');
            $table->index(['embedding_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('episodes', function (Blueprint $table) {
            $table->dropIndex(['embedding_status']);
            $table->dropColumn('embedding_status');
        });
    }
};
