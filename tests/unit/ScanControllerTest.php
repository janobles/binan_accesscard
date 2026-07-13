<?php

namespace Tests\Unit;

use Config\Services;
use CodeIgniter\Test\CIUnitTestCase;

final class ScanControllerTest extends CIUnitTestCase
{
    public function testScannerRoutesResolve(): void
    {
        $routes = Services::routes();
        $routes->loadRoutes();
        $map = $routes->getRoutes('GET');
        $this->assertArrayHasKey('scanner/scan', $map);
        $this->assertArrayHasKey('scanner/performance', $map);
        $this->assertArrayHasKey('scanner/stats', $map);

        $postRoutes = $routes->getRoutes('POST');
        $this->assertArrayHasKey('scanner/log', $postRoutes);
        $this->assertArrayHasKey('scanner/void', $postRoutes);
    }

    public function testGuardRolesIncludeScanner(): void
    {
        $src = file_get_contents(APPPATH . 'Controllers/Scanner/ScanController.php');
        $this->assertStringContainsString("requireRole(['Scanner', 'Admin', 'Developer'])", $src);
    }

    public function testScanViewLogsInline(): void
    {
        $src = file_get_contents(APPPATH . 'Views/Scanner/scan.php');
        // Logging happens in the Scan tab now: a log form posting to scanner/log.
        $this->assertStringContainsString('scanner/log', $src);
        $this->assertStringContainsString('scanner/void', $src);
    }
}
