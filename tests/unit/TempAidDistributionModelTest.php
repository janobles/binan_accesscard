<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class TempAidDistributionModelTest extends CIUnitTestCase
{
    public function testTemporaryModelMatchesQrOnlySchema(): void
    {
        $src = file_get_contents(APPPATH . 'Models/Scanner/TempAidDistributionModel.php');

        foreach (['control_no', 'aid_type_id', 'claim_date', 'batch_id'] as $field) {
            $this->assertStringContainsString($field, $src);
        }
        $this->assertStringNotContainsString('memberID', $src);
        $this->assertStringNotContainsString('userID', $src);
    }
}
