<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class MemberFamilyFieldsTest extends CIUnitTestCase
{
    public function testFamilyMembersSelectsIdentityFields(): void
    {
        $src = file_get_contents(APPPATH . 'Models/Families/MemberModel.php');
        // Isolate familyMembers() body, then assert it selects birthday + sex.
        preg_match('/function familyMembers\s*\([^)]*\)\s*:\s*array\s*\{.*?\n    \}/s', $src, $m);
        $this->assertNotEmpty($m, 'familyMembers() method not found');
        $this->assertMatchesRegularExpression('/birthday/', $m[0]);
        $this->assertMatchesRegularExpression('/sex/', $m[0]);
    }
}
