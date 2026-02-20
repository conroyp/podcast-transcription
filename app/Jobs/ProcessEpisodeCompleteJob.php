<?php

namespace App\Jobs;

use App\Models\Episode;
use App\Services\AdRemovalService;
use App\Services\EmbeddingService;
use App\Services\PodcastDownloadService;
use App\Services\TranscriptChunkingService;
use App\Services\TranscriptCleaningService;
use App\Services\TranscriptionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessEpisodeCompleteJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 3600; // 1 hour timeout for long transcriptions

    public function __construct(
        public Episode $episode,
        public array $options = []
    ) {
        // Default options
        $this->options = array_merge([
            'force_download' => false,
            'force_transcription' => false,
            'skip_ad_removal' => false,
            'skip_chunking' => false,
            'skip_embedding' => false,
        ], $this->options);
    }

    /**
     * Execute the complete episode processing pipeline.
     */
    public function handle(): void
    {
        $episodeInfo = "Episode {$this->episode->id}: \"{$this->episode->title}\"";

        try {
            Log::info('🎵 STARTING COMPLETE EPISODE PIPELINE', [
                'episode_id' => $this->episode->id,
                'episode_title' => $this->episode->title,
                'podcast_title' => $this->episode->podcast->title ?? 'Unknown',
                'options' => $this->options,
            ]);

            // Step 1: Download Audio
            $this->downloadAudio();

            // Step 2: Transcribe
            $this->transcribeAudio();

            // Step 3: Clean Transcripts
            $this->cleanTranscripts();

            // Step 4: Remove Ads
            if (! $this->options['skip_ad_removal']) {
                $this->removeAds();
            }

            // Step 5: Chunk Transcripts
            if (! $this->options['skip_chunking']) {
                $this->chunkTranscripts();
            }

            // Step 6: Generate Embeddings
            if (! $this->options['skip_embedding']) {
                $this->generateEmbeddings();
            }

            Log::info("🎉 COMPLETE PIPELINE FINISHED for {$episodeInfo}");

        } catch (\Exception $e) {
            Log::error("💥 COMPLETE PIPELINE FAILED for {$episodeInfo}", [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $this->episode->update([
                'processing_metadata' => array_merge(
                    $this->episode->processing_metadata ?? [],
                    ['pipeline_error' => $e->getMessage(), 'failed_at' => now()->toISOString()]
                ),
            ]);

            throw $e;
        }
    }

    private function downloadAudio(): void
    {
        if ($this->episode->isDownloaded() && ! $this->options['force_download']) {
            Log::info('✅ Audio already downloaded, skipping');

            return;
        }

        Log::info('📥 Starting audio download...');
        $this->episode->update(['download_status' => 'downloading']);

        $downloadService = app(PodcastDownloadService::class);

        if (! $downloadService->download($this->episode)) {
            throw new \Exception('Failed to download episode audio');
        }

        Log::info('✅ Audio download completed');
    }

    private function transcribeAudio(): void
    {
        if ($this->episode->transcription_status === 'completed' && ! $this->options['force_transcription']) {
            Log::info('✅ Already transcribed, skipping');

            return;
        }

        // Clear existing segments to avoid duplicates
        if ($this->episode->transcriptSegments()->exists()) {
            Log::info('🗑️ Clearing existing transcript segments before transcription');
            $this->episode->transcriptSegments()->delete();
        }

        Log::info('🎤 Starting transcription...');
        $this->episode->update(['transcription_status' => 'processing']);

        $transcriptionService = app(TranscriptionService::class);

        // Disable auto-processing in TranscriptionService since we're handling it here
        $transcriptionService->setAutoRemoveAds(false);

        // Generate context prompt
        $episodeTitle = str_replace(['"', "'", '&'], ['', '', 'and'], $this->episode->title);
        $contextPrompt = "This is a podcast episode titled: {$episodeTitle}";
        if ($this->episode->podcast && $this->episode->podcast->title) {
            $podcastTitle = str_replace(['"', "'", '&'], ['', '', 'and'], $this->episode->podcast->title);
            $contextPrompt .= " from the podcast {$podcastTitle}";
        }

        // Use the transcribe method which accepts the context prompt
        $audioPath = \App\Services\Storage\PodcastFileManager::getEpisodeAudioPath($this->episode);
        try {
            $transcriptionService->transcribe($audioPath, $this->episode, $contextPrompt);
        } finally {
            // Restore original setting
            $transcriptionService->setAutoRemoveAds(true);
        }

        Log::info('✅ Transcription completed');
    }

    private function cleanTranscripts(): void
    {
        Log::info('🧹 Cleaning transcripts...');

        $cleaningService = app(TranscriptCleaningService::class);
        $segments = $this->episode->transcriptSegments;
        $cleanedCount = 0;

        foreach ($segments as $segment) {
            $cleanResult = $cleaningService->cleanTranscript($segment->text);
            if ($cleanResult['text'] !== $segment->text) {
                $segment->update(['text' => $cleanResult['text']]);
                $cleanedCount++;
            }
        }

        Log::info("✅ Cleaned {$cleanedCount} transcript segments");
    }

    private function removeAds(): void
    {
        Log::info('🚫 Removing ad content...');

        $adRemovalService = app(AdRemovalService::class);
        $result = $adRemovalService->removeAdsFromEpisode($this->episode);
        $removedCount = $result['segments_removed'] ?? 0;

        Log::info("✅ Removed {$removedCount} ad segments");
    }

    private function chunkTranscripts(): void
    {
        Log::info('📊 Chunking transcripts...');

        $chunkingService = app(TranscriptChunkingService::class);
        $result = $chunkingService->chunkEpisodeSegments($this->episode);
        $chunkCount = $result['chunked_count'] ?? 0;

        Log::info("✅ Created {$chunkCount} transcript chunks");
    }

    private function generateEmbeddings(): void
    {
        Log::info('🧠 Generating embeddings...');

        $embeddingService = app(EmbeddingService::class);
        $segments = $this->episode->transcriptSegments()->where('embedding_status', 'pending')->get();
        $embeddedCount = 0;

        foreach ($segments as $segment) {
            if ($embeddingService->processSegment($segment)) {
                $embeddedCount++;
            }
        }

        $failedCount = $segments->count() - $embeddedCount;

        if ($failedCount === 0 && $embeddedCount > 0) {
            $this->episode->update(['embedding_status' => 'completed']);
        } elseif ($embeddedCount > 0) {
            $this->episode->update(['embedding_status' => 'partial']);
        } elseif ($segments->count() > 0) {
            $this->episode->update(['embedding_status' => 'failed']);
        } else {
            // No pending segments - derive status from existing segment states
            $totalSegments = $this->episode->transcriptSegments()->count();
            $completedSegments = $this->episode->transcriptSegments()->where('embedding_status', 'completed')->count();

            if ($totalSegments > 0 && $completedSegments === $totalSegments) {
                $this->episode->update(['embedding_status' => 'completed']);
            } elseif ($completedSegments > 0) {
                $this->episode->update(['embedding_status' => 'partial']);
            }
        }

        Log::info("✅ Generated embeddings for {$embeddedCount} segments");
    }
}
