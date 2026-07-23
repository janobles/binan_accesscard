<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;

final class FamilyRoutesTest extends CIUnitTestCase
{
    /** Route → handler pairs that must survive the FamilyController split. */
    public function testFamilyRoutesResolveToSplitControllers(): void
    {
        $routes = Services::routes(true);
        require APPPATH . 'Config/Routes.php';
        $getRoutes  = $routes->getRoutes('GET');
        $postRoutes = $routes->getRoutes('POST');

        $expected = [
            ['admin/manage-family/import', $getRoutes, 'FamilyImportController::importForm'],
            ['admin/manage-family/template', $getRoutes, 'FamilyImportController::downloadTemplate'],
            ['employee/manage-family/import', $postRoutes, 'FamilyImportController::import'],
            ['admin/manage-family/data', $getRoutes, 'FamilyDataTableController::dataTable'],
            ['employee/manage-family/data', $getRoutes, 'FamilyDataTableController::dataTable'],
            ['viewer/manage-family/data', $getRoutes, 'FamilyDataTableController::dataTable'],
            ['admin/manage-family/view/([0-9]+)', $getRoutes, 'FamilyController::viewFamily'],
            ['admin/manage-family/qr-check', $getRoutes, 'FamilyController::qrAvailability'],
            ['employee/manage-family/qr-check', $getRoutes, 'FamilyController::qrAvailability'],
            ['families', $postRoutes, 'FamilyController::store'],
        ];

        foreach ($expected as [$path, $routes, $handler]) {
            $this->assertArrayHasKey($path, $routes, $path);
            $this->assertStringContainsString($handler, (string) $routes[$path], $path);
        }
    }
}
