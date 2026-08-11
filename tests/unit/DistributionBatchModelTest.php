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

    public function testCloseRejectsNonPositiveId(): void
    {
        $this->assertFalse((new DistributionBatchModel())->close(0));
    }

    public function testAllBatchesReturnsArray(): void
    {
        $this->assertIsArray((new DistributionBatchModel())->allBatches());
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

    public function testFiltersForNonexistentPositiveIdReturnsEmpty(): void
    {
        $out = (new DistributionBatchModel())->filtersFor(999999999);
        $this->assertSame(['barangays' => [], 'sectors' => []], $out);
    }

    public function testRebuildRosterRefusesNonexistentPositiveId(): void
    {
        $this->assertSame(0, (new DistributionBatchModel())->rebuildRoster(999999999));
    }

    public function testSaveScheduleRejectsBlankName(): void
    {
        $this->assertSame(0, (new DistributionBatchModel())->saveSchedule([
            'batch_id' => 0, 'name' => '   ', 'subsidy_type_id' => 1,
            'scheduled_start' => '2026-08-20', 'scheduled_end' => '2026-08-20',
        ], 1));
    }

    public function testSaveScheduleRequiresSubsidyType(): void
    {
        $this->assertSame(0, (new DistributionBatchModel())->saveSchedule([
            'batch_id' => 0, 'name' => 'Batch A', 'subsidy_type_id' => 0,
            'scheduled_start' => '2026-08-20', 'scheduled_end' => '2026-08-20',
        ], 1));
    }

    public function testLastScanAtRefusesNonPositiveId(): void
    {
        $this->assertNull((new DistributionBatchModel())->lastScanAt(0));
    }

    public function testDeleteScheduleRefusesNonPositiveId(): void
    {
        $this->assertFalse((new DistributionBatchModel())->deleteSchedule(0));
    }
}
