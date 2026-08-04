<?php

namespace Tests\Unit;

use App\Libraries\Scanner\ReportsPdfGenerator;
use CodeIgniter\Test\CIUnitTestCase;

final class ReportsPdfGeneratorTest extends CIUnitTestCase
{
    public function testGeneratesPdfBytes(): void
    {
        $bytes = (new ReportsPdfGenerator())->generate(
            ['eligible' => 3, 'served' => 2, 'remaining' => 1, 'coverage' => 67, 'voided' => 0],
            [['barangay' => 'Poblacion', 'total' => 3, 'received' => 2, 'coverage' => 67]],
            [['headID' => 1, 'name' => 'Juan Cruz', 'barangay' => 'Poblacion', 'contact' => '']],
            'Batch 1'
        );
        $this->assertStringStartsWith('%PDF-', $bytes);
    }
}
