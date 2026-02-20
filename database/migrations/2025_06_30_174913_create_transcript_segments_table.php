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
        Schema::create('transcript_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('episode_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('speaker_id')->nullable(); // Will add foreign key in separate migration

            $table->decimal('start_time', 8, 3); // seconds with millisecond precision
            $table->decimal('end_time', 8, 3);
            $table->text('text');
            $table->decimal('confidence', 3, 2)->nullable(); // Whisper confidence score 0-1

            $table->integer('segment_index'); // Order within episode
            $table->integer('word_count');

            // For future embedding storage (Phase 2)
            $table->json('embedding')->nullable(); // Will store as JSON for now, migrate to vector later

            $table->timestamps();

            $table->index(['episode_id', 'segment_index']);
            $table->index(['episode_id', 'start_time']);
            $table->index(['speaker_id']);
            // Vector index will be added in Phase 2
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transcript_segments');
    }
};
