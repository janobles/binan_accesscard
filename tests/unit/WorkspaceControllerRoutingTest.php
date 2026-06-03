<?php

use App\Controllers\Accounts\AccountController;
use App\Controllers\Employee\WorkspaceController as EmployeeWorkspaceController;
use App\Controllers\Families\FamilyController;
use App\Controllers\Lookups\SectorController;
use App\Controllers\Lookups\ServiceController;
use App\Controllers\Workspace\HomeController;
use PHPUnit\Framework\TestCase;

/**
 * Guards the feature-subnamespace layout of the backend.
 *
 * Admin/developer dashboard pages live in App\Controllers\Workspace\HomeController,
 * authentication in App\Controllers\Auth, the employee workspace in
 * App\Controllers\Employee, family flows in App\Controllers\Families, lookup
 * mutations in App\Controllers\Lookups, and account management in
 * App\Controllers\Accounts. These assertions fail loudly if a controller is moved
 * back to the root namespace or a route stops targeting its feature slice.
 */
final class WorkspaceControllerRoutingTest extends TestCase
{
    public function testWorkspaceHomeExposesExpectedPageActions(): void
    {
        $this->assertPublicMethods(HomeController::class, [
            // Admin / Developer pages
            'admin',
            'adminDashboard',
            'adminAccounts',
            'adminFamilyEntry',
            'adminManageRecords',
            'adminAuditTrails',
            'adminSectors',
            'adminServices',
        ]);
    }

    public function testEmployeeWorkspaceControllerExposesExpectedPageActions(): void
    {
        $this->assertPublicMethods(EmployeeWorkspaceController::class, [
            'dashboard',
            'familyEntry',
            'manageRecords',
            'activity',
        ]);
    }

    public function testFeatureControllersExposeExpectedActions(): void
    {
        $this->assertPublicMethods(FamilyController::class, [
            'store',
            'listFamilies',
            'viewFamily',
            'editFamily',
            'update',
            'archive',
            'restore',
            'delete',
        ]);

        $this->assertPublicMethods(SectorController::class, [
            'create',
            'update',
            'archive',
            'restore',
        ]);

        $this->assertPublicMethods(ServiceController::class, [
            'create',
            'update',
            'archive',
            'restore',
        ]);

        $this->assertTrue(method_exists(AccountController::class, 'disableEmployee'));
        $this->assertTrue(method_exists(AccountController::class, 'create'));
    }

    public function testRoutesTargetFeatureSubnamespaces(): void
    {
        $routes = file_get_contents(APPPATH . 'Config/Routes.php');

        $this->assertIsString($routes);

        // The root-namespace controllers were moved into feature slices; the
        // bare targets must no longer appear in the route table.
        $this->assertStringNotContainsString("'Home::", $routes);
        $this->assertStringNotContainsString("'AccountController::", $routes);
        $this->assertStringNotContainsString("'FamilyController::", $routes);
        $this->assertStringNotContainsString("'SectorController::", $routes);
        $this->assertStringNotContainsString("'ServiceController::", $routes);

        foreach ([
            "'dashboard', 'Workspace\\HomeController::adminDashboard'",
            "'workspace', 'Employee\\WorkspaceController::dashboard'",
            "'activity', 'Employee\\WorkspaceController::activity'",
            "'accounts/disable', 'Accounts\\AccountController::disableEmployee'",
            "'list', 'Families\\FamilyController::listFamilies'",
            "'create', 'Lookups\\SectorController::create'",
            "'create', 'Lookups\\ServiceController::create'",
            "'families', 'Families\\FamilyController::store'",
        ] as $expectedRoute) {
            $this->assertStringContainsString($expectedRoute, $routes);
        }
    }

    /**
     * @param list<string> $methods
     */
    private function assertPublicMethods(string $class, array $methods): void
    {
        $reflection = new ReflectionClass($class);

        foreach ($methods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method) && $reflection->getMethod($method)->isPublic(),
                $class . '::' . $method . ' must remain a public route action.'
            );
        }
    }
}
