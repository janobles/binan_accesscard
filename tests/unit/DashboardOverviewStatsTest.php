<?php

namespace Tests\Unit;

use App\Models\DashboardModel;
use CodeIgniter\Test\CIUnitTestCase;

final class DashboardOverviewStatsTest extends CIUnitTestCase
{
    private function createSchema(): void
    {
        $forge = \Config\Database::forge();

        $forge->addField([
            'memberID'   => ['type' => 'INTEGER', 'auto_increment' => true],
            'headID'     => ['type' => 'INTEGER'],
            'dt_deleted' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addPrimaryKey('memberID');
        $forge->createTable('member', true);

        $forge->addField([
            'control_no' => ['type' => 'INTEGER'],
            'headID'     => ['type' => 'INTEGER'],
        ]);
        $forge->addPrimaryKey('control_no');
        $forge->createTable('qr_control', true);

        $forge->addField([
            'distribution_id' => ['type' => 'INTEGER', 'auto_increment' => true],
            'memberID'        => ['type' => 'INTEGER'],
            'batch_id'        => ['type' => 'INTEGER'],
            'dt_voided'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addPrimaryKey('distribution_id');
        $forge->createTable('subsidy_distribution', true);

        $forge->addField([
            'batch_id' => ['type' => 'INTEGER', 'auto_increment' => true],
            'name'     => ['type' => 'VARCHAR', 'constraint' => 100],
        ]);
        $forge->addPrimaryKey('batch_id');
        $forge->createTable('distribution_batch', true);
    }

    private function dropSchema(): void
    {
        $forge = \Config\Database::forge();
        foreach (['subsidy_distribution', 'qr_control', 'member', 'distribution_batch'] as $table) {
            $forge->dropTable($table, true);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        cache()->delete(DashboardModel::PROGRAM_STATS_CACHE_KEY);
    }

    /**
     * Three heads profiled, only two of them carded, one of the carded ones
     * served. The uncarded head is the case the old QR gate silently dropped:
     * it has never been served, so it must count toward neverServed and the
     * three family figures must reconcile.
     */
    public function testFamilyFiguresReconcileIncludingUncardedHeads(): void
    {
        $this->createSchema();
        $db = db_connect();

        foreach ([1, 2, 3] as $id) {
            $db->table('member')->insert(['memberID' => $id, 'headID' => $id]);
        }
        $db->table('qr_control')->insert(['control_no' => 101, 'headID' => 1]);
        $db->table('qr_control')->insert(['control_no' => 102, 'headID' => 2]);
        $db->table('subsidy_distribution')->insert(['memberID' => 1, 'batch_id' => 1]);
        $db->table('distribution_batch')->insert(['batch_id' => 1, 'name' => 'Rice Q1']);

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

        $this->dropSchema();
    }

    /**
     * Access cards issued is the step between profiling and being served, and
     * the only one of the four figures that is not derivable from the others.
     * It counts heads holding a control number, so a card on an archived head
     * or two control numbers on one head must not inflate it.
     */
    public function testCardsIssuedCountsActiveHeadsHoldingAControlNumber(): void
    {
        $this->createSchema();
        $db = db_connect();

        $db->table('member')->insert(['memberID' => 1, 'headID' => 1]);
        $db->table('member')->insert(['memberID' => 2, 'headID' => 2]);
        $db->table('member')->insert(['memberID' => 3, 'headID' => 3, 'dt_deleted' => '2026-01-01 00:00:00']);
        // A relative, not a head: never carded in its own right.
        $db->table('member')->insert(['memberID' => 4, 'headID' => 1]);

        $db->table('qr_control')->insert(['control_no' => 101, 'headID' => 1]);
        // Reissued card for the same head: still one family holding a card.
        $db->table('qr_control')->insert(['control_no' => 102, 'headID' => 1]);
        $db->table('qr_control')->insert(['control_no' => 103, 'headID' => 2]);
        // Archived head.
        $db->table('qr_control')->insert(['control_no' => 104, 'headID' => 3]);

        $out = (new DashboardModel())->programStats();

        $this->assertSame(2, $out['families']);
        $this->assertSame(2, $out['cardsIssued']);
        $this->assertLessThanOrEqual(
            $out['families'],
            $out['cardsIssued'],
            'a card count above the family count means the join is duplicating rows'
        );

        $this->dropSchema();
    }

    /** A voided distribution puts the family back in the never-served pool. */
    public function testVoidedDistributionDoesNotCountAsServed(): void
    {
        $this->createSchema();
        $db = db_connect();

        $db->table('member')->insert(['memberID' => 1, 'headID' => 1]);
        $db->table('subsidy_distribution')->insert([
            'memberID' => 1, 'batch_id' => 1, 'dt_voided' => date('Y-m-d H:i:s'),
        ]);

        $out = (new DashboardModel())->programStats();

        $this->assertSame(0, $out['everServed']);
        $this->assertSame(1, $out['neverServed']);

        $this->dropSchema();
    }

    /**
     * A served head that was later soft-deleted drops out of families, so it
     * must drop out of everServed too or neverServed goes negative.
     */
    public function testSoftDeletedHeadIsExcludedFromBothSides(): void
    {
        $this->createSchema();
        $db = db_connect();

        $db->table('member')->insert(['memberID' => 1, 'headID' => 1]);
        $db->table('member')->insert([
            'memberID' => 2, 'headID' => 2, 'dt_deleted' => date('Y-m-d H:i:s'),
        ]);
        $db->table('subsidy_distribution')->insert(['memberID' => 2, 'batch_id' => 1]);

        $out = (new DashboardModel())->programStats();

        $this->assertSame(1, $out['families']);
        $this->assertSame(0, $out['everServed']);
        $this->assertSame(1, $out['neverServed']);
        $this->assertGreaterThanOrEqual(0, $out['neverServed']);

        $this->dropSchema();
    }
}
