<?php

namespace Tests\Unit;

use App\Models\Families\MemberModel;
use CodeIgniter\Config\Factories;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * QrCardController feature tests.
 *
 * Auth gate: RoleAccess::requireRole() checks session('is_logged_in') and
 * session('role'). Developer role skips the DB row existence check, so tests
 * use ['is_logged_in' => true, 'role' => 'developer', 'user_id' => 0] to
 * authenticate without a live DB dependency.
 *
 * PageNotFoundException behaviour: CI4 re-throws PageNotFoundException in any
 * non-production environment instead of returning a 404 response. assertStatus(404)
 * would therefore never pass; expectException() is the correct assertion.
 *
 * MemberModel: the controller retrieves the model via model() so tests can inject
 * a mock via Factories::injectMock(). This avoids a live DB dependency for the
 * 400-on-empty-heads assertion.
 */
final class QrCardControllerTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    /** Developer session — no DB row check, no live DB needed. */
    private function devSession(): array
    {
        return ['is_logged_in' => true, 'role' => 'developer', 'user_id' => 0];
    }

    public function testLookupWithInvalidControlThrows404(): void
    {
        // ControlNumber::parse('notanumber') returns null → PageNotFoundException.
        // CI4 re-throws PageNotFoundException in non-production environments.
        $this->expectException(PageNotFoundException::class);
        $this->withSession($this->devSession())->get('admin/cards/lookup/notanumber');
    }

    public function testGenerateWithNoHeadsReturns400(): void
    {
        // Inject a stub that returns an empty head list regardless of filter.
        $stub = $this->createMock(MemberModel::class);
        $stub->method('headsForCards')->willReturn([]);
        Factories::injectMock('models', MemberModel::class, $stub);

        $result = $this->withSession($this->devSession())->post('admin/cards/generate', [
            'barangay' => '__nonexistent_barangay__',
        ]);
        $result->assertStatus(400);
    }

    public function testUnauthenticatedGenerateRedirects(): void
    {
        // No session — guard should redirect to login.
        $result = $this->post('admin/cards/generate', []);
        $result->assertRedirect();
    }
}
