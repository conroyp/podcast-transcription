<?php

namespace App\Console\Commands;

use App\Models\Episode;
use Illuminate\Console\Command;

class UpdateEpisodeTypes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'episodes:update-types';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update episode types for all existing episodes based on their titles';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Updating episode types for all episodes...');

        $episodes = Episode::all();
        $updated = 0;
        $midweekCount = 0;
        $interviewCount = 0;

        foreach ($episodes as $episode) {
            $oldType = $episode->episode_type;
            $newType = Episode::detectEpisodeType($episode->title);

            if ($oldType !== $newType) {
                $episode->episode_type = $newType;
                $episode->save();
                $updated++;

                $this->line("Updated: {$episode->title} -> {$newType}");
            }

            if ($newType === 'midweek_mayhem') {
                $midweekCount++;
            } else {
                $interviewCount++;
            }
        }

        $this->info('Episode type update complete!');
        $this->table(['Type', 'Count'], [
            ['Interviews', $interviewCount],
            ['Midweek Mayhem', $midweekCount],
            ['Total', $episodes->count()],
            ['Updated', $updated],
        ]);

        return Command::SUCCESS;
    }
}
