<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Database\DumpSchema;

/**
 * Feature coverage for FamilyController::profile() through the real route,
 * session filters, and controller - not just Family/profile.php rendered in
 * isolation, which is all FamilyProfilePageTest exercises. This is the surface
 * fix round 1 found broken: the missing truncation sentinel, the missing JS
 * init marker, the Salary/salary casing miss, and the active-only option list.
 *
 * Schema comes from the dump (Tests\Support\Database\DumpSchema), so the
 * `member`/`member_services`/`qr_control`/`sector`/`users` tables this needs
 * carry the column set production runs on.
 */
final class FamilyControllerProfileTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        DumpSchema::create(db_connect());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        DumpSchema::drop(db_connect());
    }

    public function testEditPageIsEditableSavableAndKeepsArchivedAssignments(): void
    {
        $db = db_connect();

        $db->table('users')->insert(['account_level' => 'admin']);
        $userId = (int) $db->insertID();

        // Archived (dt_deleted set) - absent from SectorModel::getActive()'s list -
        // but still assigned to the head below. getViewData() would silently drop
        // it from the rendered options; getViewDataForEdit() must grandfather it in.
        $db->table('sector')->insert([
            'sectorID' => 99, 'shortcode' => 'ARC', 'name' => 'Archived Sector',
            'description' => 'x', 'dt_deleted' => date('Y-m-d H:i:s'),
        ]);

        $db->table('member')->insert([
            'memberID'  => 7,
            'lastname'  => 'DELA CRUZ',
            'firstname' => 'JUAN',
            'middlename' => '',
            'headID'    => 7,
            'sectorID'  => '[99]',
            'Salary'    => 8000,
            'address'   => 'Purok 1, Canlalay',
        ]);

        $result = $this->withSession([
            'is_logged_in' => true,
            'role'         => 'admin',
            'user_id'      => $userId,
        ])->get('records/7/edit');

        $result->assertStatus(200);
        $html = (string) $result->response()->getBody();

        // Critical 1: the truncation sentinel FamilyController::submissionWasTruncated()
        // requires on every save - omitting it 422s every Save from this page.
        $this->assertStringContainsString('name="_form_end" value="1"', $html);
        $this->assertStringContainsString('data-members-count', $html);

        // Critical 2: the marker manage-family-modal.js's initFamilyEntryPage() looks
        // for to wire up member-row toggling, Other-selects, and the AJAX submit
        // handler on a page with no control-number gate.
        $this->assertStringContainsString('data-family-entry-form', $html);

        // Important 1: member.Salary (capital S) must resolve to the lowercase
        // head_salary field name the renderer looks up, or the required select
        // renders with nothing selected and the browser blocks Save.
        $this->assertMatchesRegularExpression(
            '/<select[^>]*name="head_salary"[^>]*>.*?<option value="8000"[^>]*selected/s',
            $html
        );

        // Important 2: the archived-but-assigned sector must still render, checked -
        // proof the page used getViewDataForEdit() (which grandfathers it in), not
        // getViewData() (active-only, which would drop it and delete it on save).
        $this->assertMatchesRegularExpression('/value="99"[^>]*checked/', $html);
    }

    public function testViewerCannotReachTheUpdateRoute(): void
    {
        $db = db_connect();

        $db->table('users')->insert(['account_level' => 'viewer']);
        $userId = (int) $db->insertID();

        $db->table('member')->insert([
            'memberID' => 7, 'lastname' => 'DELA CRUZ', 'firstname' => 'JUAN',
            'middlename' => '', 'headID' => 7, 'sectorID' => '[]', 'Salary' => 0,
        ]);

        $session = [
            'is_logged_in' => true,
            'role'         => 'viewer',
            'user_id'      => $userId,
        ];

        $profile = $this->withSession($session)->get('records/7');
        $profile->assertStatus(200);

        $html = (string) $profile->response()->getBody();
        $this->assertStringNotContainsString('data-family-save', $html, 'A Viewer must not get a Save button.');
        $this->assertStringNotContainsString('<form', $html, 'The read view prints the record; it carries no form at all.');

        // records/{id} is read-only for everyone, so the edit page is a separate
        // route the manifest keeps off a Viewer entirely.
        $edit = $this->withSession($session)->get('records/7/edit');
        $this->assertNotSame(200, $edit->response()->getStatusCode());

        // The manifest keeps records-update off Viewer, so the update route itself
        // must reject a Viewer, independent of what the profile page renders.
        $update = $this->withSession($session)->post('records/7/update', []);
        $this->assertNotSame(200, $update->response()->getStatusCode());
    }

    /** @return array{is_logged_in: true, role: string, user_id: int} */
    private function encoderSession(): array
    {
        $db = db_connect();
        $db->table('users')->insert(['account_level' => 'encoder']);

        return [
            'is_logged_in' => true,
            'role'         => 'encoder',
            'user_id'      => (int) $db->insertID(),
        ];
    }

    public function testAnAvailableNumberCarriesAQrPreview(): void
    {
        $response = $this->withSession($this->encoderSession())
            ->get('records/qr-check?control_no=999999&head_id=0');

        $json = json_decode($response->getJSON(), true);

        $this->assertTrue($json['available']);
        $this->assertArrayHasKey('qr', $json);
        $this->assertStringStartsWith('data:image/png;base64,', $json['qr']);
    }

    public function testATakenNumberCarriesNoQrPreview(): void
    {
        $db = db_connect();
        $db->table('member')->insert([
            'memberID' => 7, 'lastname' => 'DELA CRUZ', 'firstname' => 'JUAN',
            'middlename' => '', 'headID' => 7, 'sectorID' => '[]', 'Salary' => 0,
        ]);
        $db->table('qr_control')->insert(['control_no' => 12345, 'headID' => 7]);

        // A number already issued may have a card printed against it; rendering a
        // code for it here would suggest otherwise.
        $response = $this->withSession($this->encoderSession())
            ->get('records/qr-check?control_no=12345&head_id=0');

        $json = json_decode($response->getJSON(), true);

        $this->assertFalse($json['available']);
        $this->assertArrayNotHasKey('qr', $json);
    }

    public function testAnInvalidNumberCarriesNoQrPreview(): void
    {
        $response = $this->withSession($this->encoderSession())
            ->get('records/qr-check?control_no=abc&head_id=0');

        $json = json_decode($response->getJSON(), true);

        $this->assertArrayNotHasKey('qr', $json);
    }
}
