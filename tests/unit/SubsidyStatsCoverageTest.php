<?php

namespace Tests\Unit;

use App\Models\Scanner\SubsidyStatsModel;
use CodeIgniter\Test\CIUnitTestCase;

final class SubsidyStatsCoverageTest extends CIUnitTestCase
{
    public function testCoverageShapeOnUnknownBatch(): void
    {
        $out = (new SubsidyStatsModel())->coverage(0);
        foreach (['eligible', 'served', 'remaining', 'coverage', 'voided'] as $key) {
            $this->assertArrayHasKey($key, $out);
            $this->assertIsInt($out[$key]);
        }
    }

    public function testCoveragePercentNeverExceedsHundred(): void
    {
        $this->assertLessThanOrEqual(100, (new SubsidyStatsModel())->coverage(1)['coverage']);
    }

    public function testRemainingReturnsList(): void
    {
        $this->assertIsArray((new SubsidyStatsModel())->remaining(1));
    }

    public function testStatsDoNotCountInPhp(): void
    {
        $source = file_get_contents(APPPATH . 'Models/Scanner/SubsidyStatsModel.php');
        $this->assertStringNotContainsString('count($b->get()->getResultArray())', $source);
    }

    public function testBarangayRollupDoesNotParseAddress(): void
    {
        $source = file_get_contents(APPPATH . 'Models/Scanner/SubsidyStatsModel.php');
        $this->assertStringNotContainsString('SUBSTRING_INDEX', $source);
    }
}
