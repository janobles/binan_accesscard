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

    // -- helpers ---------------------------------------------------------------

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
}
