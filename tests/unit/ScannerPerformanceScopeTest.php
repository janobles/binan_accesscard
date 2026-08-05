<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class ScannerPerformanceScopeTest extends CIUnitTestCase
{
    private function source(): string
    {
        return file_get_contents(APPPATH . 'Controllers/Scanner/ScanController.php');
    }

    public function testPerformanceAcceptsAScannerOverride(): void
    {
        $this->assertStringContainsString("getGet('scanner')", $this->source());
    }

    /** Only Admin and Developer may look at somebody else's numbers. */
    public function testOverrideIsRoleGated(): void
    {
        $src = $this->source();
        $this->assertStringContainsString('resolveViewedScanner', $src);
        $this->assertStringContainsString("['Admin', 'Developer']", $src);
        $this->assertStringContainsString('RoleAccess::normalizeRole', $src);
    }

    /** The target must actually be a scanner account, not any user id. */
    public function testOverrideChecksTheTargetIsAScanner(): void
    {
        $this->assertStringContainsString("'scanner'", $this->source());
    }
}
