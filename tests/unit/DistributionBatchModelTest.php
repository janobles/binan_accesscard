<?php

namespace Tests\Unit;

use App\Models\Scanner\DistributionBatchModel;
use CodeIgniter\Test\CIUnitTestCase;

final class DistributionBatchModelTest extends CIUnitTestCase
{
    public function testActiveBatchReturnsNullOrArray(): void
    {
        $out = (new DistributionBatchModel())->activeBatch();
        $this->assertTrue($out === null || is_array($out));
    }

    public function testOpenRejectsBlankName(): void
    {
        $this->assertSame(0, (new DistributionBatchModel())->open('   ', 1, 1));
    }

    public function testOpenRequiresSubsidyType(): void
    {
        if (! extension_loaded('sqlite3')) {
            $this->markTestSkipped('sqlite3 not available');
        }
        $m = new DistributionBatchModel();
        $this->assertSame(0, $m->open('Batch A', 0, 1), 'subsidy type 0 must refuse');
    }

    public function testCloseRejectsNonPositiveId(): void
    {
        $this->assertFalse((new DistributionBatchModel())->close(0));
    }

    public function testAllBatchesReturnsArray(): void
    {
        $this->assertIsArray((new DistributionBatchModel())->allBatches());
    }

    public function testOpenStillRejectsBlankNameWithFilters(): void
    {
        $this->assertSame(0, (new DistributionBatchModel())->open('   ', 1, 1, [1], [2]));
    }

    public function testFiltersForReturnsBothKeys(): void
    {
        $out = (new DistributionBatchModel())->filtersFor(0);
        $this->assertArrayHasKey('barangays', $out);
        $this->assertArrayHasKey('sectors', $out);
    }

    public function testRebuildRosterRefusesNonPositiveId(): void
    {
        $this->assertSame(0, (new DistributionBatchModel())->rebuildRoster(0));
    }
}
