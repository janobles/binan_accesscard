<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class ReportsAssetsTest extends CIUnitTestCase
{
    public function testScannerScriptContextOrdersChartBeforeInit(): void
    {
        $scripts = asset_scripts('scanner');
        $chart   = array_search('vendor/chart.js/chart.umd.min.js', $scripts, true);
        $init    = array_search('assets/js/dashboard/scanner-reports.js', $scripts, true);
        $this->assertNotFalse($chart, 'chart.js missing from scanner scripts');
        $this->assertNotFalse($init, 'scanner-reports.js missing from scanner scripts');
        $this->assertLessThan($init, $chart, 'chart.js must load before scanner-reports.js');
    }

    public function testScannerStyleContextHasReportsCss(): void
    {
        $this->assertContains('css/scanner-reports.css', asset_styles('scanner'));
    }

    public function testLayoutMergesScannerContext(): void
    {
        $layout = file_get_contents(APPPATH . 'Views/Scanner/layout.php');
        $this->assertStringContainsString("asset_scripts('scanner')", $layout);
        $this->assertStringContainsString("asset_styles('scanner')", $layout);
    }

    public function testChartJsVendored(): void
    {
        $this->assertFileExists(FCPATH . 'vendor/chart.js/chart.umd.min.js');
    }
}
