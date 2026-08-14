<?php

namespace Tests\Unit;

use App\Libraries\DashboardPageBuilder;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Task 13's Finding 1: "Scanners active" printed the batch-wide count on
 * every day, disagreeing with the PDF's Rollout by day table, which slices
 * byScannerByDay correctly. batchHeadline() is private, so this reflects
 * into it directly with a fabricated snapshot rather than standing up a
 * database: the bug lives entirely in which snapshot key gets folded, not in
 * a query.
 */
final class BatchHeadlineScannersActiveTest extends CIUnitTestCase
{
    /** @return array{value:string,sub:string} */
    private function scannersActive(array $snapshot, ?string $selectedDay): array
    {
        $method = new \ReflectionMethod(DashboardPageBuilder::class, 'batchHeadline');
        $result = $method->invoke(null, $snapshot, $selectedDay);

        return $result['scannersActive'];
    }

    private function snapshot(): array
    {
        return [
            'coverage' => ['eligible' => 300, 'served' => 249, 'coverage' => 83],
            'heatmap'  => ['days' => [], 'hours' => [], 'cells' => [], 'max' => 0],
            // Batch-wide fold: three distinct scanners across the whole batch.
            'byScanner' => [
                ['userID' => 7, 'families' => 159],
                ['userID' => 8, 'families' => 89],
                ['userID' => 9, 'families' => 1],
                ['userID' => 0, 'families' => 249], // TOTAL row, not a station
            ],
            // Per day: only two of the three scanners logged a scan on Aug 4.
            'byScannerByDay' => [
                '2026-08-04' => [
                    ['userID' => 7, 'families' => 55],
                    ['userID' => 9, 'families' => 1],
                    ['userID' => 0, 'families' => 56], // TOTAL row for that day
                ],
            ],
        ];
    }

    public function testAllDaysCountsTheBatchWideFold(): void
    {
        $metric = $this->scannersActive($this->snapshot(), null);

        $this->assertSame('3', $metric['value']);
        $this->assertSame('across the batch', $metric['sub']);
    }

    /**
     * The regression: Aug 4 only saw two stations (Scanner1 and Scanner3),
     * matching the PDF's Rollout by day row, not the batch-wide fold of 3.
     */
    public function testSelectedDayCountsThatDaysRowsNotTheBatchWideFold(): void
    {
        $metric = $this->scannersActive($this->snapshot(), '2026-08-04');

        $this->assertSame('2', $metric['value']);
        $this->assertSame('that day', $metric['sub']);
    }

    /** A day the snapshot never logged any station for is genuinely zero, not the batch total. */
    public function testDayWithNoScannerRowsReadsZero(): void
    {
        $metric = $this->scannersActive($this->snapshot(), '2026-08-09');

        $this->assertSame('0', $metric['value']);
    }
}
