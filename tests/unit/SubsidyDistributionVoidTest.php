<?php

namespace Tests\Unit;

use App\Models\Scanner\SubsidyDistributionModel;
use CodeIgniter\Test\CIUnitTestCase;

final class SubsidyDistributionVoidTest extends CIUnitTestCase
{
    public function testVoidRejectsNonPositiveId(): void
    {
        $this->assertFalse((new SubsidyDistributionModel())->void(0));
    }

    public function testVoidIsNotADelete(): void
    {
        $source = file_get_contents(APPPATH . 'Models/Scanner/SubsidyDistributionModel.php');
        $voidBody = substr($source, strpos($source, 'function void'));
        $voidBody = substr($voidBody, 0, strpos($voidBody, "\n    }"));
        $this->assertStringNotContainsString('delete(', $voidBody, 'void must soft-void, not delete');
        $this->assertStringContainsString('dt_voided', $voidBody);
    }
}
