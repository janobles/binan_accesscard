<?php

namespace Tests\Unit;

use Config\Services;
use CodeIgniter\Test\CIUnitTestCase;

final class ManageControllerTest extends CIUnitTestCase
{
    public function testManageRoutesResolve(): void
    {
        $routes = Services::routes();
        $routes->loadRoutes();
        $get  = $routes->getRoutes('GET');
        $post = $routes->getRoutes('POST');
        $this->assertArrayHasKey('scanner/manage', $get);
        $this->assertArrayHasKey('scanner/aid-types/create', $post);
        $this->assertArrayHasKey('scanner/distributions/void/([0-9]+)', $post);
    }

    public function testEveryActionGuardsScannerRole(): void
    {
        $src = file_get_contents(APPPATH . 'Controllers/Scanner/ManageController.php');
        // Guard literal appears once per action (manage, create, archive, restore, delete, void).
        $this->assertGreaterThanOrEqual(6, substr_count($src, "requireRole(['Scanner', 'Admin', 'Developer'])"));
        // Mutations are audited.
        $this->assertStringContainsString('logAction', $src);
    }

    public function testBatchRoutesResolve(): void
    {
        $routes = Services::routes();
        $routes->loadRoutes();
        $post = $routes->getRoutes('POST');
        $this->assertArrayHasKey('scanner/batches/open', $post);
        $this->assertArrayHasKey('scanner/batches/close/([0-9]+)', $post);
    }

    public function testBatchActionsGuardAdminOnly(): void
    {
        $src = file_get_contents(APPPATH . 'Controllers/Scanner/ManageController.php');
        // Batch lifecycle is Admin/Developer only (stricter than the page guard).
        $this->assertGreaterThanOrEqual(2, substr_count($src, "requireRole(['Admin', 'Developer'])"));
    }
}
