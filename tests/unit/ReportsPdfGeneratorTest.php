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

    /**
     * The remaining-families heading must carry the row count so a reviewer
     * can reconcile it against the table below without counting rows by hand.
     * Rendered pre-dompdf so the count is asserted against plain HTML, not
     * PDF bytes.
     */
    public function testRemainingHeadingShowsTheCount(): void
    {
        $html = view('Scanner/pdf/report', [
            'coverage'   => ['eligible' => 3, 'served' => 2, 'remaining' => 1, 'coverage' => 67, 'voided' => 0],
            'byBarangay' => [],
            'remaining'  => [
                ['headID' => 1, 'name' => 'Juan Cruz', 'barangay' => 'Poblacion', 'contact' => ''],
                ['headID' => 2, 'name' => 'Ana Reyes', 'barangay' => 'Poblacion', 'contact' => ''],
            ],
            'perScanner' => [],
            'batchName'  => 'Batch 1',
        ], ['saveData' => false]);

        $this->assertStringContainsString('Remaining families (2)', $html);
    }
}
