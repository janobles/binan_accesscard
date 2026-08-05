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

    /**
     * scanner/stats feeds the same page's 5-second poll. If it read the
     * session directly instead of resolveViewedScanner(), an admin looking at
     * a station's server-rendered figures would watch them get overwritten by
     * their own (usually zero) numbers a few seconds after page load - the
     * same silent wrong answer the override exists to prevent, just delayed.
     * Scoped to the stats() method body so this fails if that one call site
     * regresses, not merely if the string appears anywhere in the file.
     */
    public function testStatsPollHonoursTheSameOverrideAsThePage(): void
    {
        $src = $this->source();

        $start = strpos($src, 'function stats(');
        $this->assertNotFalse($start, 'stats() method not found');
        $end = strpos($src, 'function ', $start + 20);
        $this->assertNotFalse($end, 'could not find the method after stats()');
        $body = substr($src, $start, $end - $start);

        $this->assertStringContainsString('resolveViewedScanner()', $body);
        $this->assertStringNotContainsString("session('user_id')", $body);
    }
}
