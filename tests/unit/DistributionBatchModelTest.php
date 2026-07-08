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
        $this->assertSame(0, (new DistributionBatchModel())->open('   ', 1));
    }

    public function testCloseRejectsNonPositiveId(): void
    {
        $this->assertFalse((new DistributionBatchModel())->close(0));
    }

    public function testAllBatchesReturnsArray(): void
    {
        $this->assertIsArray((new DistributionBatchModel())->allBatches());
    }
}
