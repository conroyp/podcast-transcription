<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Add tsvector column
        DB::statement('ALTER TABLE transcript_segments ADD COLUMN search_vector tsvector');

        // Create GIN index for full-text search
        DB::statement('CREATE INDEX transcript_segments_search_vector_idx ON transcript_segments USING GIN (search_vector)');

        // Populate existing rows
        DB::statement("UPDATE transcript_segments SET search_vector = to_tsvector('english', COALESCE(text, ''))");

        // Create trigger to keep search_vector in sync on INSERT/UPDATE
        DB::statement("
            CREATE OR REPLACE FUNCTION transcript_segments_search_vector_update() RETURNS trigger AS $$
            BEGIN
                NEW.search_vector := to_tsvector('english', COALESCE(NEW.text, ''));
                RETURN NEW;
            END
            $$ LANGUAGE plpgsql;
        ");

        DB::statement('
            CREATE TRIGGER transcript_segments_search_vector_trigger
            BEFORE INSERT OR UPDATE OF text ON transcript_segments
            FOR EACH ROW
            EXECUTE FUNCTION transcript_segments_search_vector_update();
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS transcript_segments_search_vector_trigger ON transcript_segments');
        DB::statement('DROP FUNCTION IF EXISTS transcript_segments_search_vector_update()');
        DB::statement('DROP INDEX IF EXISTS transcript_segments_search_vector_idx');
        DB::statement('ALTER TABLE transcript_segments DROP COLUMN IF EXISTS search_vector');
    }
};
