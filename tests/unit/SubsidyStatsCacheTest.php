<?php

namespace Tests\Unit;

use App\Models\Scanner\SubsidyStatsModel;
use CodeIgniter\Test\CIUnitTestCase;

final class SubsidyStatsCacheTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        cache()->delete(SubsidyStatsModel::cacheKey(7));
        parent::tearDown();
    }

    public function testCacheKeyIsPerBatch(): void
    {
        $this->assertSame('subsidy_batch_stats_7', SubsidyStatsModel::cacheKey(7));
    }

    public function testForgetBatchClearsTheKey(): void
    {
        cache()->save(SubsidyStatsModel::cacheKey(7), ['x' => 1], 60);
        (new SubsidyStatsModel())->forgetBatch(7);
        $this->assertNull(cache(SubsidyStatsModel::cacheKey(7)));
    }
}
