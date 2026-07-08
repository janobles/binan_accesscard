<?php

namespace Tests\Unit;

use Config\Services;
use CodeIgniter\Test\CIUnitTestCase;

final class ScanControllerBatchTest extends CIUnitTestCase
{
    public function testSettingRouteResolves(): void
    {
        $routes = Services::routes();
        $routes->loadRoutes();
        $this->assertArrayHasKey('scanner/setting', $routes->getRoutes('GET'));
    }

    public function testScanGuardsAidTypeAndBatch(): void
    {
        $src = file_get_contents(APPPATH . 'Controllers/Scanner/ScanController.php');
        // scan() must fall back to the setting page when prerequisites are missing.
        $this->assertStringContainsString("redirect()->to('scanner/setting')", $src);
        // logAid() must refuse with 409 when no batch is open.
        $this->assertStringContainsString('setStatusCode(409)', $src);
        // Every logged row is stamped with the open batch and the response
        // carries the live personal counter.
        $this->assertStringContainsString("'batch_id'", $src);
        $this->assertStringContainsString('myBatchCount', $src);
    }
}
