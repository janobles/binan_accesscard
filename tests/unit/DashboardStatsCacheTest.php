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

    public function testLogActionDeletesStatsCache(): void
    {
        cache()->save(DashboardModel::STATS_CACHE_KEY, ['families' => 1], 60);

        // Without a DB the insert inside logAction throws. The cache delete
        // sits before the insert, so it must have run even then; that ordering
        // is what keeps the tiles fresh no matter how the audit write ends.
        try {
            (new \App\Models\Audit\AuditTrailsModel())
                ->logAction(1, null, 'TEST_ACTION', 'cache invalidation test');
        } catch (\Throwable) {
            // Expected in the no-DB unit test environment.
        }

        $this->assertNull(cache()->get(DashboardModel::STATS_CACHE_KEY));
    }

    public function testStatsPopulatesCacheOnMiss(): void
    {
        $stats = (new DashboardModel())->stats();

        $cached = cache()->get(DashboardModel::STATS_CACHE_KEY);
        $this->assertIsArray($cached);
        $this->assertSame($stats, $cached);
    }
}
