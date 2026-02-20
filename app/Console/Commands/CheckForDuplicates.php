<?php

namespace App\Console\Commands;

use App\Models\TranscriptSegment;
use App\Services\TranscriptCleaningService;
use Illuminate\Console\Command;

class CheckForDuplicates extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'transcripts:check-for-duplicates';

    /**
     * The console command description.
     */
    protected $description = 'Check for duplication within transcriptions';

    private TranscriptCleaningService $cleaningService;

    private array $usageStats = [];

    private array $ignoredStats = [];

    private int $totalProcessed = 0;

    private int $totalChanged = 0;

    private int $totalIgnored = 0;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Get all transcript segments, grouped together by episode, where the same text appears more than once
        $this->info('🔍 Checking for duplicate transcript segments...');
        $duplicates = TranscriptSegment::select('text', 'episode_id')
            ->groupBy('text', 'episode_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();
        if ($duplicates->isEmpty()) {
            $this->info('✅ No duplicate transcript segments found');

            return 0;
        }
        $this->info('⚠️ Found duplicate transcript segments:');
        $this->table(['Episode ID', 'Episode Title', 'Text', 'Count'], $duplicates->map(function ($segment) {
            return [
                $segment->episode_id,
                $segment->episode ? $segment->episode->title : 'Unknown Episode',
                substr($segment->text, 0, 50).'...',
                TranscriptSegment::where('text', $segment->text)
                    ->where('episode_id', $segment->episode_id)
                    ->count(),
            ];
        })->toArray());
        $this->info('Please review these duplicates and take appropriate action.');

        return 0;

    }
}
