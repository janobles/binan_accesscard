<?php

namespace App\Libraries\Scanner;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Renders the Scanner Reports summary (KPIs + per-barangay + per-service
 * tables) into a one-page US-Letter PDF. Server-side, no chart.js: the barangay
 * coverage is drawn as CSS bars. Mirrors Qr\QrCardPdfGenerator's dompdf setup.
 */
final class ReportsPdfGenerator
{
    /**
     * @param array{total:int,received:int,notReceived:int,coverage:int} $summary
     * @param list<array{barangay:string,total:int,received:int,coverage:int}> $byBarangay
     * @param list<array{aid_type:string,count:int}> $byAidType
     * @param list<array{userID:int,scanner:string,handouts:int,families:int}> $perScanner
     */
    public function generate(array $summary, array $byBarangay, array $byAidType, ?string $from, ?string $to, array $perScanner = [], ?string $batchName = null): string
    {
        $html = view('Scanner/pdf/report', [
            'summary'    => $summary,
            'byBarangay' => $byBarangay,
            'byAidType'  => $byAidType,
            'from'       => $from,
            'to'         => $to,
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
