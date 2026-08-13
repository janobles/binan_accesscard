<?php

namespace Tests\Unit;

use App\Models\Families\MemberModel;
use App\Models\Scanner\QrControlModel;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Database\DumpSchema;
use Tests\Support\Database\ReferentialFixture;

/**
 * Coverage: headsForCards()'s shape, limit, control range and keyword filter,
 * plus findHead() rejecting a non-head.
 *
 * Schema comes from the dump; each case seeds the heads it asserts on.
 */
final class MemberHeadsForCardsTest extends CIUnitTestCase
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
     * Three active heads with cards, in one barangay, plus one relative that
     * must never appear in a card selection. Control numbers are 101/102/103,
     * which the control-range cases assert against.
     */
    private function seedHeads(): MemberModel
    {
        $db = db_connect();

        $db->table('barangay')->insert(['barangayID' => 1, 'name' => 'SANTO TOMAS']);

        ReferentialFixture::heads($db, [1, 2, 3]);
        ReferentialFixture::cards($db, [1, 2, 3], 100);

        $db->table('member')->update(['barangayID' => 1], ['headID' => 1]);
        $db->table('member')->update(['barangayID' => 1], ['headID' => 2]);
        $db->table('member')->update(['barangayID' => 1], ['headID' => 3]);

        // A relative under head 1: heads-only selection must not return it.
        $db->table('member')->insert([
            'memberID' => 4, 'headID' => 1,
            'firstname' => 'ANA', 'middlename' => '', 'lastname' => 'FIXTURE',
            'barangayID' => 1,
        ]);

        return new MemberModel();
    }

    public function testHeadsForCardsReturnsExpectedShape(): void
    {
        $heads = $this->seedHeads()->headsForCards();

        $this->assertCount(3, $heads, 'three heads seeded, the relative is not a head');

        $first = $heads[0];
        $this->assertSame(1, $first['memberID']);
        $this->assertSame(101, $first['controlNo']);
        $this->assertSame('FIXTURE, HEAD1', $first['fullname']);
        $this->assertSame('SANTO TOMAS', $first['barangay']);
    }

    public function testFindHeadRejectsNonHead(): void
    {
        // memberID 4 is the relative seeded under head 1, never a head itself.
        $this->assertNull($this->seedHeads()->findHead(4));
    }

    public function testHeadsForCardsControlNoEqualsMappedControl(): void
    {
        $model = $this->seedHeads();
        $heads = $model->headsForCards();

        foreach ($heads as $head) {
            // Every returned head must resolve back through qr_control, so its
            // controlNo is a real mapping, never a memberID fallback.
            $this->assertSame(
                $head['memberID'],
                (new QrControlModel())->headForControl($head['controlNo']),
                'controlNo ' . $head['controlNo'] . ' must map back to its head via qr_control'
            );
        }
    }

    public function testCountHeadsForCardsMethodExists(): void
    {
        $this->assertTrue(
            method_exists(MemberModel::class, 'countHeadsForCards'),
            'countHeadsForCards() must exist for the preview table total.'
        );
    }

    public function testLimitSlicesTheSelection(): void
    {
        $model = $this->seedHeads();

        $this->assertCount(3, $model->headsForCards());
        $this->assertCount(1, $model->headsForCards(['limit' => 1]));
    }

    public function testControlRangeNarrowsToTheBoundedCards(): void
    {
        $model = $this->seedHeads();

        $ranged = $model->headsForCards(['controlFrom' => 102, 'controlTo' => 103]);

        $this->assertSame([102, 103], array_column($ranged, 'controlNo'));

        // The count method must agree with the ranged row count when no limit applies.
        $this->assertSame(
            count($ranged),
            $model->countHeadsForCards(['controlFrom' => 102, 'controlTo' => 103]),
            'countHeadsForCards() must match the unlimited ranged result size.'
        );
    }

    public function testKeywordMatchesOnName(): void
    {
        $model = $this->seedHeads();

        $hit = $model->headsForCards(['keyword' => 'HEAD2']);

        $this->assertCount(1, $hit);
        $this->assertSame(2, $hit[0]['memberID']);
    }
}
