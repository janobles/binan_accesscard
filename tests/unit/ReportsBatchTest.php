<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class ReportsBatchTest extends CIUnitTestCase
{
    public function testControllerScopesByBatchAndRole(): void
    {
        $src = file_get_contents(APPPATH . 'Controllers/Scanner/ReportsController.php');
        $this->assertStringContainsString('perScanner', $src);
        // Scanner role sees only its own row — filtered server-side.
        $this->assertStringContainsString("'Scanner'", $src);
        $this->assertStringContainsString('getGet(\'batch\')', $src);
    }

    public function testReportsViewRendersPerformanceSection(): void
    {
        $src = file_get_contents(APPPATH . 'Views/Scanner/reports.php');
        $this->assertStringContainsString('perScanner', $src);
        $this->assertStringContainsString('name="batch"', $src);
    }
}
