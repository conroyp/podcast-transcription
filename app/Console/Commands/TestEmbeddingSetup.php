<?php

namespace App\Console\Commands;

use App\Models\TranscriptSegment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestEmbeddingSetup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'podcast:test-embedding-setup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test pgvector setup and embedding infrastructure';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧪 Testing Embedding Infrastructure Setup');
        $this->newLine();

        // Test 1: Check pgvector extension
        $this->info('1. Checking pgvector extension...');
        try {
            $vectorExtension = DB::select("SELECT extname, extversion FROM pg_extension WHERE extname = 'vector'");
            if (empty($vectorExtension)) {
                $this->error('❌ pgvector extension not found');

                return 1;
            }
            $version = $vectorExtension[0]->extversion;
            $this->line("   ✅ pgvector {$version} is installed");
        } catch (\Exception $e) {
            $this->error("❌ Error checking pgvector: {$e->getMessage()}");

            return 1;
        }

        // Test 2: Check vector column exists
        $this->info('2. Checking embedding_vector column...');
        try {
            $columnExists = DB::select("
                SELECT column_name, data_type
                FROM information_schema.columns
                WHERE table_name = 'transcript_segments'
                AND column_name = 'embedding_vector'
            ");

            if (empty($columnExists)) {
                $this->error('❌ embedding_vector column not found');

                return 1;
            }
            $this->line('   ✅ embedding_vector column exists');
        } catch (\Exception $e) {
            $this->error("❌ Error checking vector column: {$e->getMessage()}");

            return 1;
        }

        // Test 3: Check vector index
        $this->info('3. Checking vector index...');
        try {
            $indexExists = DB::select("
                SELECT indexname, indexdef
                FROM pg_indexes
                WHERE tablename = 'transcript_segments'
                AND indexname = 'transcript_segments_embedding_vector_idx'
            ");

            if (empty($indexExists)) {
                $this->error('❌ Vector index not found');

                return 1;
            }
            $this->line('   ✅ HNSW vector index exists');
        } catch (\Exception $e) {
            $this->error("❌ Error checking vector index: {$e->getMessage()}");

            return 1;
        }

        // Test 4: Test vector operations
        $this->info('4. Testing vector operations...');
        try {
            // Create a test vector
            $testVector = array_fill(0, 1536, 0.1);
            $vectorString = '['.implode(',', $testVector).']';

            // Test vector creation and basic operations
            $result = DB::select('SELECT ?::vector as test_vector', [$vectorString]);

            if (! empty($result)) {
                $this->line('   ✅ Vector operations working (1536 dimensions)');
            } else {
                $this->error('❌ Vector creation failed');

                return 1;
            }
        } catch (\Exception $e) {
            $this->error("❌ Error testing vector operations: {$e->getMessage()}");

            return 1;
        }

        // Test 5: Check transcript segments data
        $this->info('5. Checking transcript segments data...');
        try {
            $segmentCount = TranscriptSegment::count();
            $embeddingPendingCount = TranscriptSegment::where('embedding_status', 'pending')->count();

            $this->line("   📊 Total segments: {$segmentCount}");
            $this->line("   📊 Pending embeddings: {$embeddingPendingCount}");

            if ($segmentCount > 0) {
                $this->line('   ✅ Transcript segments available for embedding');
            } else {
                $this->warn('   ⚠️  No transcript segments found');
            }
        } catch (\Exception $e) {
            $this->error("❌ Error checking transcript segments: {$e->getMessage()}");

            return 1;
        }

        // Test 6: Test similarity search capability
        $this->info('6. Testing similarity search...');
        try {
            // Test cosine similarity function
            $testVector1 = array_fill(0, 1536, 0.1);
            $testVector2 = array_fill(0, 1536, 0.2);
            $vector1String = '['.implode(',', $testVector1).']';
            $vector2String = '['.implode(',', $testVector2).']';

            $result = DB::select('
                SELECT (?::vector <=> ?::vector) as cosine_distance
            ', [$vector1String, $vector2String]);

            $distance = $result[0]->cosine_distance;
            $this->line("   ✅ Cosine similarity working (distance: {$distance})");
        } catch (\Exception $e) {
            $this->error("❌ Error testing similarity search: {$e->getMessage()}");

            return 1;
        }

        $this->newLine();
        $this->info('🎉 All embedding infrastructure tests passed!');
        $this->newLine();

        $this->info('Next steps:');
        $this->line('  • Add OpenAI API key to .env file');
        $this->line('  • Create EmbeddingService for OpenAI integration');
        $this->line('  • Build embedding generation command');
        $this->line('  • Implement similarity search API');

        return 0;
    }
}
