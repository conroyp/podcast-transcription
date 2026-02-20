<?php

namespace App\Jobs;

use App\Models\Episode;
use App\Services\EmbeddingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ReEvaluateEmbeddingsJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 3600;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    public function __construct(
        public Episode $episode
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $episodeInfo = "Episode {$this->episode->id}: \"{$this->episode->title}\"";

        try {
            Log::info('🔄 STARTING EMBEDDINGS RE-EVALUATION', [
                'episode_id' => $this->episode->id,
                'episode_title' => $this->episode->title,
            ]);

            // Update status to processing
            $this->episode->update(['embedding_status' => 'processing']);

            // Get all transcript segments for this episode
            $segments = $this->episode->transcriptSegments;

            if ($segments->isEmpty()) {
                Log::error("❌ No transcript segments found for {$episodeInfo}");
                $this->episode->update([
                    'embedding_status' => 'failed',
                    'processing_metadata' => array_merge(
                        $this->episode->processing_metadata ?? [],
                        ['embedding_error' => 'No transcript segments found for this episode']
                    ),
                ]);

                return;
            }

            Log::info("📊 Found {$segments->count()} segments to re-evaluate for {$episodeInfo}");

            // Clear existing embeddings and set status to pending
            $this->episode->transcriptSegments()->update([
                'embedding_vector' => null,
                'embedding_status' => 'pending',
                'embedding_metadata' => null,
                'embedding_created_at' => null,
            ]);

            // Refresh segments to get latest status
            $segments = $this->episode->transcriptSegments()->get();

            // Generate new embeddings using the EmbeddingService (with progress tracking)
            $embeddingService = app(EmbeddingService::class);
            $result = $embeddingService->processSegmentsWithProgress($segments->all());

            // Refresh the episode model to ensure we have the latest state
            $this->episode->refresh();

            Log::info("🔍 EMBEDDING RESULTS DEBUG for {$episodeInfo}", [
                'result_success' => $result['success'],
                'result_failed' => $result['failed'],
                'total_segments' => $segments->count(),
                'current_episode_status' => $this->episode->embedding_status,
            ]);

            // Update episode status based on results
            if ($result['failed'] === 0) {
                $this->episode->update(['embedding_status' => 'completed']);
                Log::info("✅ EMBEDDINGS RE-EVALUATION COMPLETED for {$episodeInfo}", [
                    'total_segments' => $segments->count(),
                    'successful' => $result['success'],
                    'failed' => $result['failed'],
                    'success_rate' => round(($result['success'] / $segments->count()) * 100, 1).'%',
                    'final_status' => 'completed',
                ]);
            } else {
                $this->episode->update([
                    'embedding_status' => 'partial',
                    'processing_metadata' => array_merge(
                        $this->episode->processing_metadata ?? [],
                        [
                            'embedding_partial_completion' => [
                                'successful' => $result['success'],
                                'failed' => $result['failed'],
                                'total' => $segments->count(),
                                'errors' => $result['errors'],
                            ],
                        ]
                    ),
                ]);
                Log::warning("⚠️ EMBEDDINGS RE-EVALUATION PARTIALLY COMPLETED for {$episodeInfo}", [
                    'total_segments' => $segments->count(),
                    'successful' => $result['success'],
                    'failed' => $result['failed'],
                    'success_rate' => round(($result['success'] / $segments->count()) * 100, 1).'%',
                    'final_status' => 'partial',
                ]);
            }

            // Final verification - check actual database state
            $this->episode->refresh();
            $actualCompletedCount = $this->episode->transcriptSegments()->where('embedding_status', 'completed')->count();
            $actualTotalCount = $this->episode->transcriptSegments()->count();

            Log::info("🔍 FINAL VERIFICATION for {$episodeInfo}", [
                'episode_status' => $this->episode->embedding_status,
                'actual_completed' => $actualCompletedCount,
                'actual_total' => $actualTotalCount,
                'should_be_completed' => $actualCompletedCount === $actualTotalCount && $actualTotalCount > 0,
            ]);

            // Safety check: if all segments are actually completed but episode status is wrong, fix it
            if ($actualCompletedCount === $actualTotalCount && $actualTotalCount > 0 && $this->episode->embedding_status !== 'completed') {
                $this->episode->update(['embedding_status' => 'completed']);
                Log::warning("🔧 CORRECTED episode status to completed after final verification for {$episodeInfo}");
            }

        } catch (\Exception $e) {
            Log::error("💥 EMBEDDINGS RE-EVALUATION FAILED for {$episodeInfo}", [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $this->episode->update([
                'embedding_status' => 'failed',
                'processing_metadata' => array_merge(
                    $this->episode->processing_metadata ?? [],
                    ['embedding_error' => $e->getMessage()]
                ),
            ]);

            throw $e;
        }
    }
}
