<?php

namespace Tests\Unit;

use App\Models\Scanner\SubsidyDistributionModel;
use CodeIgniter\Test\CIUnitTestCase;

final class SubsidyDistributionModelTest extends CIUnitTestCase
{
    public function testHistoryForNonPositiveControlIsEmpty(): void
    {
        $this->assertSame([], (new SubsidyDistributionModel())->historyFor(0));
    }

    public function testAllowedFieldsCoverInsertPayload(): void
    {
        $model  = new SubsidyDistributionModel();
        $fields = (new \ReflectionClass($model))->getProperty('allowedFields');
        $fields->setAccessible(true);
        foreach (['control_no', 'memberID', 'subsidy_type_id', 'claim_date', 'userID'] as $col) {
            $this->assertContains($col, $fields->getValue($model));
        }
    }

    public function testAllDistributionsReturnsArray(): void
    {
        $this->assertIsArray((new \App\Models\Scanner\SubsidyDistributionModel())->allDistributions());
    }

    public function testVoidMethodExists(): void
    {
        $this->assertTrue(method_exists(new \App\Models\Scanner\SubsidyDistributionModel(), 'void'));
    }

    public function testHasClaimsRejectsNonPositiveControl(): void
    {
        $this->assertFalse((new SubsidyDistributionModel())->hasClaims(0));
        $this->assertFalse((new SubsidyDistributionModel())->hasClaims(-1));
    }

    public function testFamiliesForUserInBatchRejectsNonPositiveIds(): void
    {
        $m = new SubsidyDistributionModel();
        $this->assertSame(0, $m->familiesForUserInBatch(0, 1));
        $this->assertSame(0, $m->familiesForUserInBatch(1, 0));
    }

    public function testLogAidAcceptsBatchIdKeyWithoutError(): void
    {
        // No DB in unit posture: invalid control_no short-circuits before insert,
        // proving the signature tolerates the new key.
        $m = new SubsidyDistributionModel();
        $this->assertSame(0, $m->logAid(['control_no' => 0, 'batch_id' => 5]));
    }
}
