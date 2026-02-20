<?php

namespace App\Console\Commands;

use App\Models\Episode;
use App\Services\Storage\PodcastFileManager;
use Illuminate\Console\Command;

class DebugEpisodeCommand extends Command
{
    protected $signature = 'podcast:debug {episode_id}';

    protected $description = 'Debug episode file paths and status';

    public function handle(): int
    {
        $episodeId = $this->argument('episode_id');
        $episode = Episode::find($episodeId);

        if (! $episode) {
            $this->error("Episode {$episodeId} not found");

            return self::FAILURE;
        }

        $this->info('Episode Debug Info:');
        $this->line("ID: {$episode->id}");
        $this->line("Title: {$episode->title}");
        $this->line("Download Status: {$episode->download_status}");
        $this->line('Local Audio Path: '.($episode->local_audio_path ?: 'NULL'));

        if ($episode->local_audio_path) {
            $fullPath = PodcastFileManager::getEpisodeAudioPath($episode);
            $this->line('Full Path: '.($fullPath ?: 'Could not determine path'));
            $this->line('File Exists: '.(PodcastFileManager::episodeAudioExists($episode) ? 'YES' : 'NO'));

            if ($fullPath && file_exists($fullPath)) {
                $this->line('File Size: '.filesize($fullPath).' bytes');
            }
        }

        $this->line('isDownloaded(): '.($episode->isDownloaded() ? 'TRUE' : 'FALSE'));

        return self::SUCCESS;
    }
}
