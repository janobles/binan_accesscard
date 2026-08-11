<?php

namespace Tests\Unit;

use App\Libraries\Qr\QrCardPdfGenerator;
use CodeIgniter\Test\CIUnitTestCase;
use Config\QrCardSettings;
use ReflectionClassConstant;

final class QrCardSettingsTest extends CIUnitTestCase
{
    /**
     * Peak render memory per card, measured against the imported dump on PHP
     * 8.2 with dompdf 3.1: 600 cards peaked at 142 MB, 1200 at 268 MB. Dompdf
     * holds the whole document tree plus every embedded QR data URI until
     * output(), so the cost is linear in cards and does not fall with paging.
     */
    private const MEASURED_BYTES_PER_CARD = 233472;
    public function testDefaultsAreSane(): void
    {
        $settings = new QrCardSettings();

        $this->assertSame('', $settings->qrUrlPrefix);
        $this->assertSame(12, $settings->cellsPerPage);
        $this->assertSame(1, $settings->controlNumberWidth);
        $this->assertSame(1000, $settings->cardsPerChunk);
        $this->assertStringEndsWith('.pdf', $settings->singlePdfFileName);
    }

    /**
     * A chunk is one dompdf render, so a chunk that cannot fit the generator's
     * own memory ceiling kills the request with a fatal error instead of
     * producing a file. Half the ceiling leaves room for the response body and
     * for cards heavier than the ones measured.
     */
    public function testAChunkFitsInsideTheRenderMemoryBudget(): void
    {
        $ceiling = (int) (new ReflectionClassConstant(
            QrCardPdfGenerator::class,
            'RENDER_MEMORY_LIMIT_BYTES'
        ))->getValue();

        $projected = (new QrCardSettings())->cardsPerChunk * self::MEASURED_BYTES_PER_CARD;

        $this->assertLessThanOrEqual(
            (int) ($ceiling / 2),
            $projected,
            'cardsPerChunk projects ' . round($projected / 1048576) . ' MB per render.'
        );
    }

    public function testConfigHelperResolvesTheClass(): void
    {
        $this->assertInstanceOf(QrCardSettings::class, config('QrCardSettings'));
    }
}
