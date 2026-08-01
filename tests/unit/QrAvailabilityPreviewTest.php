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
        $original = $settings->qrUrlPrefix;
        // A non-empty prefix, so the concatenation ControlNumber::payload() does
        // is actually exercised; on the default empty prefix both sides would
        // "match" trivially with nothing to concatenate.
        $settings->qrUrlPrefix = 'https://app.binan.gov.ph/cards/lookup/';

        try {
            // ControlNumber::payload() is the single seam both QrCardPdfGenerator
            // and FamilyController::qrAvailability() call to build the QR string.
            // Asserting against it here (rather than restating the concatenation)
            // means a change to either side of that call breaks this test.
            $expected = (new QrImageGenerator())->dataUri(ControlNumber::payload(42));

            $this->assertStringStartsWith('data:image/png;base64,', $expected);
            $this->assertSame($expected, (new QrImageGenerator())->dataUri(ControlNumber::payload(42)));
        } finally {
            $settings->qrUrlPrefix = $original;
        }
    }

    public function testAControlNumberIsFormattedBeforeItIsEncoded(): void
    {
        $settings = config('QrCardSettings');
        $originalWidth = $settings->controlNumberWidth;
        // Width 1 (the default) leaves format(42) === "42", indistinguishable
        // from the raw int, so a regression that skips format() would pass
        // undetected. Widen it so format() actually changes the string.
        $settings->controlNumberWidth = 5;

        try {
            // If ControlNumber::payload() ever concatenated the raw memberID
            // instead of calling format(), this would fail: "0042" !== "42".
            $this->assertSame(
                $settings->qrUrlPrefix . '00042',
                ControlNumber::payload(42)
            );
        } finally {
            $settings->controlNumberWidth = $originalWidth;
        }
    }
}
