<?php

namespace Tests\Unit;

use App\Libraries\ImportReviewChangeLog;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Unit coverage for the Import Review change-log diff (App\Libraries\ImportReviewChangeLog).
 * Pure — no DB.
 *
 * @internal
 */
final class ImportReviewChangeLogTest extends CIUnitTestCase
{
    public function testEditedReportsFieldChangesAddsAndRemoves(): void
    {
        $old = [
            ['sheetRow' => 5, 'data' => ['familyno' => '42', 'relationship' => 'Head', 'firstname' => 'Juan', 'lastname' => 'Cruz', 'sex' => 'Male', 'job' => 'Driver']],
            ['sheetRow' => 6, 'data' => ['familyno' => '42', 'relationship' => 'Child', 'firstname' => 'Jose', 'lastname' => 'Cruz']],
        ];
        $new = [
            ['sheetRow' => 5, 'data' => ['familyno' => '42', 'relationship' => 'Head', 'firstname' => 'Juan', 'lastname' => 'Cruz', 'sex' => 'Male', 'job' => 'Teacher']],
            ['sheetRow' => 7, 'data' => ['familyno' => '42', 'relationship' => 'Spouse', 'firstname' => 'Maria', 'lastname' => 'Cruz']],
        ];

        $entry = ImportReviewChangeLog::edited($old, $new);

        $this->assertNotNull($entry);
        $this->assertSame('Edited', $entry['action']);
        $this->assertSame('42', $entry['qr']);
        $this->assertSame('Juan Cruz', $entry['head']);

        $joined = implode("\n", $entry['lines']);
        $this->assertStringContainsString('Juan Cruz · Job: "Driver" → "Teacher"', $joined);
        $this->assertStringContainsString('Added Maria Cruz', $joined);
        $this->assertStringContainsString('Removed Jose Cruz', $joined);
    }

    public function testEditedReturnsNullWhenNothingChanged(): void
    {
        $rows = [['sheetRow' => 5, 'data' => ['familyno' => '42', 'relationship' => 'Head', 'firstname' => 'Juan', 'lastname' => 'Cruz']]];

        $this->assertNull(ImportReviewChangeLog::edited($rows, $rows));
    }

    public function testEditedReportsQrChangeAndBlankValues(): void
    {
        $old = [['sheetRow' => 5, 'data' => ['familyno' => '42', 'relationship' => 'Head', 'firstname' => 'Juan', 'lastname' => 'Cruz', 'contactnumber' => '']]];
        $new = [['sheetRow' => 5, 'data' => ['familyno' => '99', 'relationship' => 'Head', 'firstname' => 'Juan', 'lastname' => 'Cruz', 'contactnumber' => '09171234567']]];

        $entry  = ImportReviewChangeLog::edited($old, $new);
        $joined = implode("\n", $entry['lines']);

        $this->assertStringContainsString('QR Number: "42" → "99"', $joined);
        $this->assertStringContainsString('Contact number: (blank) → "09171234567"', $joined);
    }

    public function testRemovedEntry(): void
    {
        $old = [
            ['sheetRow' => 5, 'data' => ['familyno' => '42', 'relationship' => 'Head', 'firstname' => 'Juan', 'lastname' => 'Cruz']],
            ['sheetRow' => 6, 'data' => ['familyno' => '42', 'relationship' => 'Child', 'firstname' => 'Jose', 'lastname' => 'Cruz']],
        ];

        $entry = ImportReviewChangeLog::removed($old);

        $this->assertSame('Removed', $entry['action']);
        $this->assertSame('42', $entry['qr']);
        $this->assertSame('Juan Cruz', $entry['head']);
        $this->assertStringContainsString('2 rows', $entry['lines'][0]);
    }
}
