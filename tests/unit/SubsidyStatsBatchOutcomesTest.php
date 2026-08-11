<?php

namespace Tests\Unit;

use App\Models\Scanner\SubsidyStatsModel;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Database\DumpSchema;

/**
 * Served-per-batch, which the Overview and Distribution tabs both read.
 *
 * Schema comes from the dump; rows are only the ones each case asserts on.
 */
final class SubsidyStatsBatchOutcomesTest extends CIUnitTestCase
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
    }
}
