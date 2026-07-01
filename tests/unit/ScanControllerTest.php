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
    }

    public function testGuardRolesIncludeScanner(): void
    {
        $src = file_get_contents(APPPATH . 'Controllers/Scanner/ScanController.php');
        $this->assertStringContainsString("requireRole(['Scanner', 'Admin', 'Developer'])", $src);
    }
}
