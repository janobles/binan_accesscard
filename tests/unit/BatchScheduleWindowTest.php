<?php

namespace Tests\Unit;

use App\Libraries\BatchScheduleWindow;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * The lifecycle rules, exercised without a database.
 *
 * Every case fixes "now" explicitly, because the point of the library is that
 * the answer depends on the schedule and the scan times rather than on when
 * the code happens to run.
 */
final class BatchScheduleWindowTest extends CIUnitTestCase
{
    /** A two day batch, Aug 20 to 21, running 08:00 to 17:00 each day. */
    private function batch(?string $startedAt = null, ?string $closedAt = null): array
    {
        return [
            'scheduled_start'  => '2026-08-20',
            'scheduled_end'    => '2026-08-21',
            'daily_start_time' => '08:00:00',
            'daily_end_time'   => '17:00:00',
            'started_at'       => $startedAt,
            'closed_at'        => $closedAt,
        ];
    }

    public function testDoesNothingBeforeTheFirstDay(): void
    {
        $out = BatchScheduleWindow::verdict($this->batch(), null, '2026-08-19 23:59:00');
        $this->assertSame('none', $out['action']);
    }

    public function testOpensOnTheFirstDay(): void
    {
        $out = BatchScheduleWindow::verdict($this->batch(), null, '2026-08-20 07:55:00');
        $this->assertSame('open', $out['action'], 'a scheduled day opens the batch even before daily_start_time');
    }

    public function testZeroTurnoutClosesAtTheScheduledEnd(): void
    {
        $out = BatchScheduleWindow::verdict(
            $this->batch('2026-08-21 08:00:00'),
            null,
            '2026-08-21 17:30:00'
        );
        $this->assertSame('close', $out['action']);
        $this->assertSame('2026-08-21 17:00:00', $out['closed_at']);
    }

    public function testDoesNotCloseBeforeTheGraceExpires(): void
    {
        $out = BatchScheduleWindow::verdict(
            $this->batch('2026-08-21 08:00:00'),
            '2026-08-21 16:10:00',
            '2026-08-21 17:29:00'
        );
        $this->assertSame('none', $out['action']);
    }

    public function testEarlyLastScanDoesNotMoveTheAnchor(): void
    {
        $out = BatchScheduleWindow::verdict(
            $this->batch('2026-08-21 08:00:00'),
            '2026-08-21 16:10:00',
            '2026-08-21 17:30:00'
        );
        $this->assertSame('close', $out['action']);
        $this->assertSame('2026-08-21 17:00:00', $out['closed_at']);
    }

    public function testOverrunRollsTheAnchorOneStep(): void
    {
        $out = BatchScheduleWindow::verdict(
            $this->batch('2026-08-21 08:00:00'),
            '2026-08-21 17:15:00',
            '2026-08-21 18:00:00'
        );
        $this->assertSame('close', $out['action']);
        $this->assertSame('2026-08-21 17:30:00', $out['closed_at']);
    }

    public function testOverrunRollsTheAnchorSeveralSteps(): void
    {
        $out = BatchScheduleWindow::verdict(
            $this->batch('2026-08-21 08:00:00'),
            '2026-08-21 18:40:00',
            '2026-08-21 19:40:00'
        );
        $this->assertSame('close', $out['action']);
        $this->assertSame('2026-08-21 19:00:00', $out['closed_at']);
    }

    public function testStillOpenWhileScansKeepArriving(): void
    {
        $out = BatchScheduleWindow::verdict(
            $this->batch('2026-08-21 08:00:00'),
            '2026-08-21 18:40:00',
            '2026-08-21 19:05:00'
        );
        $this->assertSame('none', $out['action']);
    }

    public function testNewDayReopensAClosedBatch(): void
    {
        $out = BatchScheduleWindow::verdict(
            $this->batch('2026-08-20 08:00:00', '2026-08-20 17:00:00'),
            '2026-08-20 16:20:00',
            '2026-08-21 07:50:00'
        );
        $this->assertSame('open', $out['action']);
    }

    public function testScanAfterAGraceCloseReopensTheSameDay(): void
    {
        $out = BatchScheduleWindow::verdict(
            $this->batch('2026-08-20 08:00:00', '2026-08-20 13:00:00'),
            '2026-08-20 13:40:00',
            '2026-08-20 13:41:00'
        );
        $this->assertSame('open', $out['action'], 'work resumed inside the scheduled day');
    }

    public function testAnIdleEveningDoesNotReopen(): void
    {
        $out = BatchScheduleWindow::verdict(
            $this->batch('2026-08-20 08:00:00', '2026-08-20 17:00:00'),
            '2026-08-20 16:20:00',
            '2026-08-20 21:00:00'
        );
        $this->assertSame('none', $out['action'], 'nothing has happened since the close');
    }

    public function testClosesOnceTheSpanIsOver(): void
    {
        $out = BatchScheduleWindow::verdict(
            $this->batch('2026-08-20 08:00:00'),
            '2026-08-21 16:00:00',
            '2026-08-25 09:00:00'
        );
        $this->assertSame('close', $out['action']);
        $this->assertSame('2026-08-21 17:00:00', $out['closed_at'], 'closes at the last scheduled day, not today');
    }

    public function testALateRunProducesTheSameClosedAt(): void
    {
        $batch = $this->batch('2026-08-21 08:00:00');
        $onTime = BatchScheduleWindow::verdict($batch, '2026-08-21 17:15:00', '2026-08-21 18:00:00');
        $late   = BatchScheduleWindow::verdict($batch, '2026-08-21 17:15:00', '2026-08-24 09:00:00');

        $this->assertSame($onTime['closed_at'], $late['closed_at']);
    }

    public function testAlreadyClosedAndPastTheSpanDoesNothing(): void
    {
        $out = BatchScheduleWindow::verdict(
            $this->batch('2026-08-20 08:00:00', '2026-08-21 17:00:00'),
            '2026-08-21 16:00:00',
            '2026-09-01 09:00:00'
        );
        $this->assertSame('none', $out['action']);
    }

    public function testOneDayBatch(): void
    {
        $batch = [
            'scheduled_start'  => '2026-08-20',
            'scheduled_end'    => '2026-08-20',
            'daily_start_time' => '09:00:00',
            'daily_end_time'   => '15:00:00',
            'started_at'       => '2026-08-20 09:02:00',
            'closed_at'        => null,
        ];
        $out = BatchScheduleWindow::verdict($batch, '2026-08-20 14:50:00', '2026-08-20 15:30:00');
        $this->assertSame('close', $out['action']);
        $this->assertSame('2026-08-20 15:00:00', $out['closed_at']);
    }
}
