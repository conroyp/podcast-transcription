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
        Schema::create('feeds', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url')->unique();
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->string('language')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_episode_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable(); // Store RSS feed metadata
            $table->timestamps();

            $table->index(['is_active', 'last_checked_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feeds');
    }
};
