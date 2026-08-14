<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Database\DumpSchema;
use Tests\Support\Database\ReferentialFixture;

/**
 * ReportsController::pdf() used to call SubsidyStatsModel::coverage() and
 * byBarangay() fresh, one line after batchSnapshot() had already computed and
 * cached both. batchFingerprint() keys the cache on the batch row alone, not
 * on subsidy_distribution, so a scan logged after the snapshot is primed does
 * not invalidate it: the cached snapshot still answers the pre-scan figures
 * while a fresh coverage() call would answer the post-scan ones. This pins
 * that the PDF prints the snapshot's number, not the fresher, disagreeing one
 * a second query would have produced.
 *
 * Ghostscript's txtwrite device extracts the PDF's text layer; the test skips
 * itself if gs is not on the machine running it, the same posture
 * OnlyFullGroupByTest takes for a MariaDB-only mode.
 */
final class ReportsPdfSnapshotConsistencyTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private const BATCH_ID = 41;
    private const ADMIN_ID = 91;

    protected function setUp(): void
    {
        parent::setUp();

        if (shell_exec('command -v gs') === null) {
            $this->markTestSkipped('ghostscript (gs) is not installed; cannot read the PDF text layer.');
        }

        cache()->clean();

        $db = db_connect();
        DumpSchema::create($db);

        $db->table('users')->insert([
            'userID' => self::ADMIN_ID, 'username' => 'admin41',
            'account_level' => 'admin', 'isactive' => 'Enable', 'password' => 'x',
        ]);

        $db->table('distribution_batch')->insert([
            'batch_id' => self::BATCH_ID, 'name' => 'Snapshot consistency batch',
            'subsidy_type_id' => 1, 'eligible_count' => 2,
        ]);

        ReferentialFixture::claimParents($db, [1, 2]);
        $db->table('batch_eligibility')->insertBatch([
            ['batch_id' => self::BATCH_ID, 'headID' => 1],
            ['batch_id' => self::BATCH_ID, 'headID' => 2],
        ]);

        // One family served so far: coverage()/batchSnapshot() both read 1 of
        // 2, 50%, at this point.
        $db->table('subsidy_distribution')->insert([
            'control_no' => 101, 'memberID' => 1, 'subsidy_type_id' => 1,
            'batch_id' => self::BATCH_ID, 'claim_date' => '2026-08-11',
        ]);

        // Primes the cache at 1 of 2 (50%). The fingerprint only reads the
        // distribution_batch row, so the second scan below does not evict
        // this.
        model(\App\Models\Scanner\SubsidyStatsModel::class)->batchSnapshot(self::BATCH_ID, true);

        // A second family served after the snapshot was cached: a fresh
        // coverage()/byBarangay() call from here on would answer 2 of 2,
        // 100%, which is exactly the number the PDF must not print.
        $db->table('subsidy_distribution')->insert([
            'control_no' => 102, 'memberID' => 2, 'subsidy_type_id' => 1,
            'batch_id' => self::BATCH_ID, 'claim_date' => '2026-08-11',
        ]);
    }

    protected function tearDown(): void
    {
        DumpSchema::drop(db_connect());
        cache()->clean();

        parent::tearDown();
    }

    public function testPdfPrintsTheSnapshotsCoverageNotAFreshQuery(): void
    {
        $result = $this->withSession(['is_logged_in' => true, 'role' => 'Admin', 'user_id' => self::ADMIN_ID, 'username' => 'admin41'])
            ->get('distribution/reports/pdf?batch=' . self::BATCH_ID);

        $bytes = $result->response()->getBody();
        $this->assertStringStartsWith('%PDF', $bytes);

        $text = $this->pdfText($bytes);

        // Scoped to the KPI row rather than the whole document: the scanner
        // table further down legitimately prints "100%" as a station's own
        // share, which is not the coverage figure this test is pinning.
        $kpiEnd = strpos($text, 'Coverage by barangay');
        $this->assertNotFalse($kpiEnd, 'the KPI row must be followed by the barangay section');
        $kpiRow = substr($text, 0, $kpiEnd);

        $this->assertStringContainsString('50%', $kpiRow, 'the snapshot cached 1 of 2 served (50%), so that is what the KPI row must print');
        $this->assertStringNotContainsString('100%', $kpiRow, 'a fresh coverage() call after the snapshot would read 2 of 2 (100%), which must not leak into the KPI row');
        $this->assertMatchesRegularExpression('/\bEligible\b.*\bServed\b.*\bRemaining\b.*\bCoverage\b/s', $kpiRow);
        $this->assertMatchesRegularExpression('/\b2\s+1\s+1\s+50%/', $kpiRow, 'eligible 2, served 1, remaining 1, coverage 50%, the snapshot\'s figures');
    }

    private function pdfText(string $bytes): string
    {
        $dir = sys_get_temp_dir() . '/reports-pdf-snapshot-test-' . uniqid();
        mkdir($dir);
        $pdfPath = $dir . '/report.pdf';
        $txtPath = $dir . '/report.txt';
        file_put_contents($pdfPath, $bytes);

        exec('gs -q -dNOPAUSE -dBATCH -sDEVICE=txtwrite -sOutputFile=' . escapeshellarg($txtPath) . ' ' . escapeshellarg($pdfPath));

        $text = file_exists($txtPath) ? (string) file_get_contents($txtPath) : '';

        @unlink($pdfPath);
        @unlink($txtPath);
        @rmdir($dir);

        return $text;
    }
}
