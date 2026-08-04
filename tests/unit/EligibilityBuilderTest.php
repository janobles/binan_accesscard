<?php

namespace Tests\Unit;

use App\Libraries\EligibilityBuilder;
use CodeIgniter\Test\CIUnitTestCase;

final class EligibilityBuilderTest extends CIUnitTestCase
{
    public function testCountReturnsIntWithNoFilters(): void
    {
        $this->assertIsInt((new EligibilityBuilder())->count([], []));
    }

    public function testMaterializeRefusesNonPositiveBatch(): void
    {
        $this->assertSame(0, (new EligibilityBuilder())->materialize(0, [], []));
    }

    public function testCountIsNeverNegative(): void
    {
        $this->assertGreaterThanOrEqual(0, (new EligibilityBuilder())->count([1], [2]));
    }
}
