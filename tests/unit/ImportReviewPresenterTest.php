<?php

namespace Tests\Unit;

use App\Libraries\ImportReviewPresenter;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Unit coverage for the review report, focused on the "Ready to import" list.
 *
 * That list tells the operator their data is CORRECT and will be saved, so a family must
 * only appear on it if the import would really write it. A family that is blocked, already
 * on file (skipped), or being appended to an existing family must never show up as ready —
 * that would be a promise the import does not keep.
 *
 * @internal
 */
final class ImportReviewPresenterTest extends CIUnitTestCase
{
    public function testACleanFamilyIsReadyWithNoWarnings(): void
    {
        $ready = $this->ready(
            [$this->row(3, '6001', 'Head'), $this->row(4, '6001', 'Child')],
            [],
        );

        $this->assertCount(1, $ready);
        $this->assertSame('6001', $ready[0]['qr']);
        $this->assertSame('Juan Cruz', $ready[0]['head']);
        $this->assertSame(1, $ready[0]['members']);
        $this->assertSame(0, $ready[0]['warnings']);
        $this->assertSame(3, $ready[0]['sheetRow']);   // the Head's row
    }

    public function testAFamilyWithABlockingIssueIsNotReady(): void
    {
        $ready = $this->ready(
            [$this->row(3, '6001', 'Head')],
            [$this->error(3, '6001', 'SEX', 'blocking')],
        );

        $this->assertSame([], $ready);
    }

    public function testAWarningOnlyFamilyIsReadyAndCarriesItsWarningCount(): void
    {
        // BRGY / CONTACT / DUP-PERSON etc. import as typed — they must not hide the family.
        $ready = $this->ready(
            [$this->row(3, '6001', 'Head')],
            [$this->error(3, '6001', 'BRGY', 'warning'), $this->error(3, '6001', 'CONTACT', 'warning')],
        );

        $this->assertCount(1, $ready);
        $this->assertSame(2, $ready[0]['warnings']);
    }

    public function testAFamilyAlreadyOnFileIsNotReady(): void
    {
        // DUP-EXISTS = the write step SKIPS it. Listing it as "ready to import" would be a lie.
        $ready = $this->ready(
            [$this->row(3, '6001', 'Head')],
            [$this->error(3, '6001', 'DUP-EXISTS', 'warning')],
        );

        $this->assertSame([], $ready);
    }

    public function testAFamilyWhoseHeadIsAlreadyOnFileIsNotReady(): void
    {
        // DUP-DB on the HEAD = activeHeadExists skips the whole group, members and all.
        $ready = $this->ready(
            [$this->row(3, '7777', 'Head'), $this->row(4, '7777', 'Child')],
            [$this->error(3, '7777', 'DUP-DB', 'warning')],
        );

        $this->assertSame([], $ready);
    }

    public function testAFamilyWhoseMEMBERIsAlreadyOnFileIsStillReady(): void
    {
        // Only a duplicated HEAD skips the family. A duplicated member is inserted as a
        // second record, so the family still imports — with the warning attached.
        $ready = $this->ready(
            [$this->row(3, '7777', 'Head'), $this->row(4, '7777', 'Child')],
            [$this->error(4, '7777', 'DUP-DB', 'warning')],
        );

        $this->assertCount(1, $ready);
        $this->assertSame(1, $ready[0]['warnings']);
    }

    public function testAnAddToExistingFamilyGroupIsNotListedAsANewFamily(): void
    {
        // ADD-MEMBER groups have no Head and belong to a family already in the system. They
        // are reported under their own bucket, not as a new family being created.
        $ready = $this->ready(
            [$this->row(3, '6001', 'Child')],
            [$this->error(3, '6001', 'ADD-MEMBER', 'warning')],
        );

        $this->assertSame([], $ready);
    }

    public function testTheReadyCountMatchesTheList(): void
    {
        $review = (new ImportReviewPresenter())->build([
            'rows' => [
                $this->row(3, '6001', 'Head'),
                $this->row(4, '6002', 'Head'),
                $this->row(5, '6003', 'Head'),
            ],
            'errors' => [$this->error(5, '6003', 'SEX', 'blocking')],
        ]);

        $this->assertSame(2, $review['counts']['ready']);
        $this->assertCount(2, $review['ready']);
    }

    // -- families to fix (in-place Edit / Remove) ------------------------------

    public function testAFlaggedFamilyIsListedToFixWithItsIssueCounts(): void
    {
        $families = $this->families(
            [$this->row(3, '6001', 'Head'), $this->row(4, '6001', 'Child')],
            [$this->error(3, '6001', 'SEX', 'blocking'), $this->error(3, '6001', 'BRGY', 'warning')],
        );

        $this->assertCount(1, $families);
        $this->assertSame('6001', $families[0]['qr']);
        $this->assertSame('Juan Cruz', $families[0]['head']);
        $this->assertSame(1, $families[0]['members']);
        $this->assertSame(1, $families[0]['blocking']);
        $this->assertSame(1, $families[0]['warnings']);
        $this->assertSame(3, $families[0]['sheetRow']);
    }

    public function testAWarningOnlyFamilyIsListedToFix(): void
    {
        $families = $this->families(
            [$this->row(3, '6001', 'Head')],
            [$this->error(3, '6001', 'CONTACT', 'warning')],
        );

        $this->assertCount(1, $families);
        $this->assertSame(0, $families[0]['blocking']);
        $this->assertSame(1, $families[0]['warnings']);
    }

    public function testACleanFamilyIsNotListedToFix(): void
    {
        $families = $this->families(
            [$this->row(3, '6001', 'Head'), $this->row(4, '6001', 'Child')],
            [],
        );

        $this->assertSame([], $families);
    }

    public function testAHeadlessFlaggedFamilyStillListsToFix(): void
    {
        // HEAD-NONE blocks; the operator must be able to open it and designate a head, so it
        // must appear even though no row is marked Head (falls back to the first row).
        $families = $this->families(
            [$this->row(3, '6001', 'Child'), $this->row(4, '6001', 'Child')],
            [$this->error(3, '6001', 'HEAD-NONE', 'blocking')],
        );

        $this->assertCount(1, $families);
        $this->assertSame('6001', $families[0]['qr']);
        $this->assertSame(3, $families[0]['sheetRow']);
    }

    public function testFamiliesToFixListsEachDistinctIssueTypeBlockingFirst(): void
    {
        $families = $this->families(
            [$this->row(3, '6001', 'Head')],
            [
                $this->error(3, '6001', 'SEX', 'blocking'),
                $this->error(3, '6001', 'BRGY', 'warning'),
                $this->error(3, '6001', 'SEX', 'blocking'),   // duplicate code -> collapsed
            ],
        );

        $labels = array_column($families[0]['types'], 'label');

        $this->assertContains('Invalid sex', $labels);
        $this->assertContains('Barangay not recognised', $labels);
        $this->assertCount(2, $labels);                               // SEX de-duplicated
        $this->assertSame('blocking', $families[0]['types'][0]['severity']); // blocking first
        $this->assertFalse($families[0]['existing']);
    }

    public function testAFamilyAlreadyInTheSystemCarriesTheExistingFlag(): void
    {
        $families = $this->families(
            [$this->row(3, '6001', 'Head')],
            [$this->error(3, '6001', 'DUP-EXISTS', 'warning')],
        );

        $this->assertCount(1, $families);
        $this->assertTrue($families[0]['existing']);
    }

    public function testAFamilyWithANonNumericQrIsStillListedToFixWithItsQrIntact(): void
    {
        // "-1", "N/A", "5880.0" etc. reach the review as raw QR text. They must still be
        // editable — the Edit action carries the QR as data, not a numeric URL segment.
        foreach (['-1', 'N/A', '5880.0'] as $qr) {
            $families = $this->families(
                [$this->row(3, $qr, 'Head')],
                [$this->error(3, $qr, 'QR-FORMAT', 'blocking')],
            );

            $this->assertCount(1, $families);
            $this->assertSame($qr, $families[0]['qr']);
        }
    }

    public function testBlankQrRowsAreListedAsUnassignedWithTheirIssues(): void
    {
        $review = (new ImportReviewPresenter())->build([
            'rows' => [
                $this->row(12, '', 'Head'),       // blank QR -> unassigned
                $this->row(13, '6001', 'Head'),   // has a QR -> not unassigned
            ],
            'errors' => [$this->error(12, '', 'QR-01', 'blocking')],
        ]);

        $this->assertCount(1, $review['unassigned']);
        $this->assertSame(12, $review['unassigned'][0]['sheetRow']);
        $this->assertSame('Juan Cruz', $review['unassigned'][0]['person']);
        $this->assertContains('Missing QR Number', array_column($review['unassigned'][0]['types'], 'label'));
    }

    // -- inline-editable cells (hybrid fix-in-place) ---------------------------

    public function testAFieldLevelErrorBecomesAnEditableCell(): void
    {
        $families = $this->families(
            [$this->row(3, '6001', 'Head')],
            [$this->fieldError(3, '6001', 'BRGY', 'barangay', 'warning')],
        );

        $this->assertCount(1, $families);
        $cells = $families[0]['editableCells'];
        $this->assertCount(1, $cells);
        $this->assertSame('barangay', $cells[0]['field']);
        $this->assertSame('Barangay', $cells[0]['label']);
        $this->assertSame('Poblacion', $cells[0]['value']);   // the row's current value
        $this->assertSame('BRGY', $cells[0]['code']);
        $this->assertSame('warning', $cells[0]['severity']);
    }

    public function testAStructuralErrorHasNoEditableCell(): void
    {
        // HEAD-NONE carries no field, so it is not an inline cell — it is fixed via the modal.
        $families = $this->families(
            [$this->row(3, '6001', 'Child'), $this->row(4, '6001', 'Child')],
            [$this->error(3, '6001', 'HEAD-NONE', 'blocking')],   // field => null
        );

        $this->assertCount(1, $families);
        $this->assertSame([], $families[0]['editableCells']);
    }

    public function testABlockingErrorWinsOverAWarningOnTheSameCell(): void
    {
        $families = $this->families(
            [$this->row(3, '6001', 'Head')],
            [
                $this->fieldError(3, '6001', 'BDAY-RANGE', 'birthday', 'warning'),
                $this->fieldError(3, '6001', 'BDAY', 'birthday', 'blocking'),
            ],
        );

        $cells = $families[0]['editableCells'];
        $this->assertCount(1, $cells);                 // one input per cell
        $this->assertSame('birthday', $cells[0]['field']);
        $this->assertSame('blocking', $cells[0]['severity']);
    }

    public function testAnEditableCellCarriesTheExcelReferenceWhenColumnsAreKnown(): void
    {
        $review = (new ImportReviewPresenter())->build([
            'rows'    => [$this->row(42, '6001', 'Head')],
            'errors'  => [$this->fieldError(42, '6001', 'SEX', 'sex', 'blocking')],
            'columns' => ['sex' => 'H'],
        ]);

        $cell = $review['families'][0]['editableCells'][0];
        $this->assertSame('H42', $cell['cell']);
    }

    public function testABlankQrRowExposesTheMissingQrAsAnEditableCell(): void
    {
        // "give them a QR" happens inline: the QR-01 error points at the familyno cell.
        $review = (new ImportReviewPresenter())->build([
            'rows'    => [$this->row(12, '', 'Head')],
            'errors'  => [$this->fieldError(12, '', 'QR-01', 'familyno', 'blocking')],
            'columns' => ['familyno' => 'A'],
        ]);

        $this->assertCount(1, $review['unassigned']);
        $cells = $review['unassigned'][0]['editableCells'];
        $this->assertCount(1, $cells);
        $this->assertSame('familyno', $cells[0]['field']);
        $this->assertSame('A12', $cells[0]['cell']);
    }

    public function testFileNoticesSurfaceWholeFileErrors(): void
    {
        $review = (new ImportReviewPresenter())->build([
            'rows'   => [],
            'errors' => [
                ['sheetRow' => null, 'familyNo' => '', 'field' => null, 'code' => 'EMPTY', 'message' => 'No family rows were found.', 'severity' => 'blocking'],
            ],
        ]);

        $this->assertSame(['No family rows were found.'], $review['fileNotices']);
    }

    // -- helpers ---------------------------------------------------------------

    /**
     * @param list<array> $rows
     * @param list<array> $errors
     *
     * @return list<array>
     */
    private function families(array $rows, array $errors): array
    {
        return (new ImportReviewPresenter())->build(['rows' => $rows, 'errors' => $errors])['families'];
    }

    /**
     * @param list<array> $rows
     * @param list<array> $errors
     *
     * @return list<array>
     */
    private function ready(array $rows, array $errors): array
    {
        return (new ImportReviewPresenter())->build(['rows' => $rows, 'errors' => $errors])['ready'];
    }

    private function row(int $sheetRow, string $qr, string $relationship): array
    {
        return ['sheetRow' => $sheetRow, 'data' => [
            'familyno'     => $qr,
            'relationship' => $relationship,
            'firstname'    => 'Juan',
            'lastname'     => 'Cruz',
            'address'      => '123 Rizal St',
            'barangay'     => 'Poblacion',
        ]];
    }

    private function error(int $sheetRow, string $qr, string $code, string $severity): array
    {
        return [
            'sheetRow' => $sheetRow,
            'familyNo' => $qr,
            'field'    => null,
            'code'     => $code,
            'message'  => $code,
            'severity' => $severity,
        ];
    }

    /** A field-level error (carries a `field`), which the presenter turns into an editable cell. */
    private function fieldError(int $sheetRow, string $qr, string $code, string $field, string $severity): array
    {
        return [
            'sheetRow' => $sheetRow,
            'familyNo' => $qr,
            'field'    => $field,
            'code'     => $code,
            'message'  => $code,
            'severity' => $severity,
        ];
    }
}
