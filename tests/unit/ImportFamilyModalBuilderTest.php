<?php

namespace Tests\Unit;

use App\Libraries\ImportFamilyModalBuilder;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Unit coverage for the reverse mapping that turns the family modal's POST back into raw
 * staged import rows (App\Libraries\ImportFamilyModalBuilder::toStagedRows).
 *
 * These cases use no sectors/services, so they exercise the field/relationship/sheet-row
 * logic without touching the database (shortcode<->id mapping is DB-backed and covered by
 * the end-to-end review flow).
 *
 * @internal
 */
final class ImportFamilyModalBuilderTest extends CIUnitTestCase
{
    public function testHeadRowIsRebuiltFromThePostWithHeadRelationship(): void
    {
        $rows = (new ImportFamilyModalBuilder())->toStagedRows($this->headPost('42'), $this->bundle('42', [5]), '42');

        $this->assertCount(1, $rows);
        $head = $rows[0]['data'];

        $this->assertSame('42', $head['familyno']);
        $this->assertSame('Head', $head['relationship']);
        $this->assertSame('Cruz', $head['lastname']);
        $this->assertSame('Juan', $head['firstname']);
        // Full civil-status text passes through unchanged; income is the numeric option value.
        $this->assertSame('Single', $head['civilstatus']);
        $this->assertSame('8000', $head['monthlyincome']);
        // Address and barangay stay in separate cells.
        $this->assertSame('123 Rizal St', $head['address']);
        $this->assertSame('Poblacion', $head['barangay']);
        // No sectors/services selected -> empty cells (no DB lookup).
        $this->assertSame('', $head['sector']);
        $this->assertSame('', $head['services']);
        // Reused the replaced group's sheet number.
        $this->assertSame(5, $rows[0]['sheetRow']);
    }

    public function testMembersAreRebuiltAndEmptyRowsAreDropped(): void
    {
        $post = $this->headPost('42') + [
            'members' => [
                ['firstname' => 'Maria', 'lastname' => 'Cruz', 'relationship' => 'Spouse'],
                ['firstname' => '', 'lastname' => ''],   // empty template row -> dropped
                ['firstname' => 'Jose', 'lastname' => 'Cruz', 'relationship' => 'Child'],
            ],
        ];

        $rows = (new ImportFamilyModalBuilder())->toStagedRows($post, $this->bundle('42', [5, 6, 7]), '42');

        $this->assertCount(3, $rows);   // head + two real members (the blank one dropped)
        $this->assertSame('Head', $rows[0]['data']['relationship']);
        $this->assertSame('Spouse', $rows[1]['data']['relationship']);
        $this->assertSame('Maria', $rows[1]['data']['firstname']);
        $this->assertSame('Child', $rows[2]['data']['relationship']);
    }

    public function testSheetRowsReuseTheReplacedGroupThenAllocatePastTheMax(): void
    {
        // Group '42' had one row (5); the bundle's highest row overall is 9. Head + 2 members
        // -> reuse 5, then allocate 10 and 11.
        $post = $this->headPost('42') + [
            'members' => [
                ['firstname' => 'Maria', 'lastname' => 'Cruz', 'relationship' => 'Spouse'],
                ['firstname' => 'Jose', 'lastname' => 'Cruz', 'relationship' => 'Child'],
            ],
        ];

        $bundle = [
            'rows' => [
                ['sheetRow' => 5, 'data' => ['familyno' => '42', 'relationship' => 'Head']],
                ['sheetRow' => 9, 'data' => ['familyno' => '77', 'relationship' => 'Head']],
            ],
        ];

        $rows = (new ImportFamilyModalBuilder())->toStagedRows($post, $bundle, '42');

        $this->assertSame([5, 10, 11], array_column($rows, 'sheetRow'));
    }

    public function testAChangedQrIsAppliedToEveryRebuiltRow(): void
    {
        $post = $this->headPost('99') + [   // operator retyped the QR
            'members' => [['firstname' => 'Maria', 'lastname' => 'Cruz', 'relationship' => 'Spouse']],
        ];

        $rows = (new ImportFamilyModalBuilder())->toStagedRows($post, $this->bundle('42', [5, 6]), '42');

        $this->assertSame('99', $rows[0]['data']['familyno']);
        $this->assertSame('99', $rows[1]['data']['familyno']);
    }

    public function testIssuesReturnsThisFamilysErrorsBlockingFirstWithContext(): void
    {
        $bundle = [
            'rows' => [
                ['sheetRow' => 5, 'data' => ['familyno' => '42', 'firstname' => 'Juan', 'lastname' => 'Cruz']],
                ['sheetRow' => 6, 'data' => ['familyno' => '77', 'firstname' => 'Ana', 'lastname' => 'Reyes']],
            ],
            'errors' => [
                ['sheetRow' => 5, 'familyNo' => '42', 'field' => 'contactnumber', 'message' => 'bad contact', 'severity' => 'warning'],
                ['sheetRow' => 5, 'familyNo' => '42', 'field' => 'sex', 'message' => 'bad sex', 'severity' => 'blocking'],
                ['sheetRow' => 6, 'familyNo' => '77', 'field' => 'sex', 'message' => 'other family', 'severity' => 'blocking'],
            ],
        ];

        $issues = (new ImportFamilyModalBuilder())->issues($bundle, '42');

        $this->assertCount(2, $issues);                       // only family 42's errors
        $this->assertSame('blocking', $issues[0]['severity']); // blocking sorted first
        $this->assertSame('Sex', $issues[0]['column']);        // friendly column label
        $this->assertSame('Juan Cruz', $issues[0]['person']);  // person context
        $this->assertSame('warning', $issues[1]['severity']);
        $this->assertSame('Contact Number', $issues[1]['column']);
    }

    public function testIssuesIsEmptyForACleanFamily(): void
    {
        $bundle = ['rows' => [['sheetRow' => 5, 'data' => ['familyno' => '42']]], 'errors' => []];

        $this->assertSame([], (new ImportFamilyModalBuilder())->issues($bundle, '42'));
    }

    public function testFieldIssuesMapErrorsToTheExactModalInputNames(): void
    {
        $bundle = [
            'rows' => [
                ['sheetRow' => 5, 'data' => ['familyno' => '42', 'relationship' => 'Head', 'firstname' => 'Juan', 'lastname' => 'Cruz']],
                ['sheetRow' => 6, 'data' => ['familyno' => '42', 'relationship' => 'Child', 'firstname' => 'Jose', 'lastname' => 'Cruz']],
            ],
            'errors' => [
                // Head field errors: sex -> head_sex, monthlyincome -> head_salary, QR -> qr_control_no.
                ['sheetRow' => 5, 'familyNo' => '42', 'field' => 'sex', 'message' => 'bad sex', 'severity' => 'blocking'],
                ['sheetRow' => 5, 'familyNo' => '42', 'field' => 'monthlyincome', 'message' => 'bad income', 'severity' => 'blocking'],
                ['sheetRow' => 5, 'familyNo' => '42', 'field' => 'familyno', 'message' => 'bad qr', 'severity' => 'blocking'],
                // Member (index 0) contact -> members[0][contactnumber].
                ['sheetRow' => 6, 'familyNo' => '42', 'field' => 'contactnumber', 'message' => 'bad contact', 'severity' => 'warning'],
                // No single input for these -> not mapped (left to the summary panel).
                ['sheetRow' => 5, 'familyNo' => '42', 'field' => 'services', 'message' => 'unknown service', 'severity' => 'blocking'],
                ['sheetRow' => 5, 'familyNo' => '42', 'field' => null, 'message' => 'headless', 'severity' => 'blocking'],
            ],
        ];

        $names = array_column((new ImportFamilyModalBuilder())->fieldIssues($bundle, '42'), 'name');

        $this->assertContains('head_sex', $names);
        $this->assertContains('head_salary', $names);
        $this->assertContains('qr_control_no', $names);
        $this->assertContains('members[0][contactnumber]', $names);
        $this->assertNotContains('head_services', $names);
        $this->assertCount(4, $names);   // services + null-field error are not mapped
    }

    public function testToStagedRowsHandlesANonNumericFamilyNo(): void
    {
        // A "-1" / "N/A" QR group must round-trip: found by its raw string, replaced, and the
        // corrected QR applied. (The routing fix is what lets the modal open for these at all.)
        $rows = (new ImportFamilyModalBuilder())->toStagedRows($this->headPost('501'), $this->bundle('-1', [5]), '-1');

        $this->assertCount(1, $rows);
        $this->assertSame('501', $rows[0]['data']['familyno']);   // corrected QR applied
        $this->assertSame(5, $rows[0]['sheetRow']);               // reused the -1 group's row
    }

    public function testToStagedRowsForRowReusesTheRowAndAppliesTheTypedQr(): void
    {
        $bundle = ['rows' => [
            ['sheetRow' => 12, 'data' => ['familyno' => '', 'relationship' => '']],
            ['sheetRow' => 13, 'data' => ['familyno' => '6001', 'relationship' => 'Head']],
        ]];

        // Operator opened the blank-QR row 12 and typed QR 700.
        $rows = (new ImportFamilyModalBuilder())->toStagedRowsForRow($this->headPost('700'), $bundle, 12);

        $this->assertCount(1, $rows);
        $this->assertSame(12, $rows[0]['sheetRow']);              // reused the row's own number
        $this->assertSame('700', $rows[0]['data']['familyno']);  // typed QR applied
        $this->assertSame('Head', $rows[0]['data']['relationship']);
    }

    public function testIssuesForRowReturnsOnlyThatRowsErrors(): void
    {
        $bundle = [
            'rows' => [['sheetRow' => 12, 'data' => ['familyno' => '', 'firstname' => 'Juan', 'lastname' => 'Cruz']]],
            'errors' => [
                ['sheetRow' => 12, 'familyNo' => '', 'field' => 'familyno', 'message' => 'QR required', 'severity' => 'blocking'],
                ['sheetRow' => 13, 'familyNo' => '', 'field' => 'sex', 'message' => 'other row', 'severity' => 'blocking'],
            ],
        ];

        $issues = (new ImportFamilyModalBuilder())->issuesForRow($bundle, 12);

        $this->assertCount(1, $issues);
        $this->assertSame('QR Number', $issues[0]['column']);   // FIELD_LABELS['familyno']
        $this->assertSame('Juan Cruz', $issues[0]['person']);
    }

    // -- head / relationship modal alignment ------------------------------------
    //
    // The Edit modal now lines up with the importer's head validators (see the diagnosis in
    // C:\Users\Mel\.claude\plans\can-you-explain-to-purrfect-valley.md): a demoted extra
    // head no longer round-trips as a second Head, and a head-less group opens on the same
    // person the review report names.

    public function testDemotedExtraHeadNoLongerRoundTripsIntoMultiHead(): void
    {
        // The modal now presents the demoted extra head with a BLANK relationship
        // (see testSplitHeadAndMembersBlanksTheDemotedExtraHead), so a Save leaves exactly one
        // Head. The blank relationship then raises the normal "Relationship is required"
        // prompt, guiding the operator to pick a real one — no silent HEAD-MULTI loop.
        $post = $this->headPost('42') + [
            'members' => [
                ['firstname' => 'Pedro', 'lastname' => 'Cruz', 'relationship' => ''],
            ],
        ];

        $rows = (new ImportFamilyModalBuilder())->toStagedRows($post, $this->bundle('42', [5, 6]), '42');

        $heads = array_filter($rows, static fn (array $row): bool =>
            strcasecmp((string) ($row['data']['relationship'] ?? ''), 'Head') === 0);

        $this->assertCount(1, $heads);
        $this->assertSame('', $rows[1]['data']['relationship']);   // demoted head awaits a re-pick
    }

    public function testSplitHeadAndMembersBlanksTheDemotedExtraHead(): void
    {
        // Two Head rows: the first stays Head, the extra head is demoted to a member with its
        // "Head" relationship CLEARED so it can't round-trip into a second head.
        $rows = [
            ['sheetRow' => 5, 'data' => ['familyno' => '42', 'relationship' => 'Head', 'firstname' => 'Juan']],
            ['sheetRow' => 6, 'data' => ['familyno' => '42', 'relationship' => 'Head', 'firstname' => 'Pedro']],
        ];

        [$head, $members] = $this->invokeSplit($rows);

        $this->assertSame('Juan', $head['data']['firstname']);         // first head kept
        $this->assertCount(1, $members);
        $this->assertSame('', $members[0]['data']['relationship']);    // extra head demoted + cleared
    }

    public function testSplitHeadAndMembersPromotesTheAddressCarrierWhenNoExplicitHead(): void
    {
        // No Head row: the modal promotes the row carrying the address (Maria) — the same
        // person the review report names as the likely head — not blindly row 0 (Jose).
        $rows = [
            ['sheetRow' => 5, 'data' => ['familyno' => '42', 'relationship' => 'Child', 'firstname' => 'Jose', 'address' => '']],
            ['sheetRow' => 6, 'data' => ['familyno' => '42', 'relationship' => 'Spouse', 'firstname' => 'Maria', 'address' => '123 Rizal St']],
        ];

        [$head] = $this->invokeSplit($rows);

        $this->assertSame('Maria', $head['data']['firstname']);
    }

    // -- helpers ---------------------------------------------------------------

    /** Invokes the private splitHeadAndMembers (no DB) to characterize the head split. */
    private function invokeSplit(array $rows): array
    {
        $method = new \ReflectionMethod(ImportFamilyModalBuilder::class, 'splitHeadAndMembers');
        $method->setAccessible(true);

        return $method->invoke(new ImportFamilyModalBuilder(), $rows);
    }

    /** A minimal head-only POST (no sectors/services, so no DB is touched). */
    private function headPost(string $qr): array
    {
        return [
            'qr_control_no'      => $qr,
            'head_lastname'      => 'Cruz',
            'head_firstname'     => 'Juan',
            'head_middlename'    => '',
            'head_suffix'        => '',
            'head_birthday'      => '1990-07-20',
            'head_sex'           => 'Male',
            'head_civilstatus'   => 'Single',
            'head_contactnumber' => '09171234567',
            'head_religion'      => 'Roman Catholic',
            'head_education'     => 'College Graduate',
            'head_job'           => 'Driver',
            'head_salary'        => '8000',
            'head_address'       => '123 Rizal St',
            'head_barangay'      => 'Poblacion',
            'sector_ids'         => [],
            'service_ids'        => [],
        ];
    }

    /**
     * @param list<int> $sheetRows
     */
    private function bundle(string $familyNo, array $sheetRows): array
    {
        $rows = [];

        foreach ($sheetRows as $sheetRow) {
            $rows[] = ['sheetRow' => $sheetRow, 'data' => ['familyno' => $familyNo, 'relationship' => 'Head']];
        }

        return ['rows' => $rows];
    }
}
