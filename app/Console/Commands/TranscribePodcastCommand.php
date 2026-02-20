<?php

namespace App\Console\Commands;

use App\Models\Episode;
use App\Services\TranscriptionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TranscribePodcastCommand extends Command
{
    protected $signature = 'podcast:transcribe
                            {episode_id : The ID of the episode to transcribe}
                            {--force : Force re-transcription even if already completed}
                            {--headless : Run without interactive prompts}';

    protected $description = 'Transcribe a podcast episode using Whisper';

    public function handle(TranscriptionService $transcriptionService): int
    {
        $episodeId = $this->argument('episode_id');
        $force = $this->option('force');
        $headless = $this->option('headless');

        try {
            $episode = Episode::findOrFail($episodeId);

            $this->info("Episode: {$episode->title}");
            $this->info("Podcast: {$episode->podcast->title}");

            // Check if already transcribed
            if ($episode->isTranscribed() && ! $force) {
                $this->warn('Episode is already transcribed. Use --force to re-transcribe.');

                $segmentCount = $episode->transcriptSegments()->count();
                $this->info("Current transcript has {$segmentCount} segments");

                if (! $headless && $this->confirm('Do you want to view the first few segments?')) {
                    $this->displayTranscriptPreview($episode);
                }

                return self::SUCCESS;
            }

            // Check if audio file is downloaded
            if (! $episode->isDownloaded()) {
                $this->error('Episode audio file is not downloaded. Run podcast:download first.');

                return self::FAILURE;
            }

            $this->info("Audio file: {$episode->local_audio_path}");
            $this->info("Duration: {$episode->formatted_duration}");
            $this->info('File size: '.$this->formatBytes($episode->file_size_bytes));

            // Confirm transcription (skip in headless mode)
            if (! $headless && ! $this->confirm('Start transcription? This may take several minutes.')) {
                $this->info('Transcription cancelled');

                return self::SUCCESS;
            }

            $this->info('🎯 Starting transcription with Whisper...');
            $this->info('This may take a while depending on episode length.');

            $startTime = microtime(true);

            // Start transcription (throws on failure)
            $transcriptionService->transcribeEpisode($episode);

            $endTime = microtime(true);
            $duration = round($endTime - $startTime, 2);

            $episode->refresh();
            $segmentCount = $episode->transcriptSegments()->count();

            $this->info("✅ Successfully transcribed episode in {$duration} seconds");
            $this->info("Created {$segmentCount} transcript segments");

            // Show preview
            $this->displayTranscriptPreview($episode);

            return self::SUCCESS;

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            $this->error("Episode with ID {$episodeId} not found");

            return self::FAILURE;
        } catch (\Exception $e) {
            $this->error('Error: '.$e->getMessage());
            Log::error('Transcription command failed', [
                'episode_id' => $episodeId,
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }
    }

    private function displayTranscriptPreview(Episode $episode): void
    {
        $segments = $episode->transcriptSegments()
            ->orderBy('segment_index')
            ->limit(5)
            ->get();

        if ($segments->isEmpty()) {
            $this->warn('No transcript segments found');

            return;
        }

        $this->info("\n📝 Transcript Preview:");
        $this->line(str_repeat('-', 60));

        foreach ($segments as $segment) {
            $timestamp = $segment->getFormattedTimestamp();
            $confidence = $segment->confidence ?
                sprintf(' (%.0f%%)', $segment->confidence * 100) : '';

            $this->line("<fg=cyan>[{$timestamp}]</fg=cyan>{$confidence}");
            $this->line(wordwrap($segment->text, 55, "\n    "));
            $this->line('');
        }

        $totalSegments = $episode->transcriptSegments()->count();
        if ($totalSegments > 5) {
            $this->line('... and '.($totalSegments - 5).' more segments');
        }

        $this->line(str_repeat('-', 60));
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = floor(log($bytes, 1024));

        return round($bytes / pow(1024, $power), 2).' '.$units[$power];
    }
}
