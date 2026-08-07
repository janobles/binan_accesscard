<?php

namespace Tests\Unit;

use App\Models\Scanner\SubsidyStatsModel;
use CodeIgniter\Test\CIUnitTestCase;

final class SubsidyStatsByDayTest extends CIUnitTestCase
{
    private function createSchema(): void
    {
        $forge = \Config\Database::forge();

        $forge->addField([
            'batch_id' => ['type' => 'INTEGER'],
            'headID'   => ['type' => 'INTEGER'],
        ]);
        $forge->addPrimaryKey(['batch_id', 'headID']);
        $forge->createTable('batch_eligibility', true);

        $forge->addField([
            'distribution_id' => ['type' => 'INTEGER', 'auto_increment' => true],
            'memberID'        => ['type' => 'INTEGER'],
            'batch_id'        => ['type' => 'INTEGER'],
            'claim_date'      => ['type' => 'DATE', 'null' => true],
            'dt_voided'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addPrimaryKey('distribution_id');
        $forge->createTable('subsidy_distribution', true);

        $forge->addField([
            'batch_id'       => ['type' => 'INTEGER'],
            'eligible_count' => ['type' => 'INTEGER', 'default' => 0],
        ]);
        $forge->addPrimaryKey('batch_id');
        $forge->createTable('distribution_batch', true);
    }

    private function dropSchema(): void
    {
        $forge = \Config\Database::forge();
        foreach (['subsidy_distribution', 'batch_eligibility', 'distribution_batch'] as $table) {
            $forge->dropTable($table, true);
        }
    }

    public function testEmptyListOnUnknownBatch(): void
    {
        $this->assertSame([], (new SubsidyStatsModel())->servedByDay(0));
    }

    /**
     * The pilot's shape at small scale: three days, descending, plus a voided
     * row and an off-roster row that must stay invisible. The per-day figures
     * have to sum to what coverage() calls served, because the spec promises
     * the bars add up to the Served card.
     */
    public function testDaysAreOrderedLabelledAndSumToServed(): void
    {
        $this->createSchema();
        $db = db_connect();

        $db->table('distribution_batch')->insert(['batch_id' => 1, 'eligible_count' => 7]);

        $heads = range(1, 7);
        foreach ($heads as $head) {
            $db->table('batch_eligibility')->insert(['batch_id' => 1, 'headID' => $head]);
        }

        $rows = [
            [1, '2026-03-01'], [2, '2026-03-01'], [3, '2026-03-01'],
            [4, '2026-03-02'], [5, '2026-03-02'],
            [6, '2026-03-03'],
        ];
        foreach ($rows as [$member, $date]) {
            $db->table('subsidy_distribution')->insert([
                'memberID' => $member, 'batch_id' => 1, 'claim_date' => $date,
            ]);
        }
        $db->table('subsidy_distribution')->insert([
            'memberID' => 7, 'batch_id' => 1, 'claim_date' => '2026-03-02',
            'dt_voided' => date('Y-m-d H:i:s'),
        ]);
        $db->table('subsidy_distribution')->insert([
            'memberID' => 99, 'batch_id' => 1, 'claim_date' => '2026-03-02',
        ]);

        $out = (new SubsidyStatsModel())->servedByDay(1);

        $this->assertCount(3, $out);
        $this->assertSame('Day 1', $out[0]['label']);
        $this->assertSame('Day 3', $out[2]['label']);
        $this->assertSame('2026-03-01', $out[0]['date']);
        $this->assertSame([3, 2, 1], array_column($out, 'served'));

        $served = (new SubsidyStatsModel())->coverage(1)['served'];
        $this->assertSame(
            $served,
            array_sum(array_column($out, 'served')),
            'The day bars must sum to the Served card.'
        );

        $this->dropSchema();
    }

    public function testSingleDayBatchReturnsOneRow(): void
    {
        $this->createSchema();
        $db = db_connect();

        $db->table('batch_eligibility')->insert(['batch_id' => 1, 'headID' => 1]);
        $db->table('subsidy_distribution')->insert([
            'memberID' => 1, 'batch_id' => 1, 'claim_date' => '2026-03-01',
        ]);

        $out = (new SubsidyStatsModel())->servedByDay(1);

        $this->assertCount(1, $out);
        $this->assertSame('Day 1', $out[0]['label']);

        $this->dropSchema();
    }
}
