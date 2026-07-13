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
}
