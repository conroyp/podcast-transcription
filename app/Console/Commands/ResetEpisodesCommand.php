<?php

namespace App\Console\Commands;

use App\Models\Episode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ResetEpisodesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:reset-episodes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deletes all episodes, their transcript segments, and associated audio files. User and Podcast records are not affected.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->confirm('Are you sure you want to delete ALL episodes and their data? This action cannot be undone.')) {
            $this->info('Starting the reset process...');

            // 1. Get all episodes to find their audio files before they are deleted
            $this->info('Finding associated audio files...');
            $episodes = Episode::all();
            $audioPaths = $episodes->pluck('audio_path')->filter();

            // 2. Use mass deletion for efficiency
            $this->info('Deleting transcript segments...');
            $deletedSegments = DB::table('transcript_segments')->delete();
            $this->info("Deleted {$deletedSegments} transcript segments.");

            $this->info('Deleting episodes...');
            $deletedEpisodes = DB::table('episodes')->delete();
            $this->info("Deleted {$deletedEpisodes} episodes.");

            // 3. Delete the audio files from storage
            if ($audioPaths->isNotEmpty()) {
                $this->info('Deleting audio files from storage...');
                $deletedFiles = 0;
                $bar = $this->output->createProgressBar($audioPaths->count());
                $bar->start();

                foreach ($audioPaths as $path) {
                    if (Storage::disk('private')->exists($path)) {
                        Storage::disk('private')->delete($path);
                        $deletedFiles++;
                    }
                    $bar->advance();
                }
                $bar->finish();
                $this->newLine();
                $this->info("Deleted {$deletedFiles} audio files.");
            } else {
                $this->info('No audio files found to delete.');
            }

            $this->info('Reset complete. The database is ready for re-ingestion.');
        } else {
            $this->info('Reset cancelled.');
        }

        return 0;
    }
}
