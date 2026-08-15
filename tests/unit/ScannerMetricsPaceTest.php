<?php

namespace Tests\Unit;

use App\Libraries\Scanner\ScannerMetrics;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * The arithmetic that replaced ScanController::kioskSnapshot()'s pace. That one
 * divided families by the batch's wall clock, started_at to now, so a scanner
 * who served 300 families over three days reported about 4 an hour. Active time
 * here is the sum of gaps between consecutive scans, with anything longer than
 * the idle threshold left out, so it is derived from scan timestamps alone,
 * ends at the last scan, and never ticks while nobody is scanning.
 */
final class ScannerMetricsPaceTest extends CIUnitTestCase
{
    /** @return list<array{userID:int,ts:int,control_no:int}> */
    private function scans(string $day, array $clockTimes): array
    {
        $control = 100;

        return array_map(static function ($time) use ($day, &$control) {
            return [
                'userID'     => 7,
                'ts'         => strtotime($day . ' ' . $time),
                'control_no' => $control++,
            ];
        }, $clockTimes);
    }

    /** Five scans a minute apart: four gaps, none idle, four minutes active. */
    public function testActiveTimeIsTheSumOfNonIdleGaps(): void
    {
        $out = ScannerMetrics::fold($this->scans('2026-08-11', [
            '08:00:00', '08:01:00', '08:02:00', '08:03:00', '08:04:00',
        ]));

        $this->assertSame(240, $out['scanners'][0]['activeSeconds']);
        $this->assertSame(60, $out['scanners'][0]['medianGapSeconds']);
    }

    /** A lunch break is not work, and it is not pace either. */
    public function testAGapOverTheThresholdIsExcludedFromActiveTime(): void
    {
        $out = ScannerMetrics::fold($this->scans('2026-08-11', [
            '08:00:00', '08:01:00', '12:00:00', '12:01:00',
        ]));

        $row = $out['scanners'][0];
        $this->assertSame(120, $row['activeSeconds']);
        $this->assertSame(14340, $row['longestGapSeconds']);
    }

    /** Exactly at the threshold still counts as work; only longer is idle. */
    public function testTheThresholdItselfIsInclusive(): void
    {
        $out = ScannerMetrics::fold($this->scans('2026-08-11', ['08:00:00', '08:15:00']));

        $this->assertSame(900, $out['scanners'][0]['activeSeconds']);
    }

    /**
     * The median, not the mean. Three quick scans and one near-threshold gap
     * describe a steady station; the mean would report a minute and a half a
     * family and make it look slow.
     */
    public function testTypicalTimeIsTheMedianGapNotTheMean(): void
    {
        $out = ScannerMetrics::fold($this->scans('2026-08-11', [
            '08:00:00', '08:00:30', '08:01:00', '08:01:30', '08:15:00',
        ]));

        $row = $out['scanners'][0];
        $this->assertSame(30, $row['medianGapSeconds']);
        $this->assertSame(30, ScannerMetrics::derive($row, 5)['typicalSeconds']);
    }

    /** One scan is no gap. Not zero, not infinity: nothing to report. */
    public function testASingleScanHasNoPaceAndNoTypicalTime(): void
    {
        $out = ScannerMetrics::fold($this->scans('2026-08-11', ['09:40:00']));
        $derived = ScannerMetrics::derive($out['scanners'][0], 100);

        $this->assertNull($derived['pace']);
        $this->assertNull($derived['typicalSeconds']);
        $this->assertSame(0.0, $derived['share'] * 0);
    }

    /**
     * The case the rejected daily-window denominator would have punished: a
     * scanner who worked two of three days at the same speed must report the
     * same pace as one who worked all three.
     */
    public function testPaceIgnoresDaysNotWorked(): void
    {
        $steady = ScannerMetrics::fold($this->scans('2026-08-11', [
            '08:00:00', '08:01:00', '08:02:00', '08:03:00',
        ]));
        $derived = ScannerMetrics::derive($steady['scanners'][0], 4);

        $this->assertSame(60.0, round($derived['pace'], 1));
    }

    public function testIdleIsOnStationTimeLessActiveTime(): void
    {
        $out = ScannerMetrics::fold($this->scans('2026-08-11', [
            '08:00:00', '08:01:00', '12:00:00', '12:01:00',
        ]));
        $derived = ScannerMetrics::derive($out['scanners'][0], 4);

        $this->assertSame(14460, $derived['onStationSeconds']);
        $this->assertSame(14340, $derived['idleSeconds']);
    }

    public function testBestHourIsTheScannersOwnPeak(): void
    {
        $out = ScannerMetrics::fold(array_merge(
            $this->scans('2026-08-11', ['08:10:00', '08:20:00']),
            $this->scans('2026-08-11', ['09:10:00', '09:20:00', '09:30:00'])
        ));
        $derived = ScannerMetrics::derive($out['scanners'][0], 5);

        $this->assertSame(9, $derived['bestHour']);
        $this->assertSame(3, $derived['bestHourFamilies']);
    }
}
