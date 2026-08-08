<?php

namespace Tests\Unit;

use App\Models\Scanner\DistributionBatchModel;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Database\DumpSchema;

/**
 * reconcileSchedule() applying the BatchScheduleWindow verdict to storage.
 *
 * Every case passes an explicit "now" so the assertions do not depend on when
 * the suite runs, which is also the property the design leans on: a late
 * reconcile writes the same closed_at an on-time one would.
 */
final class BatchScheduleReconcileTest extends CIUnitTestCase
{
    private DistributionBatchModel $model;

    protected function setUp(): void
    {
        parent::setUp();

        $db = db_connect();
        DumpSchema::create($db);
        $db->table('subsidy')->insert(['subsidy_type_id' => 1, 'name' => 'Rice']);

        $this->model = new DistributionBatchModel();
    }

    protected function tearDown(): void
    {
        DumpSchema::drop(db_connect());
        parent::tearDown();
    }

    private function plot(string $start = '2026-08-20', string $end = '2026-08-21'): int
    {
        return $this->model->saveSchedule([
            'batch_id'         => 0,
            'name'             => 'Relief Distribution - Zone 4',
            'venue'            => 'Canlalay Covered Court',
            'subsidy_type_id'  => 1,
            'scheduled_start'  => $start,
            'scheduled_end'    => $end,
            'daily_start_time' => '08:00:00',
            'daily_end_time'   => '17:00:00',
            'color'            => 'blue',
            'barangay_ids'     => [],
            'sector_ids'       => [],
        ], 1);
    }

    public function testOpensOnTheScheduledDay(): void
    {
        $id = $this->plot();
        $this->model->reconcileSchedule('2026-08-20 07:45:00');

        $row = $this->model->find($id);
        $this->assertNotNull($row['started_at']);
        $this->assertNull($row['closed_at']);
    }

    public function testDoesNotOpenBeforeTheFirstDay(): void
    {
        $id = $this->plot();
        $this->model->reconcileSchedule('2026-08-19 12:00:00');

        $this->assertNull($this->model->find($id)['started_at']);
    }

    public function testClosesAfterTheGraceWithNoScans(): void
    {
        $id = $this->plot('2026-08-20', '2026-08-20');
        $this->model->reconcileSchedule('2026-08-20 08:00:00');
        $this->model->reconcileSchedule('2026-08-20 17:31:00');

        $this->assertSame('2026-08-20 17:00:00', $this->model->find($id)['closed_at']);
    }

    public function testALateReconcileWritesTheSameClosedAt(): void
    {
        $id = $this->plot('2026-08-20', '2026-08-20');
        $this->model->reconcileSchedule('2026-08-20 08:00:00');
        $this->model->reconcileSchedule('2026-08-24 09:00:00');

        $this->assertSame('2026-08-20 17:00:00', $this->model->find($id)['closed_at']);
    }

    public function testIsIdempotent(): void
    {
        $id = $this->plot('2026-08-20', '2026-08-20');
        $this->model->reconcileSchedule('2026-08-20 08:00:00');
        $first = $this->model->find($id)['started_at'];

        $this->model->reconcileSchedule('2026-08-20 09:00:00');
        $this->model->reconcileSchedule('2026-08-20 10:00:00');

        $this->assertSame($first, $this->model->find($id)['started_at'], 'started_at is stamped once');
    }

    public function testFreezesTheRosterOnceOnly(): void
    {
        $id = $this->plot('2026-08-20', '2026-08-21');
        $this->model->reconcileSchedule('2026-08-20 08:00:00');

        // A second day reopens the batch. The roster must not be rebuilt, so
        // the printed coverage figure of a running batch cannot drift.
        db_connect()->table('distribution_batch')->where('batch_id', $id)
            ->update(['eligible_count' => 999, 'closed_at' => '2026-08-20 17:00:00']);

        $this->model->reconcileSchedule('2026-08-21 08:00:00');

        $row = $this->model->find($id);
        $this->assertNull($row['closed_at'], 'the second day reopens the batch');
        $this->assertSame(999, (int) $row['eligible_count'], 'the roster is not rebuilt on reopen');
    }

    public function testDoesNothingWhenNothingIsPlotted(): void
    {
        $this->model->reconcileSchedule('2026-08-20 08:00:00');
        $this->assertSame([], $this->model->allBatches());
    }
}
