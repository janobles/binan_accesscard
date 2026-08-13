<?php

namespace Tests\Unit;

use App\Models\Scanner\QrControlModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Database\DumpSchema;
use Tests\Support\Database\ReferentialFixture;

/**
 * The two edit-form paths from issue #14: a head with no qr_control row that is
 * given one on save (the backfill), and a head given a number another family
 * already owns (the rejection).
 *
 * Neither was ever driven live, because the dev dump held no orphan head. A
 * seeded orphan makes the coverage permanent instead of expiring at the next
 * reimport.
 */
final class FamilyUpdateQrControlTest extends CIUnitTestCase
{
    use FeatureTestTrait;

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
     * Head 1 carries a card (control 101); head 2 is the orphan, with no
     * qr_control row at all.
     */
    private function seedOrphanAndCardedHead(): void
    {
        $db = db_connect();

        ReferentialFixture::heads($db, [1, 2]);
        ReferentialFixture::cards($db, [1], 100);
    }

    /**
     * A real users row, so RoleAccess::sessionUserExists() (which the
     * roleNav filter checks) finds a match for the session's user_id.
     */
    private function encoderSession(): array
    {
        $db = db_connect();
        $db->table('users')->insert([
            'username'      => 'encoder-fixture',
            'password'      => 'x',
            'account_level' => 'encoder',
        ]);

        return [
            'is_logged_in' => true,
            'role'         => 'encoder',
            'user_id'      => (int) $db->insertID(),
        ];
    }

    /**
     * A complete `rulesForEntryType('head')` submission, so the request reaches
     * the QR branch instead of 422ing on a missing required field first.
     * $overrides is merged over the defaults, so a test only names the field it
     * cares about.
     */
    private function headPayload(array $overrides): array
    {
        return array_merge([
            '_form_end'        => '1',
            'head_firstname'   => 'JUAN',
            'head_middlename'  => '',
            'head_lastname'    => 'DELA CRUZ',
            'head_birthday'    => '1980-01-01',
            'head_sex'         => 'MALE',
            'head_civilstatus' => 'SINGLE',
            'head_education'   => 'COLLEGE',
            'head_job'         => 'LABORER',
            'head_salary'      => '5000',
            'head_address'     => 'PUROK 1',
            'head_barangay'    => 'CANLALAY',
            'qr_control_no'    => 205,
        ], $overrides);
    }

    public function testSavingAnOrphanHeadWritesItsControlRow(): void
    {
        $this->seedOrphanAndCardedHead();
        $qr = model(QrControlModel::class);

        $this->assertNull($qr->controlForHead(2), 'head 2 starts with no card');

        $this->withSession($this->encoderSession())
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post('records/2/update', $this->headPayload(['qr_control_no' => 205]));

        $this->assertSame(205, $qr->controlForHead(2), 'the save backfills the missing row');
    }

    public function testSavingANumberAnotherFamilyOwnsIsRejected(): void
    {
        $this->seedOrphanAndCardedHead();
        $qr = model(QrControlModel::class);

        $result = $this->withSession($this->encoderSession())
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post('records/2/update', $this->headPayload(['qr_control_no' => 101]));

        $this->assertSame(422, $result->response()->getStatusCode());
        $this->assertStringContainsString('already exists in the records', $result->getBody());
        $this->assertNull($qr->controlForHead(2), 'the rejected save writes nothing');
        $this->assertSame(1, $qr->headForControl(101), 'the owning family keeps its number');
    }
}
