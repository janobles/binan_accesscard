<?php

namespace Tests\Unit;

use App\Libraries\RoleAccess;
use CodeIgniter\Test\CIUnitTestCase;

/** Regression coverage for the database-backed Developer authority boundary. */
final class DeveloperRoleEnforcementTest extends CIUnitTestCase
{
    public function testDeveloperIsARecognizedDatabaseRole(): void
    {
        $this->assertSame('Developer', RoleAccess::normalizeRole('developer'));

        $model = file_get_contents(APPPATH . 'Models/Auth/UserModel.php');

        $this->assertIsString($model);
        $this->assertStringContainsString("['developer', 'administrator', 'encoder', 'viewer', 'scanner']", $model);
        $this->assertStringNotContainsString('DeveloperProfile', $model);
        $this->assertStringNotContainsString('verifyDeveloperLogin', $model);
    }

    public function testDeveloperAndAdministratorStatusAuthorityIsSeparated(): void
    {
        $controller = file_get_contents(APPPATH . 'Controllers/Accounts/AccountController.php');

        $this->assertIsString($controller);
        $this->assertStringContainsString("RoleAccess::requireRole(['Developer'])", $controller);
        $this->assertStringContainsString("['administrator', 'encoder', 'viewer', 'scanner']", $controller);
        $this->assertStringContainsString("['encoder', 'viewer', 'scanner']", $controller);
        $this->assertStringContainsString('Administrators cannot change another administrator account level.', $controller);
    }
}
