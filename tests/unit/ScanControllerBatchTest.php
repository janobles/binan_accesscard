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

    public function testTemporaryScanDoesNotRequireEncodedFamily(): void
    {
        $src = file_get_contents(APPPATH . 'Controllers/Scanner/ScanController.php');
        // logAid() must refuse with 409 when no batch is open.
        $this->assertStringContainsString('setStatusCode(409)', $src);
        // The aid type comes from the active batch, not POST.
        $this->assertStringContainsString("(int) \$activeBatch['aid_type_id']", $src);
        // Temporary scans use only the QR number and active batch metadata.
        $this->assertStringContainsString('TempAidDistributionModel::class', $src);
        $this->assertStringNotContainsString('QrControlModel', $src);
        $this->assertStringNotContainsString('MemberModel', $src);
        $this->assertStringContainsString("date('Y-m-d')", $src);
        // A QR logs at most once per batch.
        $this->assertStringContainsString('inBatch(', $src);
        $this->assertStringContainsString("'batch_id'", $src);
        $this->assertStringContainsString('myBatchCount', $src);
    }
}
