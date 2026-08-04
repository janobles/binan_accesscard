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

    /**
     * Voiding a distribution changes coverage()'s served/voided counts, so it
     * must invalidate that batch's cached snapshot the same way a new scan
     * does - otherwise a closed batch (ttl 0, cached forever) never recovers.
     * Asserted against the controller source, matching this file's existing
     * pattern for behaviour that isn't practical to exercise without a full
     * DB-backed request (see SubsidyDistributionVoidTest::testVoidIsNotADelete).
     */
    public function testVoidDistributionInvalidatesItsOwnBatchCache(): void
    {
        $source = file_get_contents(APPPATH . 'Controllers/Admin/DistributionController.php');
        $body   = substr($source, strpos($source, 'function voidDistribution'));
        $body   = substr($body, 0, strpos($body, "\n    }"));

        $this->assertStringContainsString('forgetBatch', $body);
        // Must key off the voided row's own batch_id, not a freshly-looked-up
        // active batch - a void can land on a closed batch.
        $this->assertStringContainsString("row['batch_id']", $body);
    }
}
