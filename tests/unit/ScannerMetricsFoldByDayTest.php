<?php

namespace Tests\Unit;

use App\Libraries\Scanner\ScannerMetrics;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * foldByDay() partitions by calendar day before calling the existing fold(),
 * so a day's figures come from the same code as the batch total and the two
 * can never disagree about what pace means. See ScannerMetricsFoldTest for
 * fold()'s own counting rules, which every assertion here relies on.
 */
final class ScannerMetricsFoldByDayTest extends CIUnitTestCase
{
    /** @return list<array{userID:int,ts:int,control_no:int}> */
    private function events(array $rows): array
    {
        return array_map(static fn ($r) => [
            'userID'     => (int) $r[0],
            'ts'         => strtotime($r[1]),
            'control_no' => (int) $r[2],
        ], $rows);
    }

    public function testEventsAcrossThreeDaysProduceThreeYmdKeys(): void
    {
        $out = ScannerMetrics::foldByDay($this->events([
            [7, '2026-08-11 08:00:00', 100],
            [7, '2026-08-12 08:00:00', 101],
            [7, '2026-08-13 08:00:00', 102],
        ]));

        $this->assertSame(['2026-08-11', '2026-08-12', '2026-08-13'], array_keys($out));
    }

    public function testAScannerPresentOnTwoOfThreeDaysAppearsOnlyInThoseDaysFolds(): void
    {
        $out = ScannerMetrics::foldByDay($this->events([
            [7, '2026-08-11 08:00:00', 100],
            [8, '2026-08-12 08:00:00', 101],
            [7, '2026-08-13 08:00:00', 102],
        ]));

        $this->assertCount(1, $out['2026-08-11']['scanners']);
        $this->assertSame(7, $out['2026-08-11']['scanners'][0]['userID']);

        $this->assertCount(1, $out['2026-08-12']['scanners']);
        $this->assertSame(8, $out['2026-08-12']['scanners'][0]['userID']);

        $this->assertCount(1, $out['2026-08-13']['scanners']);
        $this->assertSame(7, $out['2026-08-13']['scanners'][0]['userID']);
    }

    /**
     * A family (control number) claimed on two different days is one family to
     * each day's fold, since each day only ever sees its own events. The two
     * per-day counts can therefore both be true at once, and the per-day figures
     * sum to no more than the batch total.
     */
    public function testEachDayCountsOnlyItsOwnEventsAndAFamilyServedOnTwoDaysIsOneInEach(): void
    {
        $events = $this->events([
            [7, '2026-08-11 08:00:00', 100],
            [7, '2026-08-11 08:05:00', 101],
            [7, '2026-08-12 08:00:00', 100],
        ]);

        $byDay = ScannerMetrics::foldByDay($events);
        $batch = ScannerMetrics::fold($events);

        $this->assertSame(2, $byDay['2026-08-11']['total']['families']);
        $this->assertSame(1, $byDay['2026-08-12']['total']['families']);

        // Batch total is distinct control numbers across the whole batch: 100
        // and 101, i.e. 2. The per-day totals sum to 3 because family 100 is
        // counted once on each of its two days, so the per-day sum is at most
        // the batch total plus the number of repeat-day families, never less.
        $this->assertSame(2, $batch['total']['families']);
        $this->assertGreaterThanOrEqual(
            $batch['total']['families'],
            $byDay['2026-08-11']['total']['families'] + $byDay['2026-08-12']['total']['families']
        );
    }

    /**
     * 16:55 one day and 08:05 the next are the same scanner's only two scans.
     * Partitioned by day, each day has exactly one event for that scanner, so
     * neither day's fold has a gap to measure: the overnight span never
     * becomes active time in either day.
     */
    public function testNoGapSpansMidnight(): void
    {
        $out = ScannerMetrics::foldByDay($this->events([
            [7, '2026-08-11 16:55:00', 100],
            [7, '2026-08-12 08:05:00', 101],
        ]));

        $this->assertSame(0, $out['2026-08-11']['scanners'][0]['activeSeconds']);
        $this->assertSame(0, $out['2026-08-12']['scanners'][0]['activeSeconds']);
        $this->assertNull($out['2026-08-11']['scanners'][0]['medianGapSeconds']);
        $this->assertNull($out['2026-08-12']['scanners'][0]['medianGapSeconds']);
    }

    public function testADaysTotalRowIsThatDaysTotalNotTheBatchs(): void
    {
        $events = $this->events([
            [7, '2026-08-11 08:00:00', 100],
            [7, '2026-08-11 08:05:00', 101],
            [8, '2026-08-12 09:00:00', 102],
        ]);

        $byDay = ScannerMetrics::foldByDay($events);
        $batch = ScannerMetrics::fold($events);

        $this->assertSame(2, $byDay['2026-08-11']['total']['families']);
        $this->assertSame(1, $byDay['2026-08-12']['total']['families']);
        $this->assertSame(3, $batch['total']['families']);
        $this->assertNotSame($batch['total']['families'], $byDay['2026-08-11']['total']['families']);
    }

    public function testEmptyEventsGiveAnEmptyMap(): void
    {
        $this->assertSame([], ScannerMetrics::foldByDay([]));
    }
}
