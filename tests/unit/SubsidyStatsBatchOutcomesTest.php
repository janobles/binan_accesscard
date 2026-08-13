<?php

namespace Tests\Unit;

use App\Models\Scanner\SubsidyStatsModel;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Database\DumpSchema;
use Tests\Support\Database\ReferentialFixture;

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

    /** One claim, with the columns the dump requires. */
    private function claim(int $memberId, int $batchId, array $overrides = []): array
    {
        return array_merge([
            'control_no'      => 100 + $memberId,
            'memberID'        => $memberId,
            'subsidy_type_id' => 1,
            'claim_date'      => '2026-03-01',
            'batch_id'        => $batchId,
        ], $overrides);
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

        // Head 99's claim is the off-roster one, so it needs a card too.
        ReferentialFixture::claimParents($db, [1, 2, 3, 4, 99]);

        foreach ([1, 2] as $batchId) {
            $db->table('distribution_batch')->insert([
                'batch_id'        => $batchId,
                'name'            => 'Batch ' . $batchId,
                'subsidy_type_id' => 1,
                'eligible_count'  => $batchId === 1 ? 3 : 1,
            ]);
        }

        foreach ([[1, 1], [1, 2], [1, 3], [2, 4]] as [$batch, $head]) {
            $db->table('batch_eligibility')->insert(['batch_id' => $batch, 'headID' => $head]);
        }

        $db->table('subsidy_distribution')->insert($this->claim(1, 1));
        $db->table('subsidy_distribution')->insert($this->claim(2, 1));
        $db->table('subsidy_distribution')->insert(
            $this->claim(3, 1, ['dt_voided' => date('Y-m-d H:i:s')])
        );
        // Never on batch 1's roster.
        $db->table('subsidy_distribution')->insert($this->claim(99, 1));
        $db->table('subsidy_distribution')->insert($this->claim(4, 2));

        $out = (new SubsidyStatsModel())->servedByBatch();

        $this->assertSame(2, $out[1] ?? 0, 'Voided and off-roster rows must not count.');
        $this->assertSame(1, $out[2] ?? 0);
    }
}
