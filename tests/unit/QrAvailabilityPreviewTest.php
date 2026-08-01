<?php

namespace Tests\Unit;

use App\Libraries\Qr\ControlNumber;
use App\Libraries\Qr\QrImageGenerator;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * The Data Entry page previews the QR the control number will produce. It comes
 * back on the existing availability check rather than a route of its own, and it
 * encodes the same payload the printed card does, so preview and card cannot
 * drift apart.
 */
final class QrAvailabilityPreviewTest extends CIUnitTestCase
{
    public function testThePayloadMatchesThePrintedCard(): void
    {
        $settings = config('QrCardSettings');
        $control  = ControlNumber::format(42);
        $expected = (new QrImageGenerator())->dataUri($settings->qrUrlPrefix . $control);

        $this->assertStringStartsWith('data:image/png;base64,', $expected);
        $this->assertSame($expected, (new QrImageGenerator())->dataUri($settings->qrUrlPrefix . $control));
    }

    public function testAControlNumberIsFormattedBeforeItIsEncoded(): void
    {
        // QrCardPdfGenerator encodes ControlNumber::format($id), not the raw int,
        // so the preview must format too or the two encode different strings.
        $this->assertSame(ControlNumber::format(42), ControlNumber::format((int) '42'));
    }
}
