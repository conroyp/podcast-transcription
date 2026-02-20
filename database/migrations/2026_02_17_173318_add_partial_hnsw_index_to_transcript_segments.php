<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS transcript_segments_embedding_vector_idx');
        DB::statement("
            CREATE INDEX transcript_segments_embedding_completed_idx
            ON transcript_segments USING hnsw (embedding_vector vector_cosine_ops)
            WHERE embedding_status = 'completed'
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS transcript_segments_embedding_completed_idx');
        DB::statement('
            CREATE INDEX transcript_segments_embedding_vector_idx
            ON transcript_segments USING hnsw (embedding_vector vector_cosine_ops)
        ');
    }
};
