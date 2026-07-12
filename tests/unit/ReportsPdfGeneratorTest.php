<?php

namespace Tests\Unit;

use App\Libraries\Scanner\ReportsPdfGenerator;
use CodeIgniter\Test\CIUnitTestCase;

final class ReportsPdfGeneratorTest extends CIUnitTestCase
{
    public function testGeneratesPdfBytes(): void
    {
        $bytes = (new ReportsPdfGenerator())->generate(
            ['total' => 3, 'received' => 2, 'notReceived' => 1, 'coverage' => 67],
            [['barangay' => 'Poblacion', 'total' => 3, 'received' => 2, 'coverage' => 67]],
            [['service' => 'Relief Food Pack', 'service_code' => 'EDA8', 'count' => 5]],
            '2026-01-01',
            '2026-01-31'
        );
        $this->assertStringStartsWith('%PDF-', $bytes);
    }
}
