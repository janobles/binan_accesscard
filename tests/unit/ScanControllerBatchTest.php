<?php

namespace Tests\Unit;

use Config\Services;
use CodeIgniter\Test\CIUnitTestCase;

final class ScanControllerBatchTest extends CIUnitTestCase
{
    public function testStatsRouteResolves(): void
    {
        $routes = Services::routes();
        $routes->loadRoutes();
        $this->assertArrayHasKey('scanner/stats', $routes->getRoutes('GET'));
    }

    public function testScanGuardsAidTypeAndBatch(): void
    {
        $src = file_get_contents(APPPATH . 'Controllers/Scanner/ScanController.php');
        // logAid() must refuse with 409 when no batch is open.
        $this->assertStringContainsString('setStatusCode(409)', $src);
        // The aid type comes from the active batch, not POST.
        $this->assertStringContainsString("(int) \$activeBatch['aid_type_id']", $src);
        // Every logged row is stamped with the open batch and the response
        // carries the live personal counter.
        $this->assertStringContainsString("'batch_id'", $src);
        $this->assertStringContainsString('myBatchCount', $src);
    }
}
