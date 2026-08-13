<?php

namespace Tests\Unit;

use App\Models\Families\MemberModel;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Database\DumpSchema;
use Tests\Support\Database\ReferentialFixture;

/**
 * A card's barangay comes from the barangayID join, both for what is printed
 * and for what the batch filter selects.
 *
 * Issue #37: these were two mechanisms, an address LIKE in SQL and a PHP
 * derivation from the same address string, with nothing keeping them in step.
 * The V22 address/barangay split removed both. These cases stop the split ever
 * growing back: the address here deliberately names a different barangay from
 * the one the row points at, so any reader that goes back to parsing the
 * address fails.
 */
final class CardBarangayResolutionTest extends CIUnitTestCase
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

    /** One head in SANTO TOMAS whose address text names SAN VICENTE instead. */
    private function seedMismatchedHead(): MemberModel
    {
        $db = db_connect();

        $db->table('barangay')->insert(['barangayID' => 1, 'name' => 'SANTO TOMAS']);
        $db->table('barangay')->insert(['barangayID' => 2, 'name' => 'SAN VICENTE']);

        ReferentialFixture::heads($db, [1]);
        ReferentialFixture::cards($db, [1], 100);

        $db->table('member')->update(
            ['barangayID' => 1, 'address' => '123 MABINI ST, SAN VICENTE'],
            ['memberID' => 1]
        );

        return new MemberModel();
    }

    public function testThePrintedBarangayComesFromTheJoinNotTheAddress(): void
    {
        $heads = $this->seedMismatchedHead()->headsForCards();

        $this->assertCount(1, $heads);
        $this->assertSame('SANTO TOMAS', $heads[0]['barangay']);
    }

    public function testTheFilterSelectsOnTheSameValueThatPrints(): void
    {
        $model = $this->seedMismatchedHead();

        $this->assertCount(1, $model->headsForCards(['barangay' => 'SANTO TOMAS']));
        $this->assertSame([], $model->headsForCards(['barangay' => 'SAN VICENTE']));
    }

    public function testTheCountAgreesWithTheSelection(): void
    {
        $model = $this->seedMismatchedHead();

        $this->assertSame(1, $model->countHeadsForCards(['barangay' => 'SANTO TOMAS']));
        $this->assertSame(0, $model->countHeadsForCards(['barangay' => 'SAN VICENTE']));
    }
}
