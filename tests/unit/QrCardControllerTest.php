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
 * Auth gate: IdleTimeoutFilter only fires on an idle authenticated session — it
 * does NOT redirect unauthenticated requests. Unauthenticated requests reach the
 * controller, so no withSession() wrapper is needed here.
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

    public function testLookupWithInvalidControlThrows404(): void
    {
        // ControlNumber::parse('notanumber') returns null → PageNotFoundException.
        // CI4 re-throws PageNotFoundException in non-production environments.
        $this->expectException(PageNotFoundException::class);
        $this->get('admin/cards/lookup/notanumber');
    }

    public function testGenerateWithNoHeadsReturns400(): void
    {
        // Inject a stub that returns an empty head list regardless of filter.
        $stub = $this->createMock(MemberModel::class);
        $stub->method('headsForCards')->willReturn([]);
        Factories::injectMock('models', MemberModel::class, $stub);

        $result = $this->post('admin/cards/generate', [
            'barangay' => '__nonexistent_barangay__',
        ]);
        $result->assertStatus(400);
    }
}
