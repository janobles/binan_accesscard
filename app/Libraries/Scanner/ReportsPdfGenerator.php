<?php

namespace App\Libraries\Scanner;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Renders the Scanner Reports summary (KPIs + per-barangay + remaining-families
 * tables) into a one-page US-Letter PDF. Server-side, no chart.js: the barangay
 * coverage is drawn as CSS bars. Mirrors Qr\QrCardPdfGenerator's dompdf setup.
 * Batch-scoped only, no date range: a report without the unclaimed names cannot
 * support liquidation, which is why $remaining is here and bySubsidyType is not
 * (a batch binds one subsidy type, so that breakdown is always a single row).
 */
final class ReportsPdfGenerator
{
    /**
     * @param array{eligible:int,served:int,remaining:int,coverage:int,voided:int} $coverage
     * @param list<array{barangay:string,total:int,received:int,coverage:int}> $byBarangay
     * @param list<array{headID:int,name:string,barangay:string,contact:string}> $remaining
     * @param list<array{userID:int,scanner:string,handouts:int,families:int}> $perScanner
     */
    public function generate(array $coverage, array $byBarangay, array $remaining, ?string $batchName, array $perScanner = []): string
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
