<?php

namespace Tests\Unit;

use App\Models\DashboardModel;
use CodeIgniter\Test\CIUnitTestCase;

final class DashboardStatsCacheTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        cache()->delete(DashboardModel::STATS_CACHE_KEY);
    }

    protected function tearDown(): void
    {
        cache()->delete(DashboardModel::STATS_CACHE_KEY);
        parent::tearDown();
    }

    public function testStatsReturnsCachedValueWhenPresent(): void
    {
        // Prime the cache with a sentinel. If stats() reads the cache it
        // returns this untouched instead of recounting from the DB.
        $sentinel = ['families' => 7, 'members' => 21, 'sectors' => 3, 'assistance' => 5];
        cache()->save(DashboardModel::STATS_CACHE_KEY, $sentinel, 60);

        $this->assertSame($sentinel, (new DashboardModel())->stats());
    }

    public function testStatsPopulatesCacheOnMiss(): void
    {
        (new DashboardModel())->stats();

        $cached = cache()->get(DashboardModel::STATS_CACHE_KEY);
        $this->assertIsArray($cached);
        $this->assertArrayHasKey('families', $cached);
    }
}
