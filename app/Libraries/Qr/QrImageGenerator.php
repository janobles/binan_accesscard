<?php

namespace App\Libraries\Qr;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Common\EccLevel;

final class QrImageGenerator
{
    private QROptions $pngOptions;

    public function __construct()
    {
        $this->pngOptions = new QROptions([
            'outputInterface' => QrPngOutput::class,
            'eccLevel'        => EccLevel::M,
            // scale 4 keeps the code crisp at the ~1.4in print size while
            // producing a smaller PNG that dompdf embeds quickly.
            'scale'           => 4,
            'outputBase64'    => true,
        ]);
    }

    public function dataUri(string $content): string
    {
        // A fresh QRCode per call: a reused instance accumulates data segments
        // across render() calls and eventually exceeds QR capacity.
        return (new QRCode($this->pngOptions))->render($content);
    }
}
