<?php

namespace App\Libraries\Scanner;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Renders the Scanner Reports summary (KPIs, per-barangay coverage, rollout by
 * day, the peak-hours grid, per-scanner performance) into a US-Letter PDF that
 * stays a few pages long. Server-side, no chart.js: the barangay coverage is
 * drawn as CSS bars and the heatmap as a shaded table. Mirrors
 * Qr\QrCardPdfGenerator's dompdf setup.
 *
 * Batch-scoped only, no date range, and summary figures only. It used to print
 * every unclaimed family by name, which put a hundred-odd pages of roster behind
 * one page of report; the names live on the dashboard's Remaining tab instead.
 * bySubsidyType is likewise absent: a batch binds one subsidy type, so that
 * breakdown is always a single row.
 */
final class ReportsPdfGenerator
{
    /**
     * Dompdf, not the queries, is what this report costs. Dropping the roster
     * took the bulk of that away, but the ceilings stay: a citywide batch still
     * draws a row per barangay, a row per day and a row per scanner, and a
     * column per hour in the heatmap, and all of those are deliberately finite.
     * An unlimited memory_limit would let one oversized report exhaust the
     * machine and take every other request down with it; hitting a bounded
     * ceiling fails one download.
     */
    private const RENDER_TIME_LIMIT_SECONDS = 300;
    private const RENDER_MEMORY_LIMIT       = '768M';

    /**
     * @param array{eligible:int,served:int,remaining:int,coverage:int,voided:int} $coverage
     * @param list<array{barangay:string,total:int,received:int,coverage:int}> $byBarangay
     * @param list<array{userID:int,scanner:string,families:int,handouts:int,pace:?float,typicalSeconds:?int,share:float,onStationSeconds:int,idleSeconds:int,bestHour:?int,bestHourFamilies:int,longestGapSeconds:int,firstTs:?int,lastTs:?int}> $byScanner SubsidyStatsModel::batchSnapshot()['byScanner'], TOTAL row last
     * @param array{days:list<string>,hours:list<int>,cells:array<string,array<int,array{families:int,state:string}>>,max:int} $heatmap SubsidyStatsModel::batchSnapshot()['heatmap']
     * @param list<array{date:string,label:string,served:int}> $byDay SubsidyStatsModel::batchSnapshot()['byDay']
     */
    public function generate(array $coverage, array $byBarangay, ?string $batchName, array $byScanner = [], array $heatmap = [], array $byDay = []): string
    {
        $previousMemoryLimit = ini_get('memory_limit');
        $previousTimeLimit   = ini_get('max_execution_time');
        ini_set('memory_limit', self::RENDER_MEMORY_LIMIT);
        set_time_limit(self::RENDER_TIME_LIMIT_SECONDS);

        try {
            return $this->render($coverage, $byBarangay, $batchName, $byScanner, $heatmap, $byDay);
        } finally {
            $this->restoreMemoryLimit($previousMemoryLimit === false ? '128M' : $previousMemoryLimit);
            // Same reason as the memory limit: a queue worker rendering a
            // second report should not inherit this one's five minutes.
            if ($previousTimeLimit !== false) {
                set_time_limit((int) $previousTimeLimit);
            }
        }
    }

    /**
     * Puts the ceiling back for a long-lived process, but only when the process
     * has already dropped below it. PHP refuses to set memory_limit under
     * current usage and raises a warning doing so, which CI4 escalates to an
     * exception; restoring unconditionally therefore turned a report that had
     * finished rendering into a 500 on the way out. A web request would not
     * need this at all (ini_set lasts one request), so the guard costs nothing
     * there and keeps the queue worker from inheriting a raised ceiling.
     */
    private function restoreMemoryLimit(string $previous): void
    {
        $limit = self::bytes($previous);

        if ($limit > 0 && memory_get_usage(true) >= $limit) {
            return;
        }

        ini_set('memory_limit', $previous);
    }

    /** A php.ini shorthand byte value ('128M', '1G', '-1') as bytes; -1 gives 0. */
    private static function bytes(string $value): int
    {
        $value = trim($value);
        $unit  = strtolower(substr($value, -1));
        $n     = (int) $value;

        if ($n < 0) {
            return 0;
        }

        return match ($unit) {
            'g'     => $n * 1024 * 1024 * 1024,
            'm'     => $n * 1024 * 1024,
            'k'     => $n * 1024,
            default => $n,
        };
    }

    /**
     * @param array{eligible:int,served:int,remaining:int,coverage:int,voided:int} $coverage
     * @param list<array{barangay:string,total:int,received:int,coverage:int}> $byBarangay
     * @param list<array{userID:int,scanner:string,families:int,handouts:int,pace:?float,typicalSeconds:?int,share:float,onStationSeconds:int,idleSeconds:int,bestHour:?int,bestHourFamilies:int,longestGapSeconds:int,firstTs:?int,lastTs:?int}> $byScanner
     * @param array{days:list<string>,hours:list<int>,cells:array<string,array<int,array{families:int,state:string}>>,max:int} $heatmap
     * @param list<array{date:string,label:string,served:int}> $byDay
     */
    private function render(array $coverage, array $byBarangay, ?string $batchName, array $byScanner, array $heatmap, array $byDay): string
    {
        $heatmap = $heatmap === [] ? ['days' => [], 'hours' => [], 'cells' => [], 'max' => 0] : $heatmap;

        $html = view('Scanner/pdf/report-hours', [
            'coverage'   => $coverage,
            'byBarangay' => $byBarangay,
            'byScanner'  => $byScanner,
            'heatmap'    => $heatmap,
            'byDayRows'  => $this->dayRows($byDay, $heatmap, $byScanner),
            'batchName'  => $batchName,
        ]);

        $options = new Options();
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Widens each byDay row with a peak hour, read off the heatmap's own cells
     * for that date so the printed figure can never disagree with the printed
     * grid below it.
     *
     * Scanners active is the batch-wide station count, repeated on every row
     * rather than sliced per day: the byScanner rows carry no day dimension to
     * slice on, the same limit DashboardPageBuilder::batchHeadline() documents
     * for the screen's own headline tile. Printing a fabricated per-day count
     * would read as more precise than the data supports.
     *
     * @param list<array{date:string,label:string,served:int}> $byDay
     * @param array{days:list<string>,hours:list<int>,cells:array<string,array<int,array{families:int,state:string}>>,max:int} $heatmap
     * @param list<array{userID:int,scanner:string,families:int,handouts:int}> $byScanner
     * @return list<array{date:string,label:string,served:int,peakHour:?int,peakFamilies:int,scannersActive:int}>
     */
    private function dayRows(array $byDay, array $heatmap, array $byScanner): array
    {
        $scannersActive = 0;
        foreach ($byScanner as $row) {
            if ((int) ($row['userID'] ?? 0) > 0) {
                $scannersActive++;
            }
        }

        $rows = [];
        foreach ($byDay as $day) {
            $peakHour     = null;
            $peakFamilies = 0;
            foreach ($heatmap['cells'][$day['date']] ?? [] as $hour => $cell) {
                $families = (int) $cell['families'];
                if ($families > $peakFamilies) {
                    $peakHour     = (int) $hour;
                    $peakFamilies = $families;
                }
            }

            $rows[] = [
                'date'           => (string) $day['date'],
                'label'          => (string) $day['label'],
                'served'         => (int) $day['served'],
                'peakHour'       => $peakHour,
                'peakFamilies'   => $peakFamilies,
                'scannersActive' => $scannersActive,
            ];
        }

        return $rows;
    }
}
