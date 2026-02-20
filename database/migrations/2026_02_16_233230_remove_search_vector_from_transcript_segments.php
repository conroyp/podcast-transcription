<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop trigger first (depends on function)
        DB::statement('DROP TRIGGER IF EXISTS transcript_segments_search_vector_trigger ON transcript_segments');

        // Drop the trigger function
        DB::statement('DROP FUNCTION IF EXISTS transcript_segments_search_vector_update()');

        // Drop the GIN index on search_vector
        DB::statement('DROP INDEX IF EXISTS transcript_segments_search_vector_idx');

        // Drop the column
        Schema::table('transcript_segments', function ($table) {
            $table->dropColumn('search_vector');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transcript_segments', function ($table) {
            $table->addColumn('tsvector', 'search_vector')->nullable();
        });

        DB::statement('CREATE INDEX transcript_segments_search_vector_idx ON transcript_segments USING GIN (search_vector)');

        DB::statement("
            CREATE OR REPLACE FUNCTION transcript_segments_search_vector_update() RETURNS trigger AS \$\$
            BEGIN
                NEW.search_vector := to_tsvector('english', COALESCE(NEW.text, ''));
                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        DB::statement('
            CREATE TRIGGER transcript_segments_search_vector_trigger
            BEFORE INSERT OR UPDATE ON transcript_segments
            FOR EACH ROW EXECUTE FUNCTION transcript_segments_search_vector_update();
        ');
    }
};
