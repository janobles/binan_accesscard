<?php

namespace App\Libraries\Scanner;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Renders the Scanner Reports summary (KPIs + per-barangay + per-subsidy-type
 * tables) into a one-page US-Letter PDF. Server-side, no chart.js: the barangay
 * coverage is drawn as CSS bars. Mirrors Qr\QrCardPdfGenerator's dompdf setup.
 */
final class ReportsPdfGenerator
{
    /**
     * $summary is `{total, received, notReceived, coverage}`; $byBarangay is a list of
     * `{barangay, total, received, coverage}`; $byAidType is a list of `{aid_type, count}`;
     * $perScanner is a list of `{userID, scanner, handouts, families}`.
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
