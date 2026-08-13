<?php

namespace Tests\Unit;

use App\Libraries\EligibilityBuilder;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Database\DumpSchema;
use Tests\Support\Database\ReferentialFixture;

/**
 * Coverage: count() and materialize() against a real schema, so a broken
 * WHERE clause (an unprefixed table qualifier under a prefixed connection)
 * fails the test instead of silently returning an empty result.
 */
final class EligibilityBuilderTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DumpSchema::create(db_connect());
    }

    protected function tearDown(): void
    {
        DumpSchema::drop(db_connect());
        parent::tearDown();
    }

    /**
     * Three active heads with cards, in one barangay, one of them (head 2)
     * carrying a sector membership. Plus one relative under head 1, which
     * must never count as its own eligible head.
     */
    private function seedHeads(): void
    {
        $db = db_connect();

        $db->table('barangay')->insertBatch([
            ['barangayID' => 1, 'name' => 'SANTO TOMAS'],
            ['barangayID' => 2, 'name' => 'CANLALAY'],
        ]);
        $db->table('sector')->insert([
            'sectorID' => 1, 'shortcode' => 'PWD', 'name' => 'PWD', 'description' => 'PWD',
        ]);

        ReferentialFixture::heads($db, [1, 2, 3]);
        ReferentialFixture::cards($db, [1, 2, 3], 100);

        $db->table('member')->update(['barangayID' => 1], ['memberID' => 1]);
        $db->table('member')->update(['barangayID' => 1], ['memberID' => 2]);
        $db->table('member')->update(['barangayID' => 2], ['memberID' => 3]);

        $db->table('member_sectors')->insert(['memberID' => 2, 'sectorID' => 1]);

        // A relative under head 1: never its own eligible head.
        $db->table('member')->insert([
            'memberID' => 4, 'headID' => 1,
            'firstname' => 'ANA', 'middlename' => '', 'lastname' => 'FIXTURE',
            'barangayID' => 1,
        ]);
    }

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

    public function testCountMatchesSeededHeadsWithNoFilters(): void
    {
        $this->seedHeads();

        $this->assertSame(3, (new EligibilityBuilder())->count([], []));
    }

    public function testCountNarrowsToTheFilteredBarangay(): void
    {
        $this->seedHeads();

        $this->assertSame(2, (new EligibilityBuilder())->count([1], []));
        $this->assertSame(1, (new EligibilityBuilder())->count([2], []));
    }

    public function testCountNarrowsToTheFilteredSector(): void
    {
        $this->seedHeads();

        $this->assertSame(1, (new EligibilityBuilder())->count([], [1]));
    }

    public function testMaterializeWritesExactlyTheEligibleHeads(): void
    {
        $this->seedHeads();
        $db = db_connect();

        ReferentialFixture::subsidyType($db);
        $db->table('distribution_batch')->insert([
            'batch_id'        => 9,
            'name'            => 'Test batch',
            'subsidy_type_id' => 1,
        ]);

        $written = (new EligibilityBuilder())->materialize(9, [], []);

        $this->assertSame(3, $written);

        $roster = $db->table('batch_eligibility')->where('batch_id', 9)->get()->getResultArray();
        $headIds = array_map('intval', array_column($roster, 'headID'));
        sort($headIds);
        $this->assertSame([1, 2, 3], $headIds);
    }
}
