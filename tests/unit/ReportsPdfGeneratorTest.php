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
            'Batch 1'
        );
        $this->assertStringStartsWith('%PDF-', $bytes);
    }

    /**
     * The report is a summary: the unclaimed count belongs in the KPI row, the
     * names do not. Listing them ran the file to a hundred-odd pages of roster
     * behind one page of report.
     */
    public function testTheUnclaimedFamiliesAreCountedNotListed(): void
    {
        $html = view('Scanner/pdf/report', [
            'coverage'   => ['eligible' => 3, 'served' => 2, 'remaining' => 1, 'coverage' => 67, 'voided' => 0],
            'byBarangay' => [],
            'perScanner' => [],
            'batchName'  => 'Batch 1',
        ], ['saveData' => false]);

        $this->assertStringContainsString('Remaining', $html);
        $this->assertStringNotContainsString('Remaining families', $html);
        $this->assertStringNotContainsString('Contact', $html);
    }
}
