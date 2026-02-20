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
        Schema::create('speakers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('podcast_id')->constrained()->onDelete('cascade');

            $table->string('name')->nullable(); // User-assigned name
            $table->string('identifier'); // System-generated identifier (SPEAKER_00, etc)
            $table->text('description')->nullable();
            $table->string('voice_characteristics')->nullable(); // JSON or text description

            $table->boolean('is_host')->default(false);
            $table->boolean('is_verified')->default(false); // Manual verification by user

            $table->timestamps();

            $table->unique(['podcast_id', 'identifier']);
            $table->index(['podcast_id', 'is_host']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('speakers');
    }
};
