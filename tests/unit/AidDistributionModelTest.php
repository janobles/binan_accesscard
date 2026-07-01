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
}
