<?php

namespace Tests\Unit;

use App\Libraries\RoleAccess;
use CodeIgniter\Test\CIUnitTestCase;

final class ScannerRoleTest extends CIUnitTestCase
{
    public function testScannerNormalizes(): void
    {
        $this->assertSame('Scanner', RoleAccess::normalizeRole('scanner'));
        $this->assertSame('Scanner', RoleAccess::normalizeRole('Scanner'));
    }

    public function testScannerRedirectsToScanPage(): void
    {
        $response = RoleAccess::redirectByRole('scanner');
        $this->assertStringContainsString('scanner/scan', $response->getHeaderLine('Location'));
    }
}
