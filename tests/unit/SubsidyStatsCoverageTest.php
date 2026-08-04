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

    private function createSchema(): void
    {
        $forge = \Config\Database::forge();

        $forge->addField([
            'batch_id' => ['type' => 'INTEGER', 'auto_increment' => true],
            'eligible_count' => ['type' => 'INTEGER', 'default' => 0],
        ]);
        $forge->addPrimaryKey('batch_id');
        $forge->createTable('distribution_batch', true);

        $forge->addField([
            'batch_id' => ['type' => 'INTEGER'],
            'headID'   => ['type' => 'INTEGER'],
        ]);
        $forge->addPrimaryKey(['batch_id', 'headID']);
        $forge->createTable('batch_eligibility', true);

        $forge->addField([
            'distribution_id' => ['type' => 'INTEGER', 'auto_increment' => true],
            'control_no'      => ['type' => 'INTEGER'],
            'memberID'        => ['type' => 'INTEGER'],
            'batch_id'        => ['type' => 'INTEGER'],
            'dt_created'      => ['type' => 'DATETIME', 'null' => true],
            'dt_voided'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addPrimaryKey('distribution_id');
        $forge->createTable('subsidy_distribution', true);
    }

    private function dropSchema(): void
    {
        $forge = \Config\Database::forge();

        foreach (['subsidy_distribution', 'batch_eligibility', 'distribution_batch'] as $table) {
            $forge->dropTable($table, true);
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
        $this->createSchema();
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

        $this->dropSchema();
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
