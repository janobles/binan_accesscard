<?php

namespace App\Libraries\Scanner;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Renders the Scanner Reports summary (KPIs + per-barangay + remaining-families
 * tables) into a US-Letter PDF. It runs to as many pages as the roster needs,
 * which on a citywide batch is most of them. Server-side, no chart.js: the
 * barangay coverage is drawn as CSS bars. Mirrors Qr\QrCardPdfGenerator's
 * dompdf setup.
 * Batch-scoped only, no date range: a report without the unclaimed names cannot
 * support liquidation, which is why $remaining is here and bySubsidyType is not
 * (a batch binds one subsidy type, so that breakdown is always a single row).
 */
final class ReportsPdfGenerator
{
    /**
     * Dompdf, not the queries, is what this report costs: on a batch with 3,913
     * unclaimed families the four queries and the HTML take 0.5s together,
     * while Dompdf takes 107s and peaks at 398 MB. Both ceilings below exist
     * for that, and both are deliberately finite. An unlimited memory_limit
     * would let one oversized report exhaust the machine and take every other
     * request down with it; hitting a bounded ceiling fails one download.
     */
    private const RENDER_TIME_LIMIT_SECONDS = 300;
    private const RENDER_MEMORY_LIMIT       = '768M';

    /**
     * @param array{eligible:int,served:int,remaining:int,coverage:int,voided:int} $coverage
     * @param list<array{barangay:string,total:int,received:int,coverage:int}> $byBarangay
     * @param list<array{headID:int,name:string,barangay:string,contact:string}> $remaining
     * @param list<array{userID:int,scanner:string,handouts:int,families:int}> $perScanner
     */
    public function generate(array $coverage, array $byBarangay, array $remaining, ?string $batchName, array $perScanner = []): string
    {
        $previousMemoryLimit = ini_get('memory_limit');
        ini_set('memory_limit', self::RENDER_MEMORY_LIMIT);
        set_time_limit(self::RENDER_TIME_LIMIT_SECONDS);

        try {
            return $this->render($coverage, $byBarangay, $remaining, $batchName, $perScanner);
        } finally {
            $this->restoreMemoryLimit($previousMemoryLimit === false ? '128M' : $previousMemoryLimit);
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
     * @param list<array{headID:int,name:string,barangay:string,contact:string}> $remaining
     * @param list<array{userID:int,scanner:string,handouts:int,families:int}> $perScanner
     */
    private function render(array $coverage, array $byBarangay, array $remaining, ?string $batchName, array $perScanner): string
    {
        $html = view('Scanner/pdf/report', [
            'coverage'   => $coverage,
            'byBarangay' => $byBarangay,
            'remaining'  => $remaining,
            'perScanner' => $perScanner,
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
}
