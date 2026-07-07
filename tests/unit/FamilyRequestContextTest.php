<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class FamilyRequestContextTest extends CIUnitTestCase
{
    public function testTraitExistsAndCarriesGuards(): void
    {
        $this->assertTrue(trait_exists(\App\Controllers\Families\FamilyRequestContext::class));

        foreach (['isEmployeeContext', 'currentRouteBase', 'partialGuard', 'recordMissing', 'jsonError', 'requireFamilyEntryAccess', 'requireFamilyViewAccess'] as $method) {
            $this->assertTrue(method_exists(\App\Controllers\Families\FamilyRequestContext::class, $method), $method);
        }
    }

    public function testFamilyControllerUsesTrait(): void
    {
        $this->assertContains(
            \App\Controllers\Families\FamilyRequestContext::class,
            class_uses(\App\Controllers\Families\FamilyController::class)
        );
    }
}
