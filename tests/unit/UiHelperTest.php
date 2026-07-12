<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use InvalidArgumentException;

/**
 * btn() maps semantic button roles to the Bootstrap classes documented in
 * docs/knowledge/binan-conventions/ui-design-system.md. The map is the single
 * source of truth for button colors across the dashboard toolbars.
 */
final class UiHelperTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('ui');
    }

    public function testKnownRolesReturnDocumentedClasses(): void
    {
        $this->assertSame('btn btn-primary', btn('search'));
        $this->assertSame('btn btn-danger', btn('clear'));
        $this->assertSame('btn btn-success', btn('add'));
        $this->assertSame('btn btn-warning', btn('import'));
        $this->assertSame('btn btn-outline-secondary', btn('filter'));
    }

    public function testUnknownRoleThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        btn('launch-missiles');
    }
}
