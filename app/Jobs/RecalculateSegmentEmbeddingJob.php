<?php

namespace App\Jobs;

use App\Models\TranscriptSegment;
use App\Services\EmbeddingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RecalculateSegmentEmbeddingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 3600;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $segmentId) {}

    /**
     * Execute the job.
     */
    public function handle(EmbeddingService $embeddingService): void
    {
        Log::debug('Starting RecalculateSegmentEmbeddingJob', ['segment_id' => $this->segmentId]);
        $segment = TranscriptSegment::find($this->segmentId);

        if (! $segment) {
            Log::warning('TranscriptSegment not found', ['segment_id' => $this->segmentId]);

            return;
        }

        // Only process segments that still need embeddings.
        if ($segment->embedding_status !== 'pending') {
            return;
        }

        $embeddingService->processSegment($segment);
    }
}
