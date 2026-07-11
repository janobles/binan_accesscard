<?php

namespace Tests\Unit;

use App\Libraries\FamilyExcelImporter;
use CodeIgniter\Test\CIUnitTestCase;
use ReflectionClass;

/**
 * Unit coverage for the hardened QR validation and the family-coherence /
 * fingerprint checks. The lookup caches (sector/service/income) are primed to empty
 * arrays via reflection so validateAndBuild runs without a database.
 *
 * @internal
 */
final class FamilyExcelImporterTest extends CIUnitTestCase
{
    // -- validateQr: the acceptance table (spec section 7) ---------------------

    public function testValidateQrRejectsMalformedValues(): void
    {
        $importer = new FamilyExcelImporter();

        $rejects = [
            ''               => 'QR-01',
            'ABC123'         => 'QR-FORMAT',
            'QR-6001'        => 'QR-FORMAT',
            'NULL'           => 'QR-FORMAT',
            'TBD'            => 'QR-FORMAT',
            'N/A'            => 'QR-FORMAT',
            '-1'             => 'QR-FORMAT',
            '-57'            => 'QR-FORMAT',
            '0'              => 'QR-05',
            '5880.5'         => 'QR-FORMAT',
            '5880.0'         => 'QR-FORMAT', // integral-float TEXT keeps the dot -> rejected
            '6,001'          => 'QR-FORMAT',
            '1.23457E+11'    => 'QR-FORMAT',
            '#REF!'          => 'QR-08',
            '=A4'            => 'QR-12',
            '3000000000'     => 'QR-07',     // 10 digits, over the 2147483647 ceiling
            '999999999999'   => 'QR-FORMAT', // 12 digits, over the 10-digit format cap
        ];

        foreach ($rejects as $raw => $expectedCode) {
            $result = $importer->validateQr($raw);
            $this->assertFalse($result['ok'], "expected '{$raw}' to be rejected");
            $this->assertSame($expectedCode, $result['code'], "wrong code for '{$raw}'");
        }
    }

    public function testValidateQrNormalisesAndAcceptsGoodValues(): void
    {
        $importer = new FamilyExcelImporter();

        $accepts = [
            '  6001  '        => 6001,   // padded
            '6001'            => 6001,   // text-formatted number
            "6001\u{00A0}"    => 6001,   // trailing non-breaking space
            "\u{200B}6001"    => 6001,   // leading zero-width space
            '006001'          => 6001,   // leading zeros -> accepted (logged)
            '1'               => 1,
            '2147483647'      => 2147483647, // exactly the ceiling
        ];

        foreach ($accepts as $raw => $expected) {
            $result = $importer->validateQr($raw);
            $this->assertTrue($result['ok'], "expected '{$raw}' to be accepted");
            $this->assertSame($expected, $result['qr'], "wrong value for '{$raw}'");
        }
    }

    // -- family coherence + fingerprint ----------------------------------------

    public function testCleanSingleHeadFamilyBuildsWithNoBlockingErrors(): void
    {
        $result = $this->importer()->validateAndBuild([
            $this->headRow(3, '6001'),
            $this->memberRow(4, '6001'),
        ]);

        $this->assertSame(1, $result['counts']['families']);
        $this->assertSame(1, $result['counts']['members']);
        $this->assertSame(0, $result['counts']['blocking']);
    }

    public function testFingerprintFlagsDifferentBarangayInOneFamily(): void
    {
        $result = $this->importer()->validateAndBuild([
            $this->headRow(3, '6001', ['barangay' => 'Poblacion']),
            $this->memberRow(4, '6001', ['barangay' => 'Malaban']),
        ]);

        $this->assertContains('FP-ADDR', $this->codes($result));
    }

    public function testFingerprintIgnoresMixedSurnames(): void
    {
        // Two different surnames but the same (blank) member address/barangay: a real
        // household. Surname is deliberately NOT part of the fingerprint.
        $result = $this->importer()->validateAndBuild([
            $this->headRow(3, '6001', ['lastname' => 'Dela Cruz']),
            $this->memberRow(4, '6001', ['lastname' => 'Reyes']),
        ]);

        $this->assertNotContains('FP-ADDR', $this->codes($result));
    }

    public function testHeadNoneAndHeadMultiAreFlagged(): void
    {
        $none = $this->importer()->validateAndBuild([
            $this->memberRow(3, '6001'),
            $this->memberRow(4, '6001'),
        ]);
        $this->assertContains('HEAD-NONE', $this->codes($none));

        $multi = $this->importer()->validateAndBuild([
            $this->headRow(3, '6002'),
            $this->headRow(4, '6002'),
        ]);
        $this->assertContains('HEAD-MULTI', $this->codes($multi));
    }

    public function testEveryRowIsValidatedEvenWhenTheFamilyHasNoHead(): void
    {
        // The spec's secondary bug: field validation was skipped for rows whose family
        // already failed a family-level check. Here a headless family also has a member
        // missing a first name — BOTH errors must surface.
        $result = $this->importer()->validateAndBuild([
            $this->memberRow(3, '6001', ['firstname' => '']),
            $this->memberRow(4, '6001'),
        ]);

        $codes = $this->codes($result);
        $this->assertContains('HEAD-NONE', $codes);
        $this->assertContains('REQUIRED', $codes);
    }

    public function testNonContiguousFamilyIsAWarningNotBlocking(): void
    {
        $result = $this->importer()->validateAndBuild([
            $this->headRow(3, '6001'),
            $this->memberRow(5, '6001'), // gap at row 4
        ]);

        $contig = array_values(array_filter(
            $result['errors'],
            static fn (array $e): bool => $e['code'] === 'QR-CONTIG'
        ));

        $this->assertCount(1, $contig);
        $this->assertSame('warning', $contig[0]['severity']);
    }

    // -- barangay / contact / suffix / duplicate-person (all warnings) ---------

    public function testBarangayToleratesSpellingButFlagsNonBarangays(): void
    {
        // "Biñan" and "Sto. Tomas" are legitimate spellings of official barangays.
        $ok = $this->importer()->validateAndBuild([
            $this->headRow(3, '6001', ['barangay' => 'Biñan']),
            $this->headRow(4, '6002', ['barangay' => 'Sto. Tomas']),
        ]);
        $this->assertNotContains('BRGY', $this->codes($ok));

        // "Santa Rosa" is a different city — flagged (warning).
        $bad = $this->importer()->validateAndBuild([
            $this->headRow(3, '6003', ['barangay' => 'Santa Rosa']),
        ]);
        $brgy = array_values(array_filter($bad['errors'], static fn (array $e): bool => $e['code'] === 'BRGY'));
        $this->assertCount(1, $brgy);
        $this->assertSame('warning', $brgy[0]['severity']);
    }

    public function testContactNumberMustBe09Plus11Digits(): void
    {
        $good = $this->importer()->validateAndBuild([
            $this->headRow(3, '6001', ['contactnumber' => '0917-123-4567']), // punctuation ok
        ]);
        $this->assertNotContains('CONTACT', $this->codes($good));

        foreach (['0917', '+639171234567', '09171234567890', '99171234567'] as $bad) {
            $result = $this->importer()->validateAndBuild([
                $this->headRow(3, '6001', ['contactnumber' => $bad]),
            ]);
            $this->assertContains('CONTACT', $this->codes($result), "expected '{$bad}' to warn");
        }
    }

    public function testSuffixNormalisesDotSilently(): void
    {
        // "Jr." is just a trailing dot — accepted silently, stored as "Jr".
        $ok = $this->importer()->validateAndBuild([
            $this->headRow(3, '6001', ['suffix' => 'Jr.']),
        ]);
        $this->assertNotContains('SUFFIX', $this->codes($ok));
        $this->assertSame('Jr', $ok['families'][0]['headPayload']['suffix']);
    }

    public function testSuffixMapsVariantsToDropdownValueWithWarning(): void
    {
        // "the 3rd" and "Junior" are real changes — coerced to the dropdown value + warned.
        $map = ['the 3rd' => 'III', 'Junior' => 'Jr', '2nd' => 'II'];

        foreach ($map as $typed => $expected) {
            $result = $this->importer()->validateAndBuild([
                $this->headRow(3, '6001', ['suffix' => $typed]),
            ]);
            $this->assertContains('SUFFIX', $this->codes($result), "expected '{$typed}' to warn");
            $this->assertSame($expected, $result['families'][0]['headPayload']['suffix'], "'{$typed}' should map to {$expected}");
        }
    }

    public function testUnmappableSuffixIsLeftBlank(): void
    {
        // Genuine junk maps to nothing — left blank (enum-safe) with a warning.
        $result = $this->importer()->validateAndBuild([
            $this->headRow(3, '6001', ['suffix' => 'Bogus']),
        ]);
        $this->assertContains('SUFFIX', $this->codes($result));
        $this->assertNull($result['families'][0]['headPayload']['suffix']);
    }

    public function testBirthdayRangeWarnsButStillImports(): void
    {
        // In the future, or an age over 150 → flagged (still imports).
        foreach (['01-01-2050', '05-14-1870'] as $bad) {
            $result = $this->importer()->validateAndBuild([
                $this->headRow(3, '6001', ['birthday' => $bad]),
            ]);
            $codes = $this->codes($result);
            $this->assertContains('BDAY-RANGE', $codes, "expected '{$bad}' to warn");
            $this->assertNotContains('BDAY', $codes); // valid format, just implausible
            $this->assertSame(1, $result['counts']['families']); // still built/imported
        }

        // Plausible ages — including a centenarian (~100) — raise nothing.
        foreach (['05-14-1980', '05-14-1926'] as $good) {
            $ok = $this->importer()->validateAndBuild([$this->headRow(3, '6002', ['birthday' => $good])]);
            $this->assertNotContains('BDAY-RANGE', $this->codes($ok), "expected '{$good}' to pass");
        }
    }

    public function testOverLongValueIsBlocked(): void
    {
        $result = $this->importer()->validateAndBuild([
            $this->headRow(3, '6001', ['firstname' => str_repeat('A', 150)]),
        ]);
        $length = array_values(array_filter($result['errors'], static fn (array $e): bool => $e['code'] === 'LENGTH'));
        $this->assertCount(1, $length);
        $this->assertSame('blocking', $length[0]['severity']);
        $this->assertSame('firstname', $length[0]['field']);
    }

    public function testDuplicatePersonWarnsOnlyAtTheSameAddress(): void
    {
        // Same person twice in one family (shared head address) → warning.
        $dup = $this->importer()->validateAndBuild([
            $this->headRow(3, '6001'),
            $this->memberRow(4, '6001', ['firstname' => 'Jose', 'lastname' => 'Cruz', 'birthday' => '01-10-2010']),
            $this->memberRow(5, '6001', ['firstname' => 'Jose', 'lastname' => 'Cruz', 'birthday' => '01-10-2010']),
        ]);
        $this->assertContains('DUP-PERSON', $this->codes($dup));

        // Same-named heads in two families at DIFFERENT addresses → not flagged.
        $notDup = $this->importer()->validateAndBuild([
            $this->headRow(3, '6002', ['firstname' => 'Juan', 'lastname' => 'Reyes', 'barangay' => 'Poblacion']),
            $this->headRow(4, '6003', ['firstname' => 'Juan', 'lastname' => 'Reyes', 'barangay' => 'Malaban']),
        ]);
        $this->assertNotContains('DUP-PERSON', $this->codes($notDup));
    }

    // -- add-member-to-existing-family (append) --------------------------------

    public function testHeadlessGroupForExistingQrBecomesAddMember(): void
    {
        $result = $this->importer()->validateAndBuild(
            [$this->memberRow(3, '6001', ['firstname' => 'Maria', 'lastname' => 'Dela Cruz'])],
            [6001 => 'Juan Dela Cruz'], // QR 6001 already on file
        );

        $codes = $this->codes($result);
        $this->assertContains('ADD-MEMBER', $codes);
        $this->assertNotContains('HEAD-NONE', $codes); // the head is in the DB, not missing
        $this->assertCount(1, $result['appends']);
        $this->assertSame('', $result['appends'][0]['decision']);       // undecided
        $this->assertSame(1, $result['counts']['appendsPending']);
    }

    public function testHeadlessGroupForNewQrIsStillHeadNone(): void
    {
        $result = $this->importer()->validateAndBuild(
            [$this->memberRow(3, '6002', ['firstname' => 'Maria'])],
            [], // QR 6002 does not exist
        );

        $this->assertContains('HEAD-NONE', $this->codes($result));
        $this->assertSame([], $result['appends']);
    }

    public function testAddMemberDecisionCountsAppendVsPending(): void
    {
        $result = $this->importer()->validateAndBuild(
            [$this->memberRow(3, '6001', ['firstname' => 'Maria', '_decision' => 'append'])],
            [6001 => 'Juan Dela Cruz'],
        );

        $this->assertSame('append', $result['appends'][0]['decision']);
        $this->assertSame(1, $result['counts']['appendsToImport']);
        $this->assertSame(0, $result['counts']['appendsPending']);
    }

    public function testExistingQrWithAHeadIsADuplicateFamily(): void
    {
        $result = $this->importer()->validateAndBuild(
            [$this->headRow(3, '6001')],
            [6001 => 'Juan Dela Cruz'],
        );

        $this->assertContains('DUP-EXISTS', $this->codes($result));
        $this->assertSame(1, $result['counts']['existing']);
    }

    // -- helpers ---------------------------------------------------------------

    /** Importer with lookup caches primed empty so validateAndBuild needs no DB. */
    private function importer(): FamilyExcelImporter
    {
        $importer = new FamilyExcelImporter();
        $reflection = new ReflectionClass($importer);

        foreach (['sectorByCode', 'serviceByCode', 'incomeByLabel'] as $property) {
            $prop = $reflection->getProperty($property);
            $prop->setAccessible(true);
            $prop->setValue($importer, []);
        }

        return $importer;
    }

    /** @return list<string> */
    private function codes(array $result): array
    {
        return array_map(static fn (array $e): string => $e['code'], $result['errors']);
    }

    /** @param array<string,string> $overrides */
    private function headRow(int $sheetRow, string $qr, array $overrides = []): array
    {
        return ['sheetRow' => $sheetRow, 'data' => array_merge([
            'familyno' => $qr, 'relationship' => 'Head', 'firstname' => 'Juan', 'lastname' => 'Dela Cruz',
            'middlename' => '', 'suffix' => '', 'birthday' => '05-14-1980', 'sex' => 'Male',
            'civilstatus' => 'M', 'contactnumber' => '', 'religion' => '', 'education' => 'CG',
            'job' => 'Driver', 'monthlyincome' => '5000', 'address' => '123 Rizal St',
            'barangay' => 'Poblacion', 'sector' => '', 'services' => '',
        ], $overrides)];
    }

    /** @param array<string,string> $overrides */
    private function memberRow(int $sheetRow, string $qr, array $overrides = []): array
    {
        return ['sheetRow' => $sheetRow, 'data' => array_merge([
            'familyno' => $qr, 'relationship' => 'Child', 'firstname' => 'Jose', 'lastname' => 'Dela Cruz',
            'middlename' => '', 'suffix' => '', 'birthday' => '', 'sex' => '',
            'civilstatus' => '', 'contactnumber' => '', 'religion' => '', 'education' => '',
            'job' => '', 'monthlyincome' => '', 'address' => '', 'barangay' => '',
            'sector' => '', 'services' => '',
        ], $overrides)];
    }
}
