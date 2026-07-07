<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Config\QrCardSettings;

final class QrCardSettingsTest extends CIUnitTestCase
{
    public function testDefaultsAreSane(): void
    {
        $settings = new QrCardSettings();

        $this->assertSame('', $settings->qrUrlPrefix);
        $this->assertSame(12, $settings->cellsPerPage);
        $this->assertSame(1, $settings->controlNumberWidth);
        $this->assertSame(12000, $settings->cardsPerChunk);
        $this->assertStringEndsWith('.pdf', $settings->singlePdfFileName);
    }

    public function testConfigHelperResolvesTheClass(): void
    {
        $this->assertInstanceOf(QrCardSettings::class, config('QrCardSettings'));
    }
}
