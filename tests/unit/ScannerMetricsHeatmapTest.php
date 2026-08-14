<?php

namespace Tests\Unit;

use App\Libraries\Scanner\ScannerMetrics;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * The three cell states are the whole point of the heatmap. A staffed hour that
 * served nobody is the operationally interesting one and has to be visibly
 * different from an hour the station was never open for; rendering both as
 * blank would hide the only cell worth acting on.
 */
final class ScannerMetricsHeatmapTest extends CIUnitTestCase
{
    private function fold(array $rows): array
    {
        return ScannerMetrics::fold(array_map(static fn ($r) => [
            'userID'     => 7,
            'ts'         => strtotime($r[0]),
            'control_no' => (int) $r[1],
        ], $rows));
    }

    public function testHoursSpanTheDailyWindowNotJustHoursWithScans(): void
    {
        $map = ScannerMetrics::heatmap(
            $this->fold([['2026-08-11 09:30:00', 100]]),
            '08:00:00',
            '11:00:00'
        );

        $this->assertSame([8, 9, 10], $map['hours']);
    }

    public function testAStaffedHourWithNoScansIsEmptyNotClosed(): void
    {
        $map = ScannerMetrics::heatmap(
            $this->fold([['2026-08-11 09:30:00', 100]]),
            '08:00:00',
            '11:00:00'
        );

        $this->assertSame('empty', $map['cells']['2026-08-11'][8]['state']);
        $this->assertSame(0, $map['cells']['2026-08-11'][8]['families']);
        $this->assertSame('served', $map['cells']['2026-08-11'][9]['state']);
        $this->assertSame(1, $map['cells']['2026-08-11'][9]['families']);
    }

    /**
     * A scan outside the declared window is real work and must still show. The
     * window decides what "closed" means for empty cells, never whether a
     * recorded scan is displayed.
     */
    public function testAScanOutsideTheWindowWidensTheHours(): void
    {
        $map = ScannerMetrics::heatmap(
            $this->fold([['2026-08-11 18:30:00', 100]]),
            '08:00:00',
            '11:00:00'
        );

        $this->assertContains(18, $map['hours']);
        $this->assertSame('served', $map['cells']['2026-08-11'][18]['state']);
    }

    /** Without a window nothing can be called closed. */
    public function testNoDailyWindowMeansNoClosedCells(): void
    {
        $map = ScannerMetrics::heatmap($this->fold([['2026-08-11 09:30:00', 100]]), null, null);

        foreach ($map['cells']['2026-08-11'] as $cell) {
            $this->assertNotSame('closed', $cell['state']);
        }
    }

    /** The scale is the batch's own maximum, so a small batch still contrasts. */
    public function testMaxIsTheBusiestCell(): void
    {
        $map = ScannerMetrics::heatmap($this->fold([
            ['2026-08-11 09:10:00', 100],
            ['2026-08-11 09:20:00', 101],
            ['2026-08-11 10:10:00', 102],
        ]), '08:00:00', '11:00:00');

        $this->assertSame(2, $map['max']);
    }

    public function testAnEmptyBatchGivesAnEmptyMapNotAnError(): void
    {
        $map = ScannerMetrics::heatmap(ScannerMetrics::fold([]), '08:00:00', '11:00:00');

        $this->assertSame([], $map['days']);
        $this->assertSame(0, $map['max']);
    }
}
