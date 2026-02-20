<?php

namespace App\Jobs;

use App\Models\Episode;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class SyncEpisodeJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 600; // 10 minutes for large episodes

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $episodeId,
        public string $endpoint
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Load episode fresh from database
        $episode = Episode::findOrFail($this->episodeId);

        // Update status to syncing
        $episode->update([
            'sync_status' => 'syncing',
            'sync_progress' => [
                'phase' => 'starting',
                'current_batch' => 0,
                'total_batches' => 0,
                'started_at' => now()->toIso8601String(),
            ],
        ]);

        Log::info('Starting sync job for episode', [
            'episode_id' => $episode->id,
            'episode_title' => $episode->title,
            'endpoint' => $this->endpoint,
        ]);

        $exitCode = Artisan::call('sync:export', [
            'type' => 'episode',
            'id' => $episode->id,
            '--endpoint' => $this->endpoint,
        ]);

        // Refresh episode from database to get latest state
        $episode->refresh();

        if ($exitCode === 0) {
            $episode->update([
                'sync_status' => 'completed',
                'sync_progress' => [
                    'phase' => 'completed',
                    'current_batch' => 0,
                    'total_batches' => 0,
                    'completed_at' => now()->toIso8601String(),
                ],
                'last_synced_at' => now(),
            ]);

            Log::info('Sync job completed successfully', [
                'episode_id' => $episode->id,
            ]);
        } else {
            $output = Artisan::output();

            $episode->update([
                'sync_status' => 'failed',
                'sync_progress' => [
                    'phase' => 'failed',
                    'error' => $output,
                    'failed_at' => now()->toIso8601String(),
                ],
            ]);

            Log::error('Sync job failed', [
                'episode_id' => $episode->id,
                'exit_code' => $exitCode,
                'output' => $output,
            ]);

            throw new \Exception("Sync failed with exit code {$exitCode}");
        }
    }
}
