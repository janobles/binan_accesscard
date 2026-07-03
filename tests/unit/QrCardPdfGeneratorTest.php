<?php

namespace Tests\Unit;

use App\Libraries\Qr\QrCardPdfGenerator;
use CodeIgniter\Test\CIUnitTestCase;

final class QrCardPdfGeneratorTest extends CIUnitTestCase
{
    private function heads(int $count): array
    {
        $heads = [];
        for ($i = 1; $i <= $count; $i++) {
            $heads[] = ['memberID' => $i, 'fullname' => "Doe, Person {$i}", 'barangay' => 'Canlalay'];
        }

        return $heads;
    }

    public function testPageCountIsTwelvePerPage(): void
    {
        $generator = new QrCardPdfGenerator();

        $this->assertSame(1, $generator->pageCount(1));
        $this->assertSame(1, $generator->pageCount(12));
        $this->assertSame(2, $generator->pageCount(13));
        $this->assertSame(3, $generator->pageCount(25));
    }

    public function testEmptyInputThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        (new QrCardPdfGenerator())->generate([]);
    }

    public function testSingleChunkProducesPdf(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('ext-gd not loaded.');
        }

        $result = (new QrCardPdfGenerator())->generate($this->heads(3));

        $this->assertSame('pdf', $result['type']);
        $this->assertStringStartsWith('%PDF', $result['bytes']);
        $this->assertStringEndsWith('.pdf', $result['filename']);
    }
}
