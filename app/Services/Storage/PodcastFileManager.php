<?php

namespace App\Services\Storage;

use App\Models\Episode;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PodcastFileManager
{
    private const AUDIO_DIRECTORY = 'podcasts/audio';

    private const TRANSCRIPT_DIRECTORY = 'podcasts/transcripts';

    /**
     * Get the absolute path to an episode's audio file
     */
    public static function getEpisodeAudioPath(Episode $episode): ?string
    {
        if (! $episode->local_audio_path) {
            return null;
        }

        return storage_path('app/'.$episode->local_audio_path);
    }

    /**
     * Get the relative storage path for an episode's audio file
     */
    public static function getEpisodeAudioRelativePath(Episode $episode): ?string
    {
        if (! $episode->local_audio_path) {
            return null;
        }

        return $episode->local_audio_path;
    }

    /**
     * Check if an episode's audio file exists
     */
    public static function episodeAudioExists(Episode $episode): bool
    {
        $path = self::getEpisodeAudioPath($episode);

        return $path && file_exists($path);
    }

    /**
     * Generate the storage path for downloading a new episode
     */
    public static function generateAudioDownloadPath(Episode $episode): string
    {
        $filename = self::generateAudioFilename($episode);

        return 'private/'.self::AUDIO_DIRECTORY.'/'.$filename;
    }

    /**
     * Get the absolute path for downloading (to be used with Guzzle sink)
     */
    public static function getAudioDownloadAbsolutePath(Episode $episode): string
    {
        $relativePath = self::generateAudioDownloadPath($episode);

        return storage_path('app/'.$relativePath);
    }

    /**
     * Generate the directory path for episode transcripts
     */
    public static function getTranscriptDirectory(Episode $episode): string
    {
        return Storage::path(self::TRANSCRIPT_DIRECTORY.'/'.$episode->id);
    }

    /**
     * Generate filename for an audio file
     */
    public static function generateAudioFilename(Episode $episode): string
    {
        $podcastSlug = Str::slug($episode->podcast->title);
        $episodeSlug = Str::slug($episode->title);
        $timestamp = $episode->published_at->format('Y-m-d');

        // Extract extension from original URL, default to mp3
        $extension = pathinfo(parse_url($episode->audio_url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'mp3';

        return "{$podcastSlug}-{$timestamp}-{$episodeSlug}.{$extension}";
    }

    /**
     * Clean up an episode's audio file
     */
    public static function deleteEpisodeAudio(Episode $episode): bool
    {
        if (! $episode->local_audio_path) {
            return true;
        }

        $relativePath = self::getEpisodeAudioRelativePath($episode);
        if (! $relativePath) {
            return true;
        }

        // Use Storage facade for consistent deletion
        return Storage::delete($relativePath);
    }

    /**
     * Get file size of an episode's audio file
     */
    public static function getEpisodeAudioSize(Episode $episode): ?int
    {
        $path = self::getEpisodeAudioPath($episode);

        return $path && file_exists($path) ? filesize($path) : null;
    }

    /**
     * Get all podcast storage directories
     */
    public static function getStorageDirectories(): array
    {
        return [
            'audio' => self::AUDIO_DIRECTORY,
            'transcripts' => self::TRANSCRIPT_DIRECTORY,
        ];
    }

    /**
     * Ensure all necessary directories exist
     */
    public static function ensureDirectoriesExist(): void
    {
        $directories = [
            'private/'.self::AUDIO_DIRECTORY,
            'private/'.self::TRANSCRIPT_DIRECTORY,
        ];

        foreach ($directories as $dir) {
            $absolutePath = Storage::path($dir);
            if (! is_dir($absolutePath)) {
                mkdir($absolutePath, 0755, true);
            }
        }
    }
}
