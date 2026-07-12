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
        $this->assertStringContainsString("['administrator', 'encoder', 'viewer', 'scanner']", $model);
        $this->assertStringNotContainsString("whereIn('account_level', ['developer'", $model);
        $this->assertStringNotContainsString('DeveloperProfile', $model);
        $this->assertStringNotContainsString('verifyDeveloperLogin', $model);

        $searchModel = file_get_contents(APPPATH . 'Models/SearchModel.php');
        $accountView = file_get_contents(APPPATH . 'Views/Admin/accounts-body.php');

        $this->assertIsString($searchModel);
        $this->assertIsString($accountView);
        $this->assertStringNotContainsString("whereIn('account_level', ['developer'", $searchModel);
        $this->assertStringNotContainsString('option value="developer"', $accountView);
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
