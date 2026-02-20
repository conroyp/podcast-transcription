<?php

namespace App\Console\Commands;

use App\Models\Episode;
use App\Models\Podcast;
use App\Models\TranscriptSegment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ResetDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'podcast:reset
                           {--force : Skip confirmation}
                           {--keep-podcasts : Keep podcast metadata}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset database and clean storage for fresh start with optimized chunks';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->error('🚨 WARNING: This will delete ALL episodes, segments, and audio files!');
        $this->newLine();

        if (! $this->option('force')) {
            if (! $this->confirm('Are you sure you want to proceed? This action cannot be undone.')) {
                $this->info('Operation cancelled.');

                return 0;
            }

            $this->newLine();
            if (! $this->confirm('Type "yes" to confirm complete data deletion')) {
                $this->info('Operation cancelled.');

                return 0;
            }
        }

        $this->info('🧹 Starting database and storage cleanup...');
        $this->newLine();

        // Step 1: Delete transcript segments
        $segmentCount = TranscriptSegment::count();
        $this->line("Deleting {$segmentCount} transcript segments...");
        TranscriptSegment::truncate();
        $this->info("✅ Deleted {$segmentCount} transcript segments");

        // Step 2: Delete episodes
        $episodeCount = Episode::count();
        $this->line("Deleting {$episodeCount} episodes...");
        Episode::truncate();
        $this->info("✅ Deleted {$episodeCount} episodes");

        // Step 3: Reset podcasts (optional)
        if (! $this->option('keep-podcasts')) {
            $podcastCount = Podcast::count();
            $this->line("Deleting {$podcastCount} podcasts...");
            Podcast::truncate();
            $this->info("✅ Deleted {$podcastCount} podcasts");
        } else {
            // Reset podcast episode counts
            Podcast::query()->update([
                'last_episode_at' => null,
                'last_checked_at' => null,
            ]);
            $this->info('✅ Reset podcast metadata');
        }

        // Step 4: Clean storage directories
        $this->cleanStorage();

        // Step 5: Reset database sequences (PostgreSQL)
        $this->resetSequences();

        $this->newLine();
        $this->info('🎉 Database and storage cleanup completed!');
        $this->info('📊 Ready for fresh podcast ingestion with optimized chunks');

        return 0;
    }

    private function cleanStorage(): void
    {
        $this->line('Cleaning storage directories...');

        // Delete audio files
        $audioPath = 'private/podcasts/audio';
        if (Storage::exists($audioPath)) {
            $files = Storage::allFiles($audioPath);
            $this->line('Deleting '.count($files).' audio files...');
            Storage::deleteDirectory($audioPath);
            Storage::makeDirectory($audioPath);
        }

        // Delete transcript files
        $transcriptPath = 'private/podcasts/transcripts';
        if (Storage::exists($transcriptPath)) {
            $files = Storage::allFiles($transcriptPath);
            $this->line('Deleting '.count($files).' transcript files...');
            Storage::deleteDirectory($transcriptPath);
            Storage::makeDirectory($transcriptPath);
        }

        $this->info('✅ Storage directories cleaned');
    }

    private function resetSequences(): void
    {
        $this->line('Resetting database sequences...');

        try {
            // Reset PostgreSQL sequences
            DB::statement('ALTER SEQUENCE transcript_segments_id_seq RESTART WITH 1');
            DB::statement('ALTER SEQUENCE episodes_id_seq RESTART WITH 1');

            if (! $this->option('keep-podcasts')) {
                DB::statement('ALTER SEQUENCE podcasts_id_seq RESTART WITH 1');
            }

            $this->info('✅ Database sequences reset');
        } catch (\Exception $e) {
            $this->warn('⚠️ Could not reset sequences: '.$e->getMessage());
        }
    }
}
