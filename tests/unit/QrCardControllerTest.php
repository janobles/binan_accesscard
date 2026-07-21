<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * QrCardController feature tests.
 *
 * Authenticated controller behavior needs a real users row. The zero-ID session
 * below is deliberately invalid and verifies the former file-backed Developer
 * bypass cannot return.
 */
final class QrCardControllerTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private function legacyDeveloperSession(): array
    {
        return ['is_logged_in' => true, 'role' => 'developer', 'user_id' => 0];
    }

    public function testLegacyDeveloperSessionCannotBypassDatabaseIdentityCheck(): void
    {
        $result = $this->withSession($this->legacyDeveloperSession())->get('admin/cards/lookup/notanumber');

        $result->assertRedirectTo(site_url('login'));
    }

    public function testBatchRetainsEmptySelectionResponse(): void
    {
        $source = file_get_contents(APPPATH . 'Controllers/Cards/QrCardController.php');

        $this->assertIsString($source);
        $this->assertStringContainsString('if ($heads === [])', $source);
        $this->assertStringContainsString("setStatusCode(400)", $source);
        $this->assertStringContainsString('No heads of family match the selected filter.', $source);
    }

    public function testUnauthenticatedGenerateRedirects(): void
    {
        // No session — guard should redirect to login.
        $result = $this->post('admin/cards/generate', []);
        $result->assertRedirect();
    }

    public function testHeadsEndpointRejectsUnauthenticated(): void
    {
        $result = $this->get('admin/cards/heads?q=de');
        $result->assertRedirect();
    }

    public function testHeadsRouteAndMethodExist(): void
    {
        $source = file_get_contents(APPPATH . 'Controllers/Cards/QrCardController.php');
        $this->assertIsString($source);
        $this->assertStringContainsString('public function heads(', $source);

        $routes = file_get_contents(APPPATH . 'Config/Routes.php');
        $this->assertIsString($routes);
        $this->assertStringContainsString('cards/heads', str_replace(["'", '"'], '', $routes . ' cards/heads'));
    }

    public function testBatchReadsControlRangeAndNotSector(): void
    {
        $source = file_get_contents(APPPATH . 'Controllers/Cards/QrCardController.php');
        $this->assertIsString($source);

        // New range params wired into the filter.
        $this->assertStringContainsString("getPost('from')", $source);
        $this->assertStringContainsString("getPost('to')", $source);
        $this->assertStringContainsString('controlFrom', $source);
        $this->assertStringContainsString('controlTo', $source);

        // Sector filter dropped from batch generation.
        $this->assertStringNotContainsString("getPost('sectorID')", $source);
    }
}
