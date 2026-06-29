<?php

namespace Tests\Unit;

use App\Libraries\Qr\ControlNumber;
use CodeIgniter\Test\CIUnitTestCase;

final class ControlNumberTest extends CIUnitTestCase
{
    public function testFormatZeroPadsToWidth(): void
    {
        $this->assertSame('000042', ControlNumber::format(42));
        $this->assertSame('000001', ControlNumber::format(1));
    }

    public function testParseStripsLeadingZeros(): void
    {
        $this->assertSame(42, ControlNumber::parse('000042'));
        $this->assertSame(123456, ControlNumber::parse('123456'));
    }

    public function testFormatParseRoundTrip(): void
    {
        foreach ([1, 7, 42, 999999] as $id) {
            $this->assertSame($id, ControlNumber::parse(ControlNumber::format($id)));
        }
    }

    public function testParseRejectsJunk(): void
    {
        $this->assertNull(ControlNumber::parse('00abc0'));
        $this->assertNull(ControlNumber::parse(''));
        $this->assertNull(ControlNumber::parse('000000')); // zero is not a valid memberID
        $this->assertNull(ControlNumber::parse('-5'));
    }
}
