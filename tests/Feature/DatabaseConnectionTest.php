<?php

namespace Tests\Feature;

use App\Models\Podcast;
use Database\Seeders\TestDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uses_testing_database()
    {
        $dbName = DB::connection()->getDatabaseName();
        $this->assertEquals('podcast_archive_testing', $dbName, 'Tests must run against podcast_archive_testing, not the production database.');
    }

    public function test_it_can_seed_test_data()
    {
        $this->seed(TestDatabaseSeeder::class);

        $this->assertDatabaseCount('podcasts', 1);
        $this->assertDatabaseCount('episodes', 3);

        $podcast = Podcast::first();
        $this->assertEquals('Test Podcast', $podcast->title);
    }
}
