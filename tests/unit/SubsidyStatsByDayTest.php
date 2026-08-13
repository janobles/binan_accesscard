<?php

namespace Tests\Unit;

use App\Models\Scanner\SubsidyStatsModel;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Database\DumpSchema;
use Tests\Support\Database\ReferentialFixture;

/**
 * The per-day bars behind the Distribution tab.
 *
 * Schema comes from the dump; rows are only the ones each case asserts on.
 */
final class SubsidyStatsByDayTest extends CIUnitTestCase
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

    /** One claim against batch 1, with the columns the dump requires. */
    private function claim(int $memberId, string $date, array $overrides = []): array
    {
        return array_merge([
            'control_no'      => 100 + $memberId,
            'memberID'        => $memberId,
            'subsidy_type_id' => 1,
            'claim_date'      => $date,
            'batch_id'        => 1,
        ], $overrides);
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
        $db = db_connect();

        // Head 99's claim is the off-roster one, so it needs a card too.
        ReferentialFixture::claimParents($db, [...range(1, 7), 99]);
        $db->table('distribution_batch')->insert([
            'batch_id' => 1, 'name' => 'Rice Q1', 'subsidy_type_id' => 1, 'eligible_count' => 7,
        ]);

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
            $db->table('subsidy_distribution')->insert($this->claim($member, $date));
        }
        $db->table('subsidy_distribution')->insert(
            $this->claim(7, '2026-03-02', ['dt_voided' => date('Y-m-d H:i:s')])
        );
        $db->table('subsidy_distribution')->insert($this->claim(99, '2026-03-02'));

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
    }

    public function testSingleDayBatchReturnsOneRow(): void
    {
        $db = db_connect();

        ReferentialFixture::claimParents($db, [1]);
        $db->table('distribution_batch')->insert([
            'batch_id' => 1, 'name' => 'Rice Q1', 'subsidy_type_id' => 1, 'eligible_count' => 1,
        ]);
        $db->table('batch_eligibility')->insert(['batch_id' => 1, 'headID' => 1]);
        $db->table('subsidy_distribution')->insert($this->claim(1, '2026-03-01'));

        $out = (new SubsidyStatsModel())->servedByDay(1);

        $this->assertCount(1, $out);
        $this->assertSame('Day 1', $out[0]['label']);
    }
}
