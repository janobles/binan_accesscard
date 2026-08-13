<?php

namespace Tests\Unit;

use App\Models\DashboardModel;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Database\DumpSchema;
use Tests\Support\Database\ReferentialFixture;

/**
 * The four Overview figures, counted against the dump's own schema.
 *
 * Schema comes from the dump; rows are only the ones each case asserts on.
 */
final class DashboardOverviewStatsTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DumpSchema::create(db_connect());
        cache()->delete(DashboardModel::PROGRAM_STATS_CACHE_KEY);
    }

    protected function tearDown(): void
    {
        DumpSchema::drop(db_connect());
        parent::tearDown();
    }

    /**
     * A head of family, carrying the names and the head link the dump requires
     * of every member row.
     */
    private function head(int $id, array $overrides = []): array
    {
        return array_merge([
            'memberID'   => $id,
            'headID'     => $id,
            'lastname'   => 'Cruz',
            'firstname'  => 'Head ' . $id,
            'middlename' => '',
        ], $overrides);
    }

    /** One claim for the given member against batch 1. */
    private function distribution(int $memberId, array $overrides = []): array
    {
        return array_merge([
            'control_no'      => 100 + $memberId,
            'memberID'        => $memberId,
            'subsidy_type_id' => 1,
            'claim_date'      => '2026-03-01',
            'batch_id'        => 1,
        ], $overrides);
    }

    /**
     * The batch, subsidy type and cards a distribution() row points at. The
     * member rows are the case's own, since whether a head is archived is
     * what these cases are about.
     *
     * @param list<int> $memberIds
     */
    private function seedClaimParents(\CodeIgniter\Database\BaseConnection $db, array $memberIds): void
    {
        ReferentialFixture::subsidyType($db);
        ReferentialFixture::cards($db, $memberIds);
        $db->table('distribution_batch')->insert([
            'batch_id' => 1, 'name' => 'Rice Q1', 'subsidy_type_id' => 1,
        ]);
    }

    /**
     * Three heads profiled, only two of them carded, one of the carded ones
     * served. The uncarded head is the case the old QR gate silently dropped:
     * it has never been served, so it must count toward neverServed and the
     * three family figures must reconcile.
     */
    public function testFamilyFiguresReconcileIncludingUncardedHeads(): void
    {
        $db = db_connect();

        foreach ([1, 2, 3] as $id) {
            $db->table('member')->insert($this->head($id));
        }
        $db->table('qr_control')->insert(['control_no' => 101, 'headID' => 1]);
        $db->table('qr_control')->insert(['control_no' => 102, 'headID' => 2]);
        // The claim's batch, type and card are all foreign keys since V22.
        ReferentialFixture::subsidyType($db);
        $db->table('distribution_batch')->insert([
            'batch_id' => 1, 'name' => 'Rice Q1', 'subsidy_type_id' => 1,
        ]);
        $db->table('subsidy_distribution')->insert($this->distribution(1));

        $out = (new DashboardModel())->programStats();

        $this->assertSame(3, $out['families']);
        $this->assertSame(1, $out['everServed']);
        $this->assertSame(2, $out['neverServed'], 'The uncarded head has never been served.');
        $this->assertSame(1, $out['distributions']);
        $this->assertSame(
            $out['families'],
            $out['everServed'] + $out['neverServed'],
            'The three family cards must add up.'
        );
    }

    /**
     * Access cards issued is the step between profiling and being served, and
     * the only one of the four figures that is not derivable from the others.
     * It counts heads holding a control number, so a card on an archived head
     * or two control numbers on one head must not inflate it.
     */
    public function testCardsIssuedCountsActiveHeadsWhoseCardWasGenerated(): void
    {
        $db = db_connect();

        $db->table('member')->insert($this->head(1));
        $db->table('member')->insert($this->head(2));
        $db->table('member')->insert($this->head(3, ['dt_deleted' => '2026-01-01 00:00:00']));
        // A relative, not a head: never carded in its own right.
        $db->table('member')->insert($this->head(4, ['headID' => 1]));

        // card_generated_at is what counts: a control number on its own is a
        // number a worker typed while profiling, not a card anyone holds.
        $generated = '2026-02-01 09:00:00';

        $db->table('qr_control')->insert(['control_no' => 101, 'headID' => 1, 'card_generated_at' => $generated]);
        // Reissued card for the same head: still one family holding a card.
        $db->table('qr_control')->insert(['control_no' => 102, 'headID' => 1, 'card_generated_at' => $generated]);
        $db->table('qr_control')->insert(['control_no' => 103, 'headID' => 2, 'card_generated_at' => $generated]);
        // Archived head.
        $db->table('qr_control')->insert(['control_no' => 104, 'headID' => 3, 'card_generated_at' => $generated]);
        // Profiled but never carded: reserves a number, issues nothing.
        $db->table('qr_control')->insert(['control_no' => 105, 'headID' => 2]);

        $out = (new DashboardModel())->programStats();

        $this->assertSame(2, $out['families']);
        $this->assertSame(2, $out['cardsIssued']);
        $this->assertLessThanOrEqual(
            $out['families'],
            $out['cardsIssued'],
            'a card count above the family count means the join is duplicating rows'
        );
    }

    /** A voided distribution puts the family back in the never-served pool. */
    public function testVoidedDistributionDoesNotCountAsServed(): void
    {
        $db = db_connect();

        $db->table('member')->insert($this->head(1));
        $this->seedClaimParents($db, [1]);
        $db->table('subsidy_distribution')->insert(
            $this->distribution(1, ['dt_voided' => date('Y-m-d H:i:s')])
        );

        $out = (new DashboardModel())->programStats();

        $this->assertSame(0, $out['everServed']);
        $this->assertSame(1, $out['neverServed']);
    }

    /**
     * A served head that was later soft-deleted drops out of families, so it
     * must drop out of everServed too or neverServed goes negative.
     */
    public function testSoftDeletedHeadIsExcludedFromBothSides(): void
    {
        $db = db_connect();

        $db->table('member')->insert($this->head(1));
        $db->table('member')->insert($this->head(2, ['dt_deleted' => date('Y-m-d H:i:s')]));
        $this->seedClaimParents($db, [2]);
        $db->table('subsidy_distribution')->insert($this->distribution(2));

        $out = (new DashboardModel())->programStats();

        $this->assertSame(1, $out['families']);
        $this->assertSame(0, $out['everServed']);
        $this->assertSame(1, $out['neverServed']);
        $this->assertGreaterThanOrEqual(0, $out['neverServed']);
    }
}
