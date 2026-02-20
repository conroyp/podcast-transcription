<?php

namespace App\Console\Commands;

use App\Jobs\ProcessEpisodeCompleteJob;
use App\Models\Episode;
use Illuminate\Console\Command;

class ProcessEpisode extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'episode:process
                           {episode_id : The ID of the episode to process}
                           {--force-download : Force re-download even if already downloaded}
                           {--force-transcription : Force re-transcription even if already transcribed}
                           {--skip-ad-removal : Skip ad removal step}
                           {--skip-chunking : Skip transcript chunking step}
                           {--skip-embedding : Skip embedding generation step}
                           {--sync : Run job synchronously instead of queuing it}';

    /**
     * The console command description.
     */
    protected $description = 'Process a single episode through the complete pipeline';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $episodeId = $this->argument('episode_id');
        $sync = $this->option('sync');

        $episode = Episode::find($episodeId);

        if (! $episode) {
            $this->error("Episode {$episodeId} not found");

            return 1;
        }

        $jobOptions = [
            'force_download' => $this->option('force-download'),
            'force_transcription' => $this->option('force-transcription'),
            'skip_ad_removal' => $this->option('skip-ad-removal'),
            'skip_chunking' => $this->option('skip-chunking'),
            'skip_embedding' => $this->option('skip-embedding'),
        ];

        $this->info("🎙️ Processing Episode {$episode->id}: {$episode->title}");
        $this->line('Mode: '.($sync ? 'Synchronous' : 'Queued'));
        $this->newLine();

        $this->info('🔧 Job Options:');
        foreach ($jobOptions as $key => $value) {
            $this->line("  {$key}: ".($value ? 'true' : 'false'));
        }
        $this->newLine();

        try {
            if ($sync) {
                // Run synchronously
                $this->info('🔄 Running synchronously...');
                $job = new ProcessEpisodeCompleteJob($episode, $jobOptions);
                $job->handle();
                $this->info('✅ Episode processing completed successfully!');
            } else {
                // Queue the job
                $this->info('📤 Queueing job...');
                ProcessEpisodeCompleteJob::dispatch($episode, $jobOptions);
                $this->info('✅ Job queued successfully!');
                $this->newLine();
                $this->info('💡 Monitor progress with: php artisan queue:work');
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Failed to process episode: {$e->getMessage()}");

            return 1;
        }
    }
}
