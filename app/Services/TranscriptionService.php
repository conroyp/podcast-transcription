<?php

namespace App\Services;

use App\Models\Episode;
use App\Models\TranscriptSegment;
use App\Services\Storage\PodcastFileManager;
use Illuminate\Support\Facades\Log;

class TranscriptionService
{
    private EmbeddingService $embeddingService;

    /** ----------------------------------------------------------------
     *  Engine configuration
     *  ---------------------------------------------------------------- */
    // Toggle between engines (true => Faster-Whisper Python; false => whisper.cpp)
    private bool $useFasterWhisper = true;

    // Faster-Whisper runner (inside your venv)
    private string $pythonBin = '';

    private string $fwScript = '';

    // whisper.cpp runner (kept for backward compatibility)
    private string $whisperCommand = '';

    private string $whisperModel = '';

    // General settings
    private int $segmentLengthSeconds = 30;

    private string $contextPrompt = '';

    private bool $autoRemoveAds = true;

    public function __construct(EmbeddingService $embeddingService, ?bool $useFasterWhisper = null)
    {
        $this->embeddingService = $embeddingService;
        $this->pythonBin = config('services.whisper.python_path', base_path('.venv/bin/python'));
        $this->fwScript = config('services.whisper.script_path', base_path('scripts/transcribe.py'));
        $this->whisperCommand = config('services.whisper.bin_path', '');
        $this->whisperModel = config('services.whisper.model_path', '');

        if ($useFasterWhisper !== null) {
            $this->useFasterWhisper = $useFasterWhisper;
        }

        if (! $this->isTranscriberAvailable()) {
            // Don't throw exception in constructor, just log warning
            // This allows the service to be instantiated for testing or other purposes
            Log::warning('No transcription engine available. Check paths/binaries.');
        }
    }

    /** ----------------------------------------------------------------
     *  Public entry points
     *  ---------------------------------------------------------------- */
    public function transcribeEpisode(Episode $episode): bool
    {
        if (! $episode->isDownloaded()) {
            throw new \Exception("Cannot transcribe episode {$episode->id}: audio file not downloaded");
        }

        try {
            $episode->update(['transcription_status' => 'processing']);

            $audioPath = PodcastFileManager::getEpisodeAudioPath($episode);
            if (! $audioPath || ! file_exists($audioPath)) {
                throw new \Exception("Audio file not found for episode {$episode->id}");
            }

            $outputDir = PodcastFileManager::getTranscriptDirectory($episode);
            if (! is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            Log::info('Starting transcription for episode', [
                'episode_id' => $episode->id,
                'audio_path' => $audioPath,
                'output_dir' => $outputDir,
                'engine' => $this->useFasterWhisper ? 'faster-whisper' : 'whisper.cpp',
            ]);

            $transcriptData = $this->runTranscriber($audioPath, $outputDir, $episode);
            if (! $transcriptData) {
                throw new \Exception('Transcription failed (no JSON)');
            }

            // Persist segments
            $this->storeTranscriptSegments($episode, $transcriptData);

            // Optional ad removal
            if ($this->autoRemoveAds) {
                $this->removeAdContent($episode);
            }

            // Post-processing (chunking + embeddings)
            $this->processPostTranscription($episode);

            // Pull metrics and log
            $processing = $transcriptData['processing'] ?? [];
            $modelType = $transcriptData['model']['type'] ?? ($transcriptData['model'] ?? 'unknown');
            $segmentCount = count($transcriptData['transcription'] ?? []);

            $episode->update([
                'transcription_status' => 'completed',
                'processing_metadata' => array_merge($episode->processing_metadata ?? [], [
                    'transcribed_at' => now()->toISOString(),
                    'segment_count' => $segmentCount,
                    'whisper_model' => $modelType,
                    'engine' => $this->useFasterWhisper ? 'faster-whisper' : 'whisper.cpp',
                    'auto_ad_removal' => $this->autoRemoveAds,
                    'auto_chunking' => true,
                    'auto_embedding' => true,
                    'processing' => $processing, // <- includes audio_duration_sec, processing_time_sec, rtf, etc.
                ]),
            ]);

            Log::info('Successfully transcribed episode with full processing', [
                'episode_id' => $episode->id,
                'engine' => $this->useFasterWhisper ? 'faster-whisper' : 'whisper.cpp',
                'model' => $modelType,
                'segments' => $segmentCount,
                // Pass through useful performance datapoints if present:
                'audio_duration_sec' => $processing['audio_duration_sec'] ?? null,
                'processing_time_sec' => $processing['processing_time_sec'] ?? null,
                'rtf' => $processing['rtf'] ?? null,
                'cpu_threads' => $processing['cpu_threads'] ?? null,
            ]);

            return true;

        } catch (\Exception $e) {
            $this->handleTranscriptionError($episode, $e);

            throw $e;
        }
    }

    /**
     * Wrapper that lets callers supply a one-off context prompt.
     */
    public function transcribe(string $audioPath, Episode $episode, string $contextPrompt = ''): bool
    {
        $originalPrompt = $this->contextPrompt;
        if ($contextPrompt !== '') {
            $this->contextPrompt = $contextPrompt;
        }

        try {
            return $this->transcribeEpisode($episode);
        } finally {
            $this->contextPrompt = $originalPrompt;
        }
    }

    /** ----------------------------------------------------------------
     *  Engine launching
     *  ---------------------------------------------------------------- */
    private function isTranscriberAvailable(): bool
    {
        if ($this->useFasterWhisper) {
            return file_exists($this->pythonBin) && file_exists($this->fwScript);
        }

        return file_exists($this->whisperCommand) && file_exists($this->whisperModel);
    }

    private function runTranscriber(string $audioPath, string $outputDir, Episode $episode): ?array
    {
        return $this->useFasterWhisper
            ? $this->runFasterWhisper($audioPath, $outputDir)
            : $this->runWhisperCpp($audioPath, $outputDir, $episode);
    }

    /**
     * Faster-Whisper via Python script (scripts/transcribe.py).
     * Writes directly to <outputDir>/<basename>.json
     */
    private function runFasterWhisper(string $audioPath, string $outputDir): ?array
    {
        $baseName = pathinfo($audioPath, PATHINFO_FILENAME);
        $jsonFile = $outputDir.'/'.$baseName.'.json';
        $model = config('services.whisper.model', 'large-v3-turbo');

        // Use the context prompt if set
        $prompt = $this->contextPrompt ?: null;

        $cmd = sprintf(
            '%s %s %s %s %s %s 2>&1',
            escapeshellarg($this->pythonBin),
            escapeshellarg($this->fwScript),
            escapeshellarg($audioPath),
            escapeshellarg($jsonFile),
            escapeshellarg($model),
            $prompt ? escapeshellarg($prompt) : "''"
        );

        $result = $this->runCommand($cmd);
        if ($result['code'] !== 0) {
            $this->logProcessError('Faster-Whisper', $cmd, $result);

            return null;
        }

        if (! file_exists($jsonFile)) {
            Log::error('Faster-Whisper JSON output file not found', [
                'expected_path' => $jsonFile,
                'stdout' => $result['stdout'],
                'stderr' => $result['stderr'],
            ]);

            return null;
        }

        return $this->decodeJsonFile($jsonFile);
    }

    /**
     * whisper.cpp (original path you already had)
     * Produces <outputDir>/<basename>.json with "transcription" array, tokens incl. p/conf, etc.
     */
    private function runWhisperCpp(string $audioPath, string $outputDir, Episode $episode): ?array
    {
        $baseName = pathinfo($audioPath, PATHINFO_FILENAME);
        $outputFile = $outputDir.'/'.$baseName;
        $contextPrompt = $this->generateContextPrompt($episode);

        $cmd = sprintf(
            '%s -m %s -f %s -ojf -l en --max-context 128 --prompt %s -of %s 2>&1',
            $this->whisperCommand,
            escapeshellarg($this->whisperModel),
            escapeshellarg($audioPath),
            escapeshellarg($contextPrompt),
            escapeshellarg($outputFile)
        );

        $result = $this->runCommand($cmd);
        if ($result['code'] !== 0) {
            $this->logProcessError('whisper.cpp', $cmd, $result);

            return null;
        }

        $jsonFile = $outputFile.'.json';
        if (! file_exists($jsonFile)) {
            Log::error('Whisper JSON output file not found', [
                'expected_path' => $jsonFile,
                'stdout' => $result['stdout'],
                'stderr' => $result['stderr'],
            ]);

            return null;
        }

        return $this->decodeJsonFile($jsonFile);
    }

    /** ----------------------------------------------------------------
     *  Segment storage (unchanged shape expected)
     *  ---------------------------------------------------------------- */
    private function storeTranscriptSegments(Episode $episode, array $transcriptData): void
    {
        // Wipe existing
        $episode->transcriptSegments()->delete();

        $cleaningService = app(TranscriptCleaningService::class);

        // Both engines yield "transcription" => [ {...}, ... ]
        $segments = $transcriptData['transcription'] ?? [];

        if (empty($segments)) {
            Log::warning('No transcription segments found in output', [
                'episode_id' => $episode->id,
                'available_keys' => array_keys($transcriptData),
            ]);

            return;
        }

        $segmentIndex = 0;
        $cleanedSegments = 0;
        $ignoredSegments = 0;

        foreach ($segments as $segment) {
            $startTimeMs = $segment['offsets']['from'] ?? 0;
            $endTimeMs = $segment['offsets']['to'] ?? 0;
            $rawText = trim($segment['text'] ?? '');

            if ($rawText === '') {
                continue;
            }

            $cleaningResult = $cleaningService->cleanTranscript($rawText);
            if (! $cleaningResult['keep']) {
                $ignoredSegments++;

                continue;
            }

            $cleanedText = $cleaningResult['text'];
            $startTime = $startTimeMs / 1000.0;
            $endTime = $endTimeMs / 1000.0;

            $confidence = $this->extractConfidenceFromTokens($segment['tokens'] ?? []);
            // If your Python emits avg_confidence, you can prefer/merge it:
            if ($confidence === null && isset($segment['avg_confidence'])) {
                $confidence = (float) $segment['avg_confidence'];
            }

            TranscriptSegment::create([
                'episode_id' => $episode->id,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'text' => $cleanedText,
                'confidence' => $confidence,
                'segment_index' => $segmentIndex++,
            ]);

            $cleanedSegments++;
        }

        Log::info('Transcript segments stored', [
            'episode_id' => $episode->id,
            'segments_processed' => count($segments),
            'segments_saved' => $cleanedSegments,
            'segments_ignored' => $ignoredSegments,
        ]);
    }

    private function extractConfidenceFromTokens(array $tokens): ?float
    {
        if (empty($tokens)) {
            return null;
        }

        $conf = [];
        foreach ($tokens as $t) {
            if (isset($t['confidence']) && is_numeric($t['confidence'])) {
                $conf[] = (float) $t['confidence'];
            } elseif (isset($t['p']) && is_numeric($t['p'])) {
                $conf[] = (float) $t['p'];
            }
        }
        if (empty($conf)) {
            return null;
        }

        return round(array_sum($conf) / count($conf), 3);
    }

    /** ----------------------------------------------------------------
     *  Post-processing (unchanged)
     *  ---------------------------------------------------------------- */
    private function removeAdContent(Episode $episode): void
    {
        try {
            $adRemovalService = app(AdRemovalService::class);
            $result = $adRemovalService->removeAdsFromEpisode($episode);

            if (($result['ads_found'] ?? 0) > 0) {
                Log::info('Ad content automatically removed from episode', [
                    'episode_id' => $episode->id,
                    'ads_found' => $result['ads_found'],
                    'segments_removed' => $result['segments_removed'] ?? null,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to automatically remove ads', [
                'episode_id' => $episode->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function processPostTranscription(Episode $episode): void
    {
        try {
            Log::info('Starting post-transcription processing', [
                'episode_id' => $episode->id,
                'episode_title' => $episode->title,
            ]);

            // Note: Cleaning is already performed during storeTranscriptSegments()
            // so we skip directly to chunking

            // Chunking
            $chunkingService = app(TranscriptChunkingService::class);
            $chunkingResult = $chunkingService->chunkEpisodeSegments($episode);

            Log::info('Transcript chunking completed', [
                'episode_id' => $episode->id,
                'original_count' => $chunkingResult['original_count'] ?? null,
                'chunked_count' => $chunkingResult['chunked_count'] ?? null,
                'reduction_percent' => isset($chunkingResult['original_count'], $chunkingResult['chunked_count'])
                    ? round((1 - $chunkingResult['chunked_count'] / max($chunkingResult['original_count'], 1)) * 100, 1)
                    : null,
            ]);

            // Embeddings
            $pending = $episode->transcriptSegments()->where('embedding_status', 'pending')->get();

            if ($pending->isNotEmpty()) {
                $embeddingResult = $this->embeddingService->processBatchSegments($pending->all());

                Log::info('Embedding generation completed', [
                    'episode_id' => $episode->id,
                    'successful' => $embeddingResult['success'] ?? null,
                    'failed' => $embeddingResult['failed'] ?? null,
                    'success_rate' => ($pending->count() > 0 && isset($embeddingResult['success']))
                        ? round(($embeddingResult['success'] / $pending->count()) * 100, 1).'%'
                        : null,
                ]);
            } else {
                Log::info('No segments need embedding generation', [
                    'episode_id' => $episode->id,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Post-transcription processing failed', [
                'episode_id' => $episode->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /** ----------------------------------------------------------------
     *  Helpers
     *  ---------------------------------------------------------------- */
    private function runCommand(string $cmd): array
    {
        Log::debug('Executing command', ['command' => $cmd]);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = proc_open($cmd, $descriptors, $pipes);
        if (! is_resource($proc)) {
            return ['code' => -1, 'stdout' => '', 'stderr' => 'Failed to start process'];
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $code = proc_close($proc);

        Log::debug('Command finished', [
            'return_code' => $code,
            'stdout' => substr($stdout, 0, 1000),
            'stderr' => substr($stderr, 0, 1000),
        ]);

        return ['code' => $code, 'stdout' => $stdout, 'stderr' => $stderr];
    }

    private function decodeJsonFile(string $path): ?array
    {
        $jsonContent = file_get_contents($path);
        $jsonContent = mb_convert_encoding($jsonContent, 'UTF-8', 'UTF-8');
        $data = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Failed to parse JSON', [
                'json_error' => json_last_error_msg(),
                'json_content_preview' => substr($jsonContent, 0, 500),
            ]);

            return null;
        }

        return $data;
    }

    private function logProcessError(string $label, string $cmd, array $result): void
    {
        Log::error("$label command failed", [
            'command' => $cmd,
            'return_code' => $result['code'],
            'stdout' => substr($result['stdout'], 0, 2000),
            'stderr' => substr($result['stderr'], 0, 2000),
        ]);
    }

    private function generateContextPrompt(Episode $episode): string
    {
        $baseContext = config('podcast.transcription_context',
            'This is a podcast transcription. Please maintain proper capitalization, punctuation, and formatting.'
        );

        // Sanitize the context for safe use with transcription engine
        $baseContext = preg_replace('/[^\w\s.,\-\']/u', '', $baseContext);

        return $baseContext;
    }

    /**
     * Handle a transcription failure: log the error, mark the episode as failed,
     * and record error details in processing metadata.
     */
    private function handleTranscriptionError(Episode $episode, \Exception $e): void
    {
        Log::error('Transcription failed for episode', [
            'episode_id' => $episode->id,
            'episode_title' => $episode->title,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        $episode->update([
            'transcription_status' => 'failed',
            'processing_metadata' => array_merge($episode->processing_metadata ?? [], [
                'transcription_failed_at' => now()->toISOString(),
                'transcription_error' => $e->getMessage(),
                'engine' => $this->useFasterWhisper ? 'faster-whisper' : 'whisper.cpp',
            ]),
        ]);
    }

    /**
     * Enable or disable automatic ad removal after transcription
     */
    public function setAutoRemoveAds(bool $enabled): void
    {
        $this->autoRemoveAds = $enabled;
    }

    /**
     * Return available Whisper models (whisper.cpp path only)
     */
    public function getAvailableModels(): array
    {
        return [
            'tiny' => 'Tiny (~39 MB)',
            'base' => 'Base (~74 MB)',
            'small' => 'Small (~244 MB)',
            'medium' => 'Medium (~769 MB)',
            'large' => 'Large (~1550 MB)',
        ];
    }
}
