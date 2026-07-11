<?php

namespace App\Libraries\Qr;

use chillerlan\QRCode\Output\QROutputAbstract;

use function pack, str_repeat, strlen;

/**
 * Pure-PHP PNG output for chillerlan/php-qrcode v6.
 * Produces a grayscale PNG (1 byte per pixel, 8-bit) without requiring ext-gd
 * to *build* it; dompdf still needs ext-gd to embed it. Dark modules render as
 * black (0x00), light modules as white (0xFF).
 */
final class QrPngOutput extends QROutputAbstract
{
    public const MIME_TYPE = 'image/png';

    public static function moduleValueIsValid(mixed $value): bool
    {
        return true;
    }

    protected function prepareModuleValue(mixed $value): bool
    {
        return (bool) $value;
    }

    protected function getDefaultModuleValue(bool $isDark): bool
    {
        return $isDark;
    }

    public function dump(string|null $file = null): string
    {
        $imageSize = $this->length; // moduleCount * scale
        $rawPixels = $this->buildRawPixelRows($imageSize);
        $pngBinary = $this->encodeToPng($imageSize, $rawPixels);

        $this->saveToFile($pngBinary, $file);

        if ($this->options->outputBase64) {
            return $this->toBase64DataURI($pngBinary, self::MIME_TYPE);
        }

        return $pngBinary;
    }

    /** @return string[] one filtered scanline per pixel row */
    private function buildRawPixelRows(int $imageSize): array
    {
        $matrixSize = $this->moduleCount;
        $scale      = $this->scale;
        $allScanlines = [];

        for ($moduleRow = 0; $moduleRow < $matrixSize; $moduleRow++) {
            $scanlinePixels = '';

            for ($moduleColumn = 0; $moduleColumn < $matrixSize; $moduleColumn++) {
                $moduleType  = $this->matrix->get($moduleColumn, $moduleRow);
                $isDarkPixel = $this->moduleValues[$moduleType] ?? false;
                $pixelByte   = $isDarkPixel ? "\x00" : "\xFF";
                $scanlinePixels .= str_repeat($pixelByte, $scale);
            }

            $filteredScanline = "\x00" . $scanlinePixels; // PNG filter byte 0 (None)

            for ($scaleRow = 0; $scaleRow < $scale; $scaleRow++) {
                $allScanlines[] = $filteredScanline;
            }
        }

        return $allScanlines;
    }

    private function encodeToPng(int $imageSize, array $allScanlines): string
    {
        $rawImageData        = implode('', $allScanlines);
        $compressedImageData = gzcompress($rawImageData, 6);

        $pngSignature = "\x89PNG\r\n\x1a\n";

        // IHDR: width, height, bit depth 8, colour type 0 (grayscale), no filter/interlace.
        $ihdrData  = pack('NNCCCCC', $imageSize, $imageSize, 8, 0, 0, 0, 0);
        $ihdrChunk = $this->buildPngChunk('IHDR', $ihdrData);
        $idatChunk = $this->buildPngChunk('IDAT', $compressedImageData);
        $iendChunk = $this->buildPngChunk('IEND', '');

        return $pngSignature . $ihdrChunk . $idatChunk . $iendChunk;
    }

    private function buildPngChunk(string $chunkType, string $chunkData): string
    {
        $dataLength = strlen($chunkData);
        $crc32Value = crc32($chunkType . $chunkData);

        return pack('N', $dataLength) . $chunkType . $chunkData . pack('N', $crc32Value & 0xFFFFFFFF);
    }
}
