<?php

namespace Tests\Unit;

use App\Models\Scanner\AidDistributionModel;
use CodeIgniter\Test\CIUnitTestCase;

final class AidDistributionModelTest extends CIUnitTestCase
{
    public function testHistoryForNonPositiveControlIsEmpty(): void
    {
        $this->assertSame([], (new AidDistributionModel())->historyFor(0));
    }

    public function testAllowedFieldsCoverInsertPayload(): void
    {
        $model  = new AidDistributionModel();
        $fields = (new \ReflectionClass($model))->getProperty('allowedFields');
        $fields->setAccessible(true);
        foreach (['control_no', 'memberID', 'aid_type_id', 'claim_date', 'userID'] as $col) {
            $this->assertContains($col, $fields->getValue($model));
        }
    }

    public function testAllDistributionsReturnsArray(): void
    {
        $this->assertIsArray((new \App\Models\Scanner\AidDistributionModel())->allDistributions());
    }

    public function testVoidMethodExists(): void
    {
        $this->assertTrue(method_exists(new \App\Models\Scanner\AidDistributionModel(), 'void'));
    }

    public function testHasClaimsRejectsNonPositiveControl(): void
    {
        $this->assertFalse((new AidDistributionModel())->hasClaims(0));
        $this->assertFalse((new AidDistributionModel())->hasClaims(-1));
    }
}
