<?php

namespace App\Http\Controllers;

use App\Models\Episode;
use App\Models\TranscriptSegment;
use App\Services\Storage\PodcastFileManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EpisodeController extends Controller
{
    public function index()
    {
        $episodes = Episode::with(['podcast'])
            ->withCount([
                'transcriptSegments',
                'transcriptSegments as embeddings_count' => function ($query) {
                    $query->whereNotNull('embedding_vector');
                },
            ])
            ->orderBy('published_at', 'desc')
            ->paginate(20);

        return view('episodes.index', compact('episodes'));
    }

    public function show(Episode $episode)
    {
        $allEpisodes = Episode::with('podcast')
            ->orderBy('published_at', 'desc')
            ->get();

        $transcriptSegments = $episode->transcriptSegments()
            ->orderBy('start_time')
            ->get();

        // Calculate embedding statistics
        $embeddingStats = [
            'total_segments' => $transcriptSegments->count(),
            'embedded_segments' => $transcriptSegments->whereNotNull('embedding_vector')->count(),
            'pending_segments' => $transcriptSegments->where('embedding_status', 'pending')->count(),
            'failed_segments' => $transcriptSegments->where('embedding_status', 'failed')->count(),
        ];

        $embeddingStats['completion_rate'] = $embeddingStats['total_segments'] > 0
            ? round(($embeddingStats['embedded_segments'] / $embeddingStats['total_segments']) * 100, 1)
            : 0;

        return view('episodes.show', compact('episode', 'allEpisodes', 'transcriptSegments', 'embeddingStats'));
    }

    public function updateTranscript(Request $request, TranscriptSegment $segment): JsonResponse
    {
        $this->authorize('update', $segment);

        $request->validate([
            'text' => 'required|string',
        ]);

        $segment->update([
            'text' => $request->text,
            'confidence' => 1.0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Transcript updated successfully',
        ], 200, [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
    }

    public function deleteTranscript(TranscriptSegment $segment): JsonResponse
    {
        $this->authorize('delete', $segment);

        $segment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Transcript segment deleted successfully',
        ], 200, [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
    }

    public function serveAudio(Episode $episode)
    {
        if (! $episode->isDownloaded()) {
            abort(404);
        }

        $path = PodcastFileManager::getEpisodeAudioPath($episode);

        if (! file_exists($path)) {
            abort(404);
        }

        return response()->file($path, [
            'Content-Type' => 'audio/mpeg',
            'Accept-Ranges' => 'bytes',
        ]);
    }

    /**
     * Re-evaluate embeddings for an episode
     */
    public function reEvaluateEmbeddings(Episode $episode): JsonResponse
    {
        try {
            // Check if episode has transcript segments
            $segmentCount = $episode->transcriptSegments()->count();

            if ($segmentCount === 0) {
                return response()->json([
                    'success' => false,
                    'error' => 'No transcript segments found for this episode',
                ], 400, [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
            }

            // Check if already processing
            if ($episode->embedding_status === 'processing') {
                return response()->json([
                    'success' => false,
                    'error' => 'Embeddings are already being processed for this episode',
                ], 409, [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
            }

            // Dispatch the job to re-evaluate embeddings
            \App\Jobs\ReEvaluateEmbeddingsJob::dispatch($episode);

            // Update status to processing
            $episode->update(['embedding_status' => 'processing']);

            return response()->json([
                'success' => true,
                'message' => 'Embeddings re-evaluation job has been queued',
                'status' => 'processing',
                'segment_count' => $segmentCount,
            ], 200, [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);

        } catch (\Exception $e) {
            Log::error('Failed to dispatch re-evaluate embeddings job for episode', [
                'episode_id' => $episode->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to queue embeddings re-evaluation: '.$e->getMessage(),
            ], 500, [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
        }
    }

    /**
     * Get embedding progress for an episode
     */
    public function getEmbeddingProgress(Episode $episode): JsonResponse
    {
        $segments = $episode->transcriptSegments;

        $stats = [
            'total_segments' => $segments->count(),
            'pending_segments' => $segments->where('embedding_status', 'pending')->count(),
            'processing_segments' => $segments->where('embedding_status', 'processing')->count(),
            'embedded_segments' => $segments->whereNotNull('embedding_vector')->where('embedding_status', 'completed')->count(),
            'failed_segments' => $segments->where('embedding_status', 'failed')->count(),
            'episode_status' => $episode->embedding_status,
        ];

        $stats['completion_rate'] = $stats['total_segments'] > 0
            ? round(($stats['embedded_segments'] / $stats['total_segments']) * 100, 1)
            : 0;

        $stats['is_processing'] = $episode->embedding_status === 'processing';
        $stats['is_completed'] = $episode->embedding_status === 'completed';
        $stats['is_failed'] = $episode->embedding_status === 'failed';
        $stats['is_partial'] = $episode->embedding_status === 'partial';

        return response()->json([
            'success' => true,
            'stats' => $stats,
        ], 200, [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
    }

    /**
     * Re-transcribe an episode
     */
    public function reTranscribe(Episode $episode): JsonResponse
    {
        try {
            // Check if episode has audio URL
            if (! $episode->audio_url) {
                return response()->json([
                    'success' => false,
                    'error' => 'Episode has no audio URL to transcribe',
                ], 400, [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
            }

            // Check if already processing
            if ($episode->transcription_status === 'processing') {
                return response()->json([
                    'success' => false,
                    'error' => 'Episode is already being transcribed',
                ], 409, [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
            }

            // Dispatch the job to re-transcribe the episode
            \App\Jobs\ProcessEpisodeCompleteJob::dispatch($episode, [
                'force_download' => true,
                'force_transcription' => true,
            ]);

            // Update status to processing
            $episode->update([
                'transcription_status' => 'processing',
                'download_status' => 'downloading',
                'embedding_status' => 'pending',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Re-transcription job has been queued',
                'status' => 'processing',
            ], 200, [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);

        } catch (\Exception $e) {
            Log::error('Failed to dispatch re-transcription job for episode', [
                'episode_id' => $episode->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to queue re-transcription.'], 500);
        }
    }

    /**
     * Sync episode to remote server
     */
    public function syncEpisode(Episode $episode): JsonResponse
    {
        try {
            // Validate episode is ready
            if (! $episode->isFullyProcessed()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Episode not fully processed yet. Ensure download, transcription, and embeddings are complete.',
                ], 400, [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
            }

            $endpoint = config('services.sync.upload_endpoint');
            $secret = config('services.sync.shared_secret');

            if (! $endpoint || ! $secret) {
                return response()->json([
                    'success' => false,
                    'error' => 'Sync not configured. Set SYNC_UPLOAD_ENDPOINT and SHARED_UPLOAD_SECRET in .env',
                ], 500, [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
            }

            // Reset sync status before queuing
            $episode->update([
                'sync_status' => 'queued',
                'sync_progress' => [
                    'phase' => 'queued',
                    'queued_at' => now()->toIso8601String(),
                ],
            ]);

            // Dispatch sync job to run in background (pass ID, not model)
            \App\Jobs\SyncEpisodeJob::dispatch($episode->id, $endpoint);

            Log::info('Episode sync job queued from dashboard', [
                'episode_id' => $episode->id,
                'episode_title' => $episode->title,
                'endpoint' => $endpoint,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Episode sync has been queued. Monitoring progress...',
            ], 200, [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);

        } catch (\Exception $e) {
            Log::error('Failed to queue sync job', [
                'episode_id' => $episode->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to queue sync.'], 500);
        }
    }

    /**
     * Get sync status for an episode
     */
    public function getSyncStatus(Episode $episode): JsonResponse
    {
        return response()->json([
            'success' => true,
            'status' => $episode->sync_status,
            'progress' => $episode->sync_progress,
        ], 200, [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
    }

    /**
     * Remove the specified episode and all related data.
     */
    public function destroy(Episode $episode)
    {
        try {
            // Get file paths before deleting the episode
            $audioPath = PodcastFileManager::getEpisodeAudioPath($episode);
            $transcriptDir = PodcastFileManager::getTranscriptDirectory($episode);

            // Begin database transaction
            \DB::beginTransaction();

            // Delete all transcript segments
            $segmentCount = $episode->transcriptSegments()->count();
            $episode->transcriptSegments()->delete();

            // Delete the episode
            $episode->delete();

            \DB::commit();

            // Delete files after successful database deletion
            if ($audioPath && file_exists($audioPath)) {
                unlink($audioPath);
            }

            if ($transcriptDir && file_exists($transcriptDir)) {
                // Recursively delete the transcript directory
                array_map('unlink', glob("$transcriptDir/*.*"));
                rmdir($transcriptDir);
            }

            return redirect()->route('episodes.index')
                ->with('success', "Episode deleted successfully along with $segmentCount transcript segments.");
        } catch (\Exception $e) {
            \DB::rollBack();
            Log::error('Failed to delete episode: '.$e->getMessage(), [
                'episode_id' => $episode->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('episodes.index')
                ->with('error', 'Failed to delete episode: '.$e->getMessage());
        }
    }
}
