<?php

namespace Tests\Unit;

use App\Models\Scanner\AidTypeModel;
use CodeIgniter\Test\CIUnitTestCase;

final class AidTypeModelTest extends CIUnitTestCase
{
    public function testActiveReturnsArray(): void
    {
        // Without a DB this returns []; the assertion pins the return contract.
        $this->assertIsArray((new AidTypeModel())->active());
    }

    public function testAllReturnsArray(): void
    {
        // No DB -> []; pins the contract that all() exists and returns array.
        $this->assertIsArray((new AidTypeModel())->all());
    }

    public function testCrudMethodsExist(): void
    {
        $model = new AidTypeModel();
        $this->assertTrue(method_exists($model, 'create'));
        $this->assertTrue(method_exists($model, 'archive'));
        $this->assertTrue(method_exists($model, 'restore'));
    }
}
