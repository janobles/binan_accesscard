<?php

use App\Controllers\Accounts\AccountController;
use App\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Controllers\Employee\DashboardController as EmployeeDashboardController;
use App\Controllers\Families\FamilyController;
use App\Controllers\Lookups\SectorController;
use App\Controllers\Lookups\ServiceController;
use PHPUnit\Framework\TestCase;

/**
 * Guards the feature-subnamespace layout of the backend.
 *
 * Admin/developer dashboard pages live in App\Controllers\Admin\DashboardController,
 * the employee workspace in App\Controllers\Employee\DashboardController,
 * authentication in App\Controllers\Auth, family flows in App\Controllers\Families,
 * lookup mutations in App\Controllers\Lookups, and account management in
 * App\Controllers\Accounts. These assertions fail loudly if a controller is moved
 * back to the root namespace or a route stops targeting its feature slice.
 */
final class DashboardControllerRoutingTest extends TestCase
{
    public function testAdminDashboardExposesExpectedPageActions(): void
    {
        $this->assertPublicMethods(AdminDashboardController::class, [
            // Admin / Developer pages
            'index',
            'dashboard',
            'accounts',
            'familyEntry',
            'manageRecords',
            'auditTrails',
            'sectors',
            'services',
            'manageMembers',
        ]);
    }

    public function testEmployeeDashboardExposesExpectedPageActions(): void
    {
        $this->assertPublicMethods(EmployeeDashboardController::class, [
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
            "'dashboard', 'Admin\\DashboardController::dashboard'",
            "'workspace', 'Employee\\DashboardController::dashboard'",
            "'activity', 'Employee\\DashboardController::activity'",
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
