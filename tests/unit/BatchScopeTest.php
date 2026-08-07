<?php

namespace Tests\Unit;

use App\Libraries\Scanner\BatchScope;
use CodeIgniter\Test\CIUnitTestCase;

final class BatchScopeTest extends CIUnitTestCase
{
    public function testRequestedBatchWins(): void
    {
        $batches = [['batch_id' => 1, 'name' => 'A'], ['batch_id' => 2, 'name' => 'B']];
        [$id, $row] = BatchScope::resolve($batches, ['batch_id' => 1], 2);
        $this->assertSame(2, $id);
        $this->assertSame('B', $row['name']);
    }

    public function testFallsBackToActiveBatch(): void
    {
        $batches = [['batch_id' => 1, 'name' => 'A'], ['batch_id' => 2, 'name' => 'B']];
        [$id] = BatchScope::resolve($batches, ['batch_id' => 2], 0);
        $this->assertSame(2, $id);
    }

    public function testFallsBackToNewestWhenNoneActive(): void
    {
        $batches = [['batch_id' => 5, 'name' => 'E'], ['batch_id' => 4, 'name' => 'D']];
        [$id] = BatchScope::resolve($batches, null, 0);
        $this->assertSame(5, $id);
    }

    public function testUnknownBatchResolvesToZero(): void
    {
        [$id, $row] = BatchScope::resolve([['batch_id' => 1, 'name' => 'A']], null, 99);
        $this->assertSame(0, $id);
        $this->assertNull($row);
    }
}
