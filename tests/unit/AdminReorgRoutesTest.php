<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;

final class AdminReorgRoutesTest extends CIUnitTestCase
{
    private array $getRoutes;
    private array $postRoutes;

    protected function setUp(): void
    {
        parent::setUp();
        $routes = Services::routes(true);
        require APPPATH . 'Config/Routes.php';
        $this->getRoutes  = $routes->getRoutes('GET');
        $this->postRoutes = $routes->getRoutes('POST');
    }

    /** New merged pages resolve to their controllers. */
    public function testMergedPagesResolve(): void
    {
        $expected = [
            ['admin/reference-data', $this->getRoutes, 'DashboardController::referenceData'],
            ['admin/distribution', $this->getRoutes, 'DistributionController::distribution'],
            ['admin/dashboard', $this->getRoutes, 'DashboardController::dashboard'],
            ['viewer/reference-data', $this->getRoutes, 'DashboardController::referenceData'],
        ];

        foreach ($expected as [$path, $routes, $handler]) {
            $this->assertArrayHasKey($path, $routes, $path);
            $this->assertStringContainsString($handler, (string) $routes[$path], $path);
        }
    }

    /** Old page GETs and dead aliases are gone. */
    public function testRemovedRoutesAreGone(): void
    {
        $removed = [
            'admin/sectors', 'admin/services', 'admin/categories', 'admin/aidtypes',
            'admin/batches', 'admin/distributions', 'admin/reports',
            'admin/manage-families', 'admin/manage-members', 'admin/family-entry',
            'admin/manage-family',
            'employee/family-entry', 'employee/manage-families',
            'viewer/sectors', 'viewer/services', 'viewer/manage-families',
        ];

        foreach ($removed as $path) {
            $this->assertArrayNotHasKey($path, $this->getRoutes, $path);
        }
    }

    /** Mutation endpoints and report data endpoints survive untouched. */
    public function testMutationAndDataEndpointsSurvive(): void
    {
        $expectedPost = [
            'admin/sectors/create', 'admin/services/create', 'admin/categories/create',
            'admin/aidtypes/create', 'admin/batches/open', 'admin/distributions/void/([0-9]+)',
        ];

        foreach ($expectedPost as $path) {
            $this->assertArrayHasKey($path, $this->postRoutes, $path);
        }

        $this->assertArrayHasKey('admin/reports/stats', $this->getRoutes);
        $this->assertArrayHasKey('admin/reports/pdf', $this->getRoutes);
        $this->assertArrayHasKey('admin/manage-family/data', $this->getRoutes);
    }
}
