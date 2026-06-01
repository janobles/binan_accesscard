<?php

use App\Controllers\Admin\WorkspaceController as AdminWorkspaceController;
use App\Controllers\AccountController;
use App\Controllers\Employee\WorkspaceController as EmployeeWorkspaceController;
use App\Models\Employee\WorkspaceModel as EmployeeWorkspaceModel;
use PHPUnit\Framework\TestCase;

final class WorkspaceControllerRoutingTest extends TestCase
{
    public function testWorkspaceControllersExposeExpectedPageActions(): void
    {
        $this->assertPublicMethods(AdminWorkspaceController::class, [
            'index',
            'dashboard',
            'accounts',
            'familyEntry',
            'manageRecords',
            'auditTrails',
            'sectors',
            'services',
        ]);

        $this->assertPublicMethods(EmployeeWorkspaceController::class, [
            'dashboard',
            'familyEntry',
            'manageRecords',
            'activity',
        ]);
    }

    public function testRoutesUseWorkspaceControllersAndRetainCompatibilityAliases(): void
    {
        $routes = file_get_contents(APPPATH . 'Config/Routes.php');

        $this->assertIsString($routes);
        $this->assertStringNotContainsString('Home::', $routes);

        foreach ([
            "'family-entry', 'Admin\\WorkspaceController::familyEntry'",
            "'manage-members', 'Admin\\WorkspaceController::manageRecords'",
            "'manage-families', 'Admin\\WorkspaceController::manageRecords'",
            "'family-entry', 'Employee\\WorkspaceController::familyEntry'",
            "'manage-families', 'Employee\\WorkspaceController::manageRecords'",
        ] as $expectedRoute) {
            $this->assertStringContainsString($expectedRoute, $routes);
        }

        $this->assertStringContainsString("'sectors', 'Admin\\WorkspaceController::sectors'", $routes);
        $this->assertStringContainsString("'services', 'Admin\\WorkspaceController::services'", $routes);
        $this->assertStringNotContainsString("group('sectors'", $routes);
        $this->assertStringNotContainsString("group('services'", $routes);
        $this->assertStringNotContainsString("'SectorController::create'", $routes);
        $this->assertStringNotContainsString("'ServiceController::create'", $routes);
    }

    public function testEmployeeWorkspaceHasFocusedPagesAndOwnDataModel(): void
    {
        foreach ([
            'layout.php',
            'dashboard.php',
            'family-entry.php',
            'manage-records.php',
            'activity.php',
        ] as $view) {
            $this->assertFileExists(APPPATH . 'Views/Employee/' . $view);
        }

        $this->assertFileDoesNotExist(APPPATH . 'Views/Employee/index.php');
        $this->assertTrue(class_exists(EmployeeWorkspaceModel::class));
        $this->assertTrue(method_exists(EmployeeWorkspaceModel::class, 'pageData'));
        $this->assertTrue(method_exists(EmployeeWorkspaceModel::class, 'recordListData'));
    }

    public function testAdminAccountDisableRouteHasControllerAction(): void
    {
        $this->assertTrue(method_exists(AccountController::class, 'disableEmployee'));
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
