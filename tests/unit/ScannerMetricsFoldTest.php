<?php

namespace Tests\Unit;

use App\Libraries\Scanner\ScannerMetrics;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * The fold's counting half: families are distinct control numbers, handouts
 * are rows, the same definitions the grouped SQL query used before the fold
 * replaced it. The totals row is folded by the same code as the scanner rows,
 * so a batch total can never be a separately computed number that happens to
 * sit at the bottom of the table.
 */
final class ScannerMetricsFoldTest extends CIUnitTestCase
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

    public function testFamiliesCountDistinctControlNumbersAndHandoutsCountRows(): void
    {
        $out = ScannerMetrics::fold($this->events([
            [7, '2026-08-11 08:00:00', 100],
            [7, '2026-08-11 08:05:00', 100],
            [7, '2026-08-11 08:10:00', 101],
        ]));

        $this->assertCount(1, $out['scanners']);
        $this->assertSame(2, $out['scanners'][0]['families']);
        $this->assertSame(3, $out['scanners'][0]['handouts']);
    }

    public function testTotalRowFoldsEveryScanner(): void
    {
        $out = ScannerMetrics::fold($this->events([
            [7, '2026-08-11 08:00:00', 100],
            [8, '2026-08-11 08:02:00', 101],
            [8, '2026-08-11 08:04:00', 102],
        ]));

        $this->assertSame(3, $out['total']['families']);
        $this->assertSame(3, $out['total']['handouts']);
        $this->assertSame(0, $out['total']['userID']);
    }

    /**
     * One family scanned by two stations is one family to the batch. A total
     * summed from the per-scanner rows would say two, which is the exact
     * disagreement that keeping one source is meant to prevent.
     */
    public function testTotalDoesNotDoubleCountAFamilyServedByTwoScanners(): void
    {
        $out = ScannerMetrics::fold($this->events([
            [7, '2026-08-11 08:00:00', 100],
            [8, '2026-08-11 09:00:00', 100],
        ]));

        $this->assertSame(1, $out['scanners'][0]['families']);
        $this->assertSame(1, $out['scanners'][1]['families']);
        $this->assertSame(1, $out['total']['families']);
        $this->assertSame(2, $out['total']['handouts']);
    }

    /**
     * The TOTAL row is aggregated from the per-scanner accumulators, so it has
     * to carry the fields those rows carry. Built from an empty accumulator it
     * would report families and handouts and then leave pace, best hour and on
     * station blank in every table that prints it.
     */
    public function testTotalCarriesHoursAndSpanNotJustCounts(): void
    {
        $out = ScannerMetrics::fold($this->events([
            [7, '2026-08-11 08:10:00', 100],
            [7, '2026-08-11 08:20:00', 101],
            [8, '2026-08-11 09:15:00', 102],
        ]));

        $this->assertSame([8 => 2, 9 => 1], $out['total']['byHour']);
        $this->assertSame(strtotime('2026-08-11 08:10:00'), $out['total']['firstTs']);
        $this->assertSame(strtotime('2026-08-11 09:15:00'), $out['total']['lastTs']);
    }

    public function testHoursAreBucketedPerScanner(): void
    {
        $out = ScannerMetrics::fold($this->events([
            [7, '2026-08-11 08:15:00', 100],
            [7, '2026-08-11 08:45:00', 101],
            [7, '2026-08-11 09:05:00', 102],
        ]));

        $this->assertSame([8 => 2, 9 => 1], $out['scanners'][0]['byHour']);
    }

    public function testEmptyEventsGiveAnEmptyShapeNotAnError(): void
    {
        $out = ScannerMetrics::fold([]);

        $this->assertSame([], $out['scanners']);
        $this->assertSame([], $out['days']);
        $this->assertSame(0, $out['total']['families']);
    }
}
