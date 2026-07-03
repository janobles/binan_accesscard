<?php

namespace App\Libraries\Qr;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Renders QR access cards for a list of family heads into a print-ready
 * US-Letter PDF (3x4 grid, 12 cards/page), or a ZIP of PDFs for large batches.
 *
 * Unlike the cswd-qr prototype's range-based generator, this takes an explicit
 * list of heads (memberID gaps from deletions make a contiguous range wrong).
 * Each head's control number is derived via ControlNumber::format($memberID).
 */
final class QrCardPdfGenerator
{
    private const RENDER_MEMORY_LIMIT_BYTES = 512 * 1024 * 1024;
    private const RENDER_TIME_LIMIT_SECONDS = 300;

    /**
     * @param list<array{memberID:int, fullname:string, barangay:string}> $heads
     * @return array{type:string, bytes:string, filename:string}
     */
    public function generate(array $heads): array
    {
        if ($heads === []) {
            throw new \RuntimeException('No heads of family match the selected filter.');
        }

        $settings   = config('QrCardSettings');
        $chunks     = array_values(array_chunk($heads, $settings->cardsPerChunk));
        $firstNo    = ControlNumber::format($heads[0]['controlNo'] ?? $heads[0]['memberID']);
        $lastNo     = ControlNumber::format($heads[count($heads) - 1]['controlNo'] ?? $heads[count($heads) - 1]['memberID']);

        if (count($chunks) === 1) {
            return [
                'type'     => 'pdf',
                'bytes'    => $this->renderChunkPdf($chunks[0]),
                'filename' => $settings->singlePdfFileName,
            ];
        }

        if (! class_exists(\ZipArchive::class)) {
            throw new \RuntimeException(
                'The PHP zip extension (ZipArchive) is required for multi-file batches.'
            );
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'binan-qr-zip');
        if ($zipPath === false) {
            throw new \RuntimeException('Failed to create a temp file for the ZIP bundle.');
        }

        try {
            $zip = new \ZipArchive();
            if ($zip->open($zipPath, \ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('ZipArchive::open() failed.');
            }
            foreach ($chunks as $chunk) {
                $chunkFirst = ControlNumber::format($chunk[0]['controlNo'] ?? $chunk[0]['memberID']);
                $chunkLast  = ControlNumber::format($chunk[count($chunk) - 1]['controlNo'] ?? $chunk[count($chunk) - 1]['memberID']);
                $entryName  = sprintf($settings->chunkPdfNamePattern, $chunkFirst, $chunkLast);
                $zip->addFromString($entryName, $this->renderChunkPdf($chunk));
            }
            $zip->close();

            $zipBytes = file_get_contents($zipPath);
            if ($zipBytes === false) {
                throw new \RuntimeException('Failed to read assembled ZIP from temp file.');
            }
        } finally {
            if (is_file($zipPath)) {
                unlink($zipPath);
            }
        }

        return [
            'type'     => 'zip',
            'bytes'    => $zipBytes,
            'filename' => sprintf($settings->zipFileNamePattern, $firstNo, $lastNo),
        ];
    }

    public function pageCount(int $cardCount): int
    {
        return (int) ceil($cardCount / config('QrCardSettings')->cellsPerPage);
    }

    /**
     * @param list<array{memberID:int, fullname:string, barangay:string}> $headsChunk
     */
    public function renderChunkPdf(array $headsChunk): string
    {
        $this->ensureRenderMemoryLimit();
        set_time_limit(self::RENDER_TIME_LIMIT_SECONDS);

        if (! extension_loaded('gd')) {
            throw new \RuntimeException(
                'The PHP GD extension is required to embed PNG QR codes. Install php-gd and restart.'
            );
        }

        $settings = config('QrCardSettings');
        $qr       = new QrImageGenerator();
        $perPage  = $settings->cellsPerPage;

        $pagesHtml  = '';
        $pageNumber = 0;
        foreach (array_chunk($headsChunk, $perPage) as $pageHeads) {
            $pageNumber++;
            $cells = [];
            foreach ($pageHeads as $head) {
                $control = ControlNumber::format($head['controlNo'] ?? $head['memberID']);
                $cells[] = [
                    'controlNumber' => $control,
                    'fullname'      => $head['fullname'],
                    'barangay'      => $head['barangay'],
                    'qrDataUri'     => $qr->dataUri($settings->qrUrlPrefix . $control),
                ];
            }
            // Pad the final page to a full 3x4 grid so cards keep a consistent size.
            while (count($cells) < $perPage) {
                $cells[] = ['controlNumber' => '', 'fullname' => '', 'barangay' => '', 'qrDataUri' => ''];
            }
            $pagesHtml .= view('Cards/pdf/batch_page', [
                'cells'       => $cells,
                'isFirstPage' => $pageNumber === 1,
            ]);
            unset($cells);
        }

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('fontDir', WRITEPATH . 'fonts');
        $options->set('fontCache', WRITEPATH . 'fonts');
        $options->set('defaultFont', 'Roboto');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('Cards/pdf/_styles') . $pagesHtml);
        $dompdf->setPaper('letter', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private function ensureRenderMemoryLimit(): void
    {
        $current = trim((string) ini_get('memory_limit'));
        if ($current === '-1') {
            return;
        }
        if ($this->parseMemoryLimitToBytes($current) < self::RENDER_MEMORY_LIMIT_BYTES) {
            ini_set('memory_limit', (string) self::RENDER_MEMORY_LIMIT_BYTES);
        }
    }

    private function parseMemoryLimitToBytes(string $memoryLimit): int
    {
        $value = (int) $memoryLimit;
        return match (strtolower(substr($memoryLimit, -1))) {
            'g'     => $value * 1024 * 1024 * 1024,
            'm'     => $value * 1024 * 1024,
            'k'     => $value * 1024,
            default => $value,
        };
    }
}
