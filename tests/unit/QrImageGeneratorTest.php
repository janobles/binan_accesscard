<?php

namespace Tests\Unit;

use App\Libraries\Qr\QrImageGenerator;
use CodeIgniter\Test\CIUnitTestCase;

final class QrImageGeneratorTest extends CIUnitTestCase
{
    public function testDataUriReturnsBase64Png(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('ext-gd not loaded.');
        }

        $uri = (new QrImageGenerator())->dataUri('000042');

        $this->assertStringStartsWith('data:image/png;base64,', $uri);

        $base64 = substr($uri, strlen('data:image/png;base64,'));
        $binary = base64_decode($base64, true);
        $this->assertNotFalse($binary);
        // PNG magic bytes.
        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $binary);
    }
}
