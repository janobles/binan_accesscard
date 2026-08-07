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

    public function testMaterializeReturnsFalseNotZeroOnWriteFailure(): void
    {
        // false must be distinguishable from a legitimately empty roster (0),
        // since DistributionBatchModel::open() uses this to decide whether to
        // discard the batch row it just inserted.
        $db = $this->createMock(\CodeIgniter\Database\BaseConnection::class);
        $db->method('table')->willThrowException(new \RuntimeException('forced failure'));

        $this->assertFalse((new EligibilityBuilder($db))->materialize(1, [], []));
    }
}
