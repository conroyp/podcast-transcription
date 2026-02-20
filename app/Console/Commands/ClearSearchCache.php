<?php

namespace App\Console\Commands;

use App\Services\SearchCacheService;
use Illuminate\Console\Command;

class ClearSearchCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'search:clear-cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear all search result cache';

    private SearchCacheService $searchCacheService;

    public function __construct(SearchCacheService $searchCacheService)
    {
        parent::__construct();
        $this->searchCacheService = $searchCacheService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Clearing search cache...');

        try {
            $this->searchCacheService->clearAllCache();
            $this->info('✅ Search cache cleared successfully!');

            return 0;
        } catch (\Exception $e) {
            $this->error('❌ Failed to clear search cache: '.$e->getMessage());

            return 1;
        }
    }
}
