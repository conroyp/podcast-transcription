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
        Schema::table('transcript_segments', function (Blueprint $table) {
            // Add embedding status tracking
            $table->enum('embedding_status', ['pending', 'processing', 'completed', 'failed'])
                ->default('pending')
                ->index();

            // Add chunk grouping for better organization
            $table->string('chunk_group')->nullable()->index();

            // Add metadata for embeddings
            $table->json('embedding_metadata')->nullable();

            // Add processing timestamps
            $table->timestamp('embedding_created_at')->nullable();
        });

        // Add vector column for embeddings (1536 dimensions for OpenAI text-embedding-3-small)
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE transcript_segments ADD COLUMN embedding_vector vector(1536)');

            // Create vector index for similarity search
            DB::statement('CREATE INDEX IF NOT EXISTS transcript_segments_embedding_vector_idx ON transcript_segments USING hnsw (embedding_vector vector_cosine_ops)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transcript_segments', function (Blueprint $table) {
            $table->dropColumn('embedding_status');
            $table->dropColumn('chunk_group');
            $table->dropColumn('embedding_metadata');
            $table->dropColumn('embedding_created_at');
        });

        DB::statement('DROP INDEX IF EXISTS transcript_segments_embedding_vector_idx');
        DB::statement('ALTER TABLE transcript_segments DROP COLUMN embedding_vector');
    }
};
