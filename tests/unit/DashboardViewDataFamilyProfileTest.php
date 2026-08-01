<?php

namespace Tests\Unit;

use App\Libraries\Qr\ControlNumber;
use App\Libraries\Qr\QrImageGenerator;
use App\Support\DashboardViewData;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * DashboardViewData::familyProfile() computes qrDataUri for the edit page. This
 * is the assembly side of the contract: FamilyProfilePageTest covers what the
 * view does with the value, this covers what the value actually is.
 */
final class DashboardViewDataFamilyProfileTest extends CIUnitTestCase
{
    public function testANonZeroControlNumberEncodesThePrintedCardPayload(): void
    {
        $result = DashboardViewData::familyProfile(['controlNumber' => 142]);

        // Same seam QrAvailabilityPreviewTest and QrCardPdfGenerator go through:
        // asserting against it, rather than restating the concatenation, means a
        // change to either side of that call breaks this test too.
        $expected = (new QrImageGenerator())->dataUri(ControlNumber::payload(142));

        $this->assertStringStartsWith('data:image/png;base64,', $result['qrDataUri']);
        $this->assertSame($expected, $result['qrDataUri']);
    }

    public function testAZeroControlNumberProducesNoQrCode(): void
    {
        $result = DashboardViewData::familyProfile(['controlNumber' => 0]);

        $this->assertSame('', $result['qrDataUri']);
    }

    public function testAMissingControlNumberProducesNoQrCode(): void
    {
        $result = DashboardViewData::familyProfile([]);

        $this->assertSame('', $result['qrDataUri']);
    }
}
