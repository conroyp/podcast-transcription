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
            $table->double('start_time')->change();
            $table->double('end_time')->change();
            $table->double('confidence')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transcript_segments', function (Blueprint $table) {
            $table->decimal('start_time', 8, 3)->change();
            $table->decimal('end_time', 8, 3)->change();
            $table->decimal('confidence', 3, 2)->nullable()->change();
        });
    }
};
