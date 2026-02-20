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
        Schema::create('episodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('podcast_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('audio_url');
            $table->string('guid')->unique(); // RSS GUID for deduplication
            $table->integer('duration_seconds')->nullable();
            $table->bigInteger('file_size_bytes')->nullable();
            $table->string('audio_format', 10)->nullable(); // mp3, wav, etc
            $table->timestamp('published_at');
            $table->string('episode_number')->nullable();
            $table->string('season_number')->nullable();

            // Processing status
            $table->enum('download_status', ['pending', 'downloading', 'completed', 'failed'])->default('pending');
            $table->enum('transcription_status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->enum('diarization_status', ['pending', 'processing', 'completed', 'failed'])->default('pending');

            // File paths
            $table->string('local_audio_path')->nullable();
            $table->json('processing_metadata')->nullable(); // Store processing details, errors, etc

            $table->timestamps();

            $table->index(['podcast_id', 'published_at']);
            $table->index(['download_status']);
            $table->index(['transcription_status']);
            $table->index(['guid']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('episodes');
    }
};
