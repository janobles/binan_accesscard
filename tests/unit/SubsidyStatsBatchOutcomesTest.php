<?php

namespace Tests\Unit;

use App\Models\Scanner\SubsidyStatsModel;
use CodeIgniter\Test\CIUnitTestCase;

final class SubsidyStatsBatchOutcomesTest extends CIUnitTestCase
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
    }

    private function dropSchema(): void
    {
        $forge = \Config\Database::forge();
        foreach (['subsidy_distribution', 'batch_eligibility'] as $table) {
            $forge->dropTable($table, true);
        }
    }

    public function testEmptyMapOnNoData(): void
    {
        $this->assertSame([], (new SubsidyStatsModel())->servedByBatch());
    }

    /**
     * Two batches, plus the two rows that must not be counted: a voided
     * distribution and one for a family that was never on the roster. Both
     * are excluded by coverage(), so they must be excluded here too or the
     * Overview and Distribution tabs would disagree about the same batch.
     */
    public function testServedPerBatchMatchesCoverageSemantics(): void
    {
        $this->createSchema();
        $db = db_connect();

        foreach ([[1, 1], [1, 2], [1, 3], [2, 4]] as [$batch, $head]) {
            $db->table('batch_eligibility')->insert(['batch_id' => $batch, 'headID' => $head]);
        }

        $db->table('subsidy_distribution')->insert(['memberID' => 1, 'batch_id' => 1]);
        $db->table('subsidy_distribution')->insert(['memberID' => 2, 'batch_id' => 1]);
        $db->table('subsidy_distribution')->insert([
            'memberID' => 3, 'batch_id' => 1, 'dt_voided' => date('Y-m-d H:i:s'),
        ]);
        // Never on batch 1's roster.
        $db->table('subsidy_distribution')->insert(['memberID' => 99, 'batch_id' => 1]);
        $db->table('subsidy_distribution')->insert(['memberID' => 4, 'batch_id' => 2]);

        $out = (new SubsidyStatsModel())->servedByBatch();

        $this->assertSame(2, $out[1] ?? 0, 'Voided and off-roster rows must not count.');
        $this->assertSame(1, $out[2] ?? 0);

        $this->dropSchema();
    }
}
