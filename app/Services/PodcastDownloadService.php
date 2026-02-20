<?php

namespace App\Services;

use App\Models\Episode;
use App\Services\Storage\PodcastFileManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PodcastDownloadService
{
    public function download(Episode $episode): ?string
    {
        try {
            // Validate the audio URL before attempting download
            $url = $episode->audio_url;

            if (empty($url)) {
                Log::error('Episode has no audio URL', ['episode_id' => $episode->id]);
                $this->handleDownloadError($episode, 'No audio URL provided');

                return null;
            }

            if (! filter_var($url, FILTER_VALIDATE_URL)) {
                Log::error('Invalid audio URL format', ['episode_id' => $episode->id, 'url' => $url]);
                $this->handleDownloadError($episode, 'Invalid audio URL format');

                return null;
            }

            // Only allow http/https schemes for security
            $scheme = parse_url($url, PHP_URL_SCHEME);
            if (! in_array(strtolower($scheme ?? ''), ['http', 'https'])) {
                Log::error('Invalid URL scheme - only http/https allowed', [
                    'episode_id' => $episode->id,
                    'scheme' => $scheme,
                ]);
                $this->handleDownloadError($episode, 'Invalid URL scheme - only http/https allowed');

                return null;
            }

            Log::info("Starting download for episode {$episode->id}: {$episode->title}");

            $episode->update(['download_status' => 'downloading']);

            // Use the centralized file manager to get the download path
            $relativePath = PodcastFileManager::generateAudioDownloadPath($episode);
            $absolutePath = PodcastFileManager::getAudioDownloadAbsolutePath($episode);

            // Ensure the directory exists
            PodcastFileManager::ensureDirectoriesExist();

            // Stream download directly to file to avoid memory issues
            $response = Http::timeout(600)->sink($absolutePath)->get($episode->audio_url);

            if (! $response->successful()) {
                Log::error("Failed to download episode: HTTP {$response->status()}", [
                    'episode_id' => $episode->id,
                    'audio_url' => $episode->audio_url,
                    'status' => $response->status(),
                ]);
                $this->handleDownloadError($episode, "HTTP {$response->status()}");

                return null;
            }

            // Check if file was actually created
            if (! file_exists($absolutePath)) {
                Log::error('Downloaded file does not exist', [
                    'episode_id' => $episode->id,
                    'expected_path' => $absolutePath,
                    'audio_url' => $episode->audio_url,
                ]);
                $this->handleDownloadError($episode, "File not created at {$absolutePath}");

                return null;
            }

            // Get file size from the actual file
            $fileSize = filesize($absolutePath);
            $audioFormat = $this->detectAudioFormat($absolutePath);
            $duration = $this->getAudioDuration($absolutePath);

            // Update episode with file information
            $episode->update([
                'download_status' => 'completed',
                'local_audio_path' => $relativePath,
                'file_size_bytes' => $fileSize,
                'audio_format' => $audioFormat,
                'duration_seconds' => $duration,
                'processing_metadata' => array_merge($episode->processing_metadata ?? [], [
                    'downloaded_at' => now()->toISOString(),
                    'file_size' => $fileSize,
                    'audio_format' => $audioFormat,
                ]),
            ]);

            $localPath = PodcastFileManager::getEpisodeAudioRelativePath($episode);
            Log::info("Successfully downloaded episode {$episode->id} to {$localPath}");

            return $localPath;

        } catch (\Exception $e) {
            Log::error("Download failed for episode {$episode->id}: ".$e->getMessage(), [
                'episode_id' => $episode->id,
                'audio_url' => $episode->audio_url,
                'exception' => $e,
            ]);
            $this->handleDownloadError($episode, $e->getMessage());

            return null;
        }
    }

    public function getLocalPath(Episode $episode): ?string
    {
        return PodcastFileManager::getEpisodeAudioPath($episode);
    }

    public function deleteLocalFile(Episode $episode): bool
    {
        try {
            return PodcastFileManager::deleteEpisodeAudio($episode);
        } catch (\Exception $e) {
            Log::error('Failed to delete local file for episode', [
                'episode_id' => $episode->id,
                'local_audio_path' => $episode->local_audio_path,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function handleDownloadError(Episode $episode, string $message): void
    {
        $episode->update([
            'download_status' => 'failed',
            'processing_metadata' => array_merge($episode->processing_metadata ?? [], [
                'download_error' => [
                    'message' => $message,
                    'occurred_at' => now()->toISOString(),
                ],
            ]),
        ]);
    }

    private function detectAudioFormat(string $filePath): string
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        // Map extensions to formats
        $formatMap = [
            'mp3' => 'mp3',
            'wav' => 'wav',
            'm4a' => 'm4a',
            'aac' => 'aac',
            'ogg' => 'ogg',
            'flac' => 'flac',
        ];

        return $formatMap[$extension] ?? 'unknown';
    }

    private function getAudioDuration(string $filePath): ?int
    {
        try {
            // Try to get duration using ffprobe if available
            // Check if ffprobe exists first to avoid error output
            $ffprobe = shell_exec('which ffprobe');
            if (! $ffprobe) {
                return null;
            }

            $command = 'ffprobe -v quiet -show_entries format=duration -of csv="p=0" '.escapeshellarg($filePath);
            $output = shell_exec($command);

            if ($output && is_numeric(trim($output))) {
                return (int) round(floatval(trim($output)));
            }

            return null;
        } catch (\Exception $e) {
            Log::warning('Could not determine audio duration', [
                'file_path' => $filePath,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
