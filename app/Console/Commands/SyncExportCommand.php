<?php

namespace App\Console\Commands;

use App\Models\Episode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncExportCommand extends Command
{
    protected $signature = 'sync:export {type} {id?} {--endpoint=}';

    protected $description = 'Export and sync episode data to remote server in batches';

    public function handle()
    {
        $type = $this->argument('type');
        $id = $this->argument('id');
        $endpoint = $this->option('endpoint') ?? config('services.sync.upload_endpoint');
        $secret = config('services.sync.shared_secret');

        if (! $endpoint) {
            $this->error('No endpoint specified. Use --endpoint or set SYNC_UPLOAD_ENDPOINT in .env');

            return 1;
        }

        if (! $secret) {
            $this->error('No shared secret configured. Set SHARED_UPLOAD_SECRET in .env');

            return 1;
        }

        $parsedUrl = parse_url($endpoint);
        if (! $parsedUrl || ! isset($parsedUrl['scheme'], $parsedUrl['host'])) {
            $this->error('Invalid endpoint URL format. Expected format: https://your-server.com/api/import-sync');

            return 1;
        }

        if ($type === 'episode') {
            if (! $id) {
                $this->error('You must provide an episode ID.');

                return 1;
            }

            return $this->syncEpisode($id, $endpoint, $secret);
        } else {
            $this->error('Type must be "episode".');

            return 1;
        }
    }

    private function syncEpisode(int $episodeId, string $endpoint, string $secret): int
    {
        // Load episode with relations
        $episode = Episode::with(['podcast'])->find($episodeId);

        if (! $episode) {
            $this->error("Episode {$episodeId} not found");

            return 1;
        }

        $this->info("🚀 Starting sync for episode: {$episode->title}");

        // Step 1: Send episode metadata
        $this->info('📤 Syncing episode metadata...');

        $segmentCount = $episode->transcriptSegments()->count();
        $batchSize = 100;
        $totalBatches = $segmentCount > 0 ? (int) ceil($segmentCount / $batchSize) : 0;

        // Update progress
        $episode->update([
            'sync_progress' => [
                'phase' => 'metadata',
                'current_batch' => 0,
                'total_batches' => $totalBatches,
                'total_segments' => $segmentCount,
            ],
        ]);

        $episodePayload = [
            'type' => 'episode',
            'episode' => $episode->attributesToArray(), // Only attributes, not relationships
            'podcast' => $episode->podcast->attributesToArray(), // Only attributes, not relationships
            'stats' => [
                'total_segments' => $segmentCount,
                'total_batches' => $totalBatches,
                'batch_size' => $batchSize,
            ],
        ];

        if (! $this->sendPayload($endpoint, $secret, $episodePayload, $episode)) {
            $this->error('❌ Failed to sync episode metadata');

            return 1;
        }

        $this->info('✅ Episode metadata synced');

        // Step 2: Send segments in batches
        if ($totalBatches > 0) {
            $this->info("📦 Syncing {$segmentCount} segments in {$totalBatches} batches...");

            $segments = $episode->transcriptSegments()->orderBy('segment_index')->get();
            $batches = $segments->chunk($batchSize);

            $bar = $this->output->createProgressBar($totalBatches);
            $bar->start();

            foreach ($batches as $index => $batch) {
                $batchNumber = $index + 1;

                // Update progress
                $episode->update([
                    'sync_progress' => [
                        'phase' => 'segments',
                        'current_batch' => $batchNumber,
                        'total_batches' => $totalBatches,
                        'total_segments' => $segmentCount,
                    ],
                ]);

                // Prepare segment data with embeddings
                $segmentData = $batch->map(function ($segment) {
                    $data = $segment->attributesToArray(); // Only attributes, not relationships

                    // Get vector embedding if exists
                    if ($segment->embedding_status === 'completed') {
                        try {
                            $vector = DB::selectOne(
                                'SELECT embedding_vector::text as vector FROM transcript_segments WHERE id = ?',
                                [$segment->id]
                            );

                            if ($vector && $vector->vector) {
                                $data['embedding_vector'] = json_decode($vector->vector);
                            }
                        } catch (\Exception $e) {
                            Log::warning("Failed to get embedding for segment {$segment->id}", [
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }

                    return $data;
                })->toArray();

                $segmentPayload = [
                    'type' => 'segments',
                    'episode_id' => $episode->id,
                    'batch_number' => $batchNumber,
                    'total_batches' => $totalBatches,
                    'segments' => $segmentData,
                ];

                if (! $this->sendPayload($endpoint, $secret, $segmentPayload, $episode)) {
                    $bar->finish();
                    $this->error("\n❌ Failed at batch {$batchNumber}/{$totalBatches}");

                    return 1;
                }

                $bar->advance();
            }

            $bar->finish();
            $this->line('');
        }

        $this->info('✅ Sync completed successfully!');
        $this->table(['Metric', 'Value'], [
            ['Episode ID', $episode->id],
            ['Episode Title', $episode->title],
            ['Total Segments', $segmentCount],
            ['Batches Sent', $totalBatches],
            ['Endpoint', $endpoint],
        ]);

        return 0;
    }

    private function sendPayload(string $endpoint, string $secret, array $payload, Episode $episode): bool
    {
        try {
            // Encode to JSON
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE);

            if ($json === false) {
                $this->error('Failed to encode payload to JSON');

                return false;
            }

            // Gzip compress
            $compressed = gzencode($json, 9);

            if ($compressed === false) {
                $this->error('Failed to compress payload');

                return false;
            }

            // Log payload size with segment count
            $originalSize = strlen($json);
            $compressedSize = strlen($compressed);
            $compressionRatio = round((1 - $compressedSize / $originalSize) * 100, 1);

            $logData = [
                'type' => $payload['type'],
                'original_size' => $originalSize,
                'compressed_size' => $compressedSize,
                'compression_ratio' => $compressionRatio.'%',
            ];

            // Add segment count for segment payloads
            if ($payload['type'] === 'segments') {
                $logData['segment_count'] = count($payload['segments']);
                $logData['batch_number'] = $payload['batch_number'];
                $logData['total_batches'] = $payload['total_batches'];
            }

            Log::info('Sending payload', $logData);

            // Build signed headers
            $timestamp = (string) now()->timestamp;
            $parsedEndpoint = parse_url($endpoint);
            $endpointPath = ltrim($parsedEndpoint['path'] ?? '', '/');
            $signature = hash_hmac('sha256', implode("\n", [
                'POST',
                $endpointPath,
                $timestamp,
                hash('sha256', $compressed),
            ]), $secret);

            // Send to endpoint
            $response = Http::attach('file', $compressed, 'payload.gz')
                ->withHeaders([
                    'X-Upload-Secret' => $secret,
                    'X-Upload-Timestamp' => $timestamp,
                    'X-Upload-Signature' => $signature,
                ])
                ->timeout(30) // 30 second timeout per batch
                ->post($endpoint);

            if (! $response->successful()) {
                $errorMsg = "HTTP {$response->status()}";
                $errorBody = $response->body();

                $this->error("❌ {$errorMsg}: {$errorBody}");

                Log::error('Sync HTTP request failed', [
                    'type' => $payload['type'],
                    'status' => $response->status(),
                    'body' => $errorBody,
                    'endpoint' => $endpoint,
                ]);

                return false;
            }

            return true;

        } catch (\Exception $e) {
            $this->error("Exception during payload send: {$e->getMessage()}");
            Log::error('Sync export failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }
}
