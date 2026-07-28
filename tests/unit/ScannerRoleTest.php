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

    public function testEncoderIsTheOnlySpellingOfTheEncodingRole(): void
    {
        $this->assertSame('Encoder', RoleAccess::normalizeRole('encoder'));
        $this->assertSame('Encoder', RoleAccess::normalizeRole('Encoder'));
    }

    public function testLegacyEncodingAliasesAreRejected(): void
    {
        $this->assertNull(RoleAccess::normalizeRole('user'));
        $this->assertNull(RoleAccess::normalizeRole('employee'));
    }

    public function testAuditRoleLabelIsGone(): void
    {
        $this->assertFalse(
            method_exists(RoleAccess::class, 'auditRoleLabel'),
            'auditRoleLabel exists only to bridge two spellings of one role.'
        );
    }
}
