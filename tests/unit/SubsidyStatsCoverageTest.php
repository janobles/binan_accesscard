<?php

namespace Tests\Unit;

use App\Models\Scanner\SubsidyStatsModel;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Database\DumpSchema;

/**
 * Coverage: eligible, served, remaining and the percentage they produce.
 *
 * Schema comes from the dump; rows are only the ones each case asserts on.
 */
final class SubsidyStatsCoverageTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DumpSchema::create(db_connect());
    }

    protected function tearDown(): void
    {
        DumpSchema::drop(db_connect());
        parent::tearDown();
    }

    public function testCoverageShapeOnUnknownBatch(): void
    {
        $out = (new SubsidyStatsModel())->coverage(0);
        foreach (['eligible', 'served', 'remaining', 'coverage', 'voided'] as $key) {
            $this->assertArrayHasKey($key, $out);
            $this->assertIsInt($out[$key]);
        }
    }

    /**
     * Task 6's testCoveragePercentNeverExceedsHundred asserted the property
     * on empty data, which passes on any denominator including a broken one.
     * This seeds a roster of 2, one served on-roster, one voided on-roster,
     * and one distribution for a memberID never granted eligibility (an
     * off-roster scan, which the scan path must still be free to log). Fix 1
     * scopes served/voided to batch_eligibility via a join, so the off-roster
     * row must be invisible to coverage() and the percentage must stay <= 100.
     */
    public function testCoveragePercentNeverExceedsHundred(): void
    {
        $db = db_connect();

        $db->table('distribution_batch')->insert(['batch_id' => 1, 'eligible_count' => 2]);
        $db->table('batch_eligibility')->insert(['batch_id' => 1, 'headID' => 1]);
        $db->table('batch_eligibility')->insert(['batch_id' => 1, 'headID' => 2]);

        // On-roster served.
        $db->table('subsidy_distribution')->insert([
            'control_no' => 1, 'memberID' => 1, 'batch_id' => 1, 'dt_created' => date('Y-m-d H:i:s'),
        ]);
        // On-roster voided.
        $db->table('subsidy_distribution')->insert([
            'control_no' => 2, 'memberID' => 2, 'batch_id' => 1,
            'dt_created' => date('Y-m-d H:i:s'), 'dt_voided' => date('Y-m-d H:i:s'),
        ]);
        // Off-roster: memberID 3 was never granted eligibility for batch 1.
        $db->table('subsidy_distribution')->insert([
            'control_no' => 3, 'memberID' => 3, 'batch_id' => 1, 'dt_created' => date('Y-m-d H:i:s'),
        ]);

        $out = (new SubsidyStatsModel())->coverage(1);

        $this->assertSame(1, $out['served'], 'The off-roster distribution must not count as served.');
        $this->assertSame(1, $out['voided']);
        $this->assertSame(2, $out['eligible']);
        $this->assertLessThanOrEqual(100, $out['coverage']);
        $this->assertSame(50, $out['coverage']);
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
