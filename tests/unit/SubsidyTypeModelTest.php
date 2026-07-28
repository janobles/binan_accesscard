<?php

namespace Tests\Unit;

use App\Models\Scanner\SubsidyTypeModel;
use CodeIgniter\Test\CIUnitTestCase;

final class SubsidyTypeModelTest extends CIUnitTestCase
{
    public function testActiveReturnsArray(): void
    {
        // Without a DB this returns []; the assertion pins the return contract.
        $this->assertIsArray((new SubsidyTypeModel())->active());
    }

    public function testAllReturnsArray(): void
    {
        // No DB -> []; pins the contract that all() exists and returns array.
        $this->assertIsArray((new SubsidyTypeModel())->all());
    }

    public function testCrudMethodsExist(): void
    {
        $model = new SubsidyTypeModel();
        $this->assertTrue(method_exists($model, 'create'));
        $this->assertTrue(method_exists($model, 'archive'));
        $this->assertTrue(method_exists($model, 'restore'));
    }
}
