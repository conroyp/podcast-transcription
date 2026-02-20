<?php

namespace App\Http\Controllers;

use App\Models\Episode;
use App\Models\Podcast;
use App\Models\TranscriptSegment;
use App\Services\SearchCacheService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncImportController extends Controller
{
    /**
     * Verify HMAC signature and timestamp freshness for a request.
     *
     * @param  string  $bodyContent  Raw body/payload used to compute the request signature
     */
    private function verifySignature(Request $request, string $bodyContent): bool
    {
        $sharedSecret = config('services.sync.shared_secret');
        $ttl = (int) config('services.sync.signature_ttl_seconds', 300);

        $receivedSecret = $request->header('X-Upload-Secret');
        $timestamp = $request->header('X-Upload-Timestamp');
        $receivedSignature = $request->header('X-Upload-Signature');

        if (! $receivedSecret || ! $timestamp || ! $receivedSignature) {
            return false;
        }

        if (! hash_equals((string) $sharedSecret, (string) $receivedSecret)) {
            return false;
        }

        if (abs(now()->timestamp - (int) $timestamp) > $ttl) {
            return false;
        }

        $path = ltrim($request->path(), '/');
        $expectedSignature = hash_hmac('sha256', implode("\n", [
            'POST',
            $path,
            $timestamp,
            hash('sha256', $bodyContent),
        ]), $sharedSecret);

        return hash_equals($expectedSignature, (string) $receivedSignature);
    }

    /**
     * Healthcheck endpoint to validate connection and authentication
     */
    public function healthcheck(Request $request)
    {
        if (! $this->verifySignature($request, '')) {
            return response()->json([
                'status' => 'unauthorized',
                'message' => 'Invalid or missing authentication secret',
            ], 401);
        }

        return response()->json([
            'status' => 'ok',
            'message' => 'Sync endpoint is healthy and authentication successful',
            'server_time' => now()->toIso8601String(),
            'environment' => app()->environment(),
        ]);
    }

    public function import(Request $request)
    {
        if (! $request->hasFile('file')) {
            return response()->json(['error' => 'No file uploaded'], 400);
        }

        $fileContent = file_get_contents($request->file('file')->getPathname());

        if (! $this->verifySignature($request, $fileContent)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Decompress and decode payload
        $json = @gzdecode($fileContent);
        if ($json === false) {
            return response()->json(['error' => 'Failed to decompress'], 400);
        }

        try {

            $data = json_decode($json, true);
            if (! $data || json_last_error() !== JSON_ERROR_NONE) {
                return response()->json(['error' => 'Invalid JSON: '.json_last_error_msg()], 400);
            }

        } catch (\Exception $e) {
            Log::error('Failed to process upload', ['error' => $e->getMessage()]);

            return response()->json(['error' => 'Failed to process upload'], 500);
        }

        // Route to appropriate handler
        DB::beginTransaction();

        // Unguard models for ID preservation during import
        Episode::unguard();
        Podcast::unguard();
        TranscriptSegment::unguard();

        try {
            if ($data['type'] === 'episode') {
                $result = $this->importEpisodeMetadata($data);
                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Episode metadata imported',
                    'episode_id' => $result['episode_id'],
                    'total_batches' => $data['stats']['total_batches'] ?? 0,
                    'total_segments' => $data['stats']['total_segments'] ?? 0,
                ]);

            } elseif ($data['type'] === 'segments') {
                $result = $this->importSegmentBatch($data);
                DB::commit();

                $isFinalBatch = $data['batch_number'] === $data['total_batches'];

                // If this is the final batch, clear cache
                if ($isFinalBatch) {
                    app(SearchCacheService::class)->clearAllCache();

                    Log::info('Sync import completed', [
                        'episode_id' => $data['episode_id'],
                        'total_segments_imported' => $result['segments_imported'],
                        'total_batches' => $data['total_batches'],
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Final batch imported, cache cleared',
                        'batch_number' => $data['batch_number'],
                        'total_batches' => $data['total_batches'],
                        'segments_imported' => $result['segments_imported'],
                        'completed' => true,
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Segment batch imported',
                    'batch_number' => $data['batch_number'],
                    'total_batches' => $data['total_batches'],
                    'segments_imported' => $result['segments_imported'],
                    'completed' => false,
                ]);

            } else {
                DB::rollBack();

                return response()->json(['error' => 'Unknown type: '.($data['type'] ?? 'missing')], 400);
            }

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Sync import failed', [
                'type' => $data['type'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Import failed',
            ], 500);
        } finally {
            // Always re-guard models to restore mass assignment protection
            Episode::reguard();
            Podcast::reguard();
            TranscriptSegment::reguard();
        }
    }

    private function importEpisodeMetadata(array $data): array
    {
        // Upsert podcast
        $podcast = Podcast::updateOrCreate(
            ['id' => $data['podcast']['id']],
            $data['podcast']
        );

        // Upsert episode (without segments)
        $episode = Episode::updateOrCreate(
            ['id' => $data['episode']['id']],
            $data['episode']
        );

        Log::info('Episode metadata imported', [
            'episode_id' => $episode->id,
            'podcast_id' => $podcast->id,
            'total_segments_expected' => $data['stats']['total_segments'] ?? 0,
        ]);

        return [
            'episode_id' => $episode->id,
            'podcast_id' => $podcast->id,
        ];
    }

    private function importSegmentBatch(array $data): array
    {
        $segmentsImported = 0;

        foreach ($data['segments'] as $segmentData) {
            $embedding = $segmentData['embedding_vector'] ?? null;
            unset($segmentData['embedding_vector']); // Remove from attributes

            if (! isset($segmentData['word_count']) || $segmentData['word_count'] === null) {
                $segmentData['word_count'] = str_word_count($segmentData['text'] ?? '');
            }

            if ($embedding && is_array($embedding)) {
                // Insert/update with vector using raw SQL
                $vectorString = '['.implode(',', $embedding).']';

                // Check if segment exists
                $exists = DB::table('transcript_segments')
                    ->where('id', $segmentData['id'])
                    ->exists();

                if ($exists) {
                    // Update existing
                    DB::statement('
                        UPDATE transcript_segments
                        SET episode_id = ?,
                            start_time = ?,
                            end_time = ?,
                            text = ?,
                            confidence = ?,
                            segment_index = ?,
                            word_count = ?,
                            embedding_status = ?,
                            embedding_vector = ?::vector,
                            updated_at = ?
                        WHERE id = ?
                    ', [
                        $segmentData['episode_id'],
                        $segmentData['start_time'],
                        $segmentData['end_time'],
                        $segmentData['text'],
                        $segmentData['confidence'],
                        $segmentData['segment_index'] ?? null,
                        $segmentData['word_count'],
                        $segmentData['embedding_status'] ?? 'pending',
                        $vectorString,
                        $segmentData['updated_at'] ?? now(),
                        $segmentData['id'],
                    ]);
                } else {
                    // Insert new
                    DB::statement('
                        INSERT INTO transcript_segments (
                            id, episode_id, start_time, end_time, text,
                            confidence, segment_index, word_count, embedding_status,
                            embedding_vector, created_at, updated_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?::vector, ?, ?)
                    ', [
                        $segmentData['id'],
                        $segmentData['episode_id'],
                        $segmentData['start_time'],
                        $segmentData['end_time'],
                        $segmentData['text'],
                        $segmentData['confidence'],
                        $segmentData['segment_index'] ?? null,
                        $segmentData['word_count'],
                        $segmentData['embedding_status'] ?? 'pending',
                        $vectorString,
                        $segmentData['created_at'] ?? now(),
                        $segmentData['updated_at'] ?? now(),
                    ]);
                }

                $segmentsImported++;

            } else {
                // No embedding, use standard upsert
                TranscriptSegment::updateOrCreate(
                    ['id' => $segmentData['id']],
                    $segmentData
                );

                $segmentsImported++;
            }
        }

        Log::info('Segment batch imported', [
            'episode_id' => $data['episode_id'],
            'batch_number' => $data['batch_number'],
            'total_batches' => $data['total_batches'],
            'segments_in_batch' => count($data['segments']),
            'segments_imported' => $segmentsImported,
        ]);

        return [
            'segments_imported' => $segmentsImported,
        ];
    }
}
