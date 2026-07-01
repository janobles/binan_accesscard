<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class MemberFamilyFieldsTest extends CIUnitTestCase
{
    public function testFamilyMembersSelectsIdentityFields(): void
    {
        $src = file_get_contents(APPPATH . 'Models/Families/MemberModel.php');
        // familyMembers() must select birthday and sex for scan-panel identity checks.
        $this->assertMatchesRegularExpression('/familyMembers.*?birthday.*?sex/s', $src);
    }
}
