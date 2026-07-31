<?php

namespace Tests\Unit;

use App\Libraries\ImportLookupCache;
use App\Libraries\ImportStagingStore;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Feature coverage for the review rows and apply endpoints through the real route,
 * filters and controller.
 *
 * The rows endpoint is what makes a 10,000-row import reviewable: the page asks for one
 * slice at a time instead of carrying every person in its HTML. Its failure modes matter
 * as much as its happy path, because the staging file can vanish mid-review (a sweep
 * after the 24h TTL, or the job committing in another tab) and an operator who is told
 * nothing would keep typing fixes into a review that can no longer be saved.
 *
 * The `job_queue` and `users` tables this needs do not exist in PHPUnit's in-memory
 * SQLite (no migrations, per repo policy - schema lives in the SQL dump only), so they
 * are built for the duration of each test and dropped after.
 *
 * @internal
 */
final class ImportReviewRowsTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    private string $stagingDir;

    /** Job IDs staged this test, so their ImportLookupCache file can be forgotten after. */
    private array $stagedJobIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->stagingDir = WRITEPATH . 'import-staging-test-' . uniqid('', true);
        mkdir($this->stagingDir, 0775, true);

        \CodeIgniter\Config\Services::injectMock('importStaging', new ImportStagingStore($this->stagingDir));

        $this->createSchema();
    }

    protected function tearDown(): void
    {
        foreach ((array) glob($this->stagingDir . '/*') as $file) {
            @unlink((string) $file);
        }

        @rmdir($this->stagingDir);

        // ImportLookupCache (unlike ImportStagingStore) has no service seam to point at the
        // temp dir above, so the apply endpoint writes its cache to the real writable/
        // import-staging under the job ID SQLite just handed out. SQLite resets that
        // auto-increment every test, so without this a later test can read an earlier
        // test's stale cache under the same ID.
        foreach ($this->stagedJobIds as $jobId) {
            (new ImportLookupCache())->forget($jobId);
        }

        \CodeIgniter\Config\Services::reset();
        $this->dropSchema();
        parent::tearDown();
    }

    private function createSchema(): void
    {
        $forge = \Config\Database::forge();

        $forge->addField([
            'userID'        => ['type' => 'INTEGER', 'auto_increment' => true],
            'account_level' => ['type' => 'VARCHAR', 'constraint' => 20],
        ]);
        $forge->addPrimaryKey('userID');
        $forge->createTable('users', true);

        $forge->addField([
            'jobID'          => ['type' => 'INTEGER', 'auto_increment' => true],
            'type'           => ['type' => 'VARCHAR', 'constraint' => 64],
            'payload'        => ['type' => 'TEXT', 'null' => true],
            'status'         => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'],
            'progress_total' => ['type' => 'INTEGER', 'default' => 0],
            'progress_done'  => ['type' => 'INTEGER', 'default' => 0],
            'checkpoint'     => ['type' => 'INTEGER', 'default' => 0],
            'result_json'    => ['type' => 'TEXT', 'null' => true],
            'message'        => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'userID'         => ['type' => 'INTEGER', 'null' => true],
            'ip_address'     => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'user_agent'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'attempts'       => ['type' => 'INTEGER', 'default' => 0],
            'max_attempts'   => ['type' => 'INTEGER', 'default' => 1],
            'available_at'   => ['type' => 'DATETIME', 'null' => true],
            'locked_at'      => ['type' => 'DATETIME', 'null' => true],
            'locked_by'      => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'dt_created'     => ['type' => 'DATETIME', 'null' => true],
            'dt_started'     => ['type' => 'DATETIME', 'null' => true],
            'dt_finished'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->addPrimaryKey('jobID');
        $forge->createTable('job_queue', true);

        // The apply endpoint revalidates through FamilyExcelImporter::existingHeadsForRows(),
        // which queries qr_control directly with no hasTable() guard once a staged row's
        // familyno looks like a real QR (accesscardV19.sql's qr_control, unprefixed here).
        $forge->addField([
            'control_no' => ['type' => 'INTEGER'],
            'headID'     => ['type' => 'INTEGER'],
        ]);
        $forge->addPrimaryKey('control_no');
        $forge->createTable('qr_control', true);
    }

    private function dropSchema(): void
    {
        $forge = \Config\Database::forge();

        foreach (['job_queue', 'users', 'qr_control'] as $table) {
            $forge->dropTable($table, true);
        }
    }

    /**
     * Stages a two-person import: a clean head and a member with a blocking SEX error.
     * Returns the job ID.
     */
    private function stageJob(int $userId): int
    {
        $db = db_connect();

        $db->table('job_queue')->insert([
            'type'        => 'family_import',
            'status'      => 'done',
            'result_json' => json_encode(['phase' => 'review', 'counts' => ['rows' => 2]]),
            'userID'      => $userId,
            'dt_created'  => date('Y-m-d H:i:s'),
        ]);

        $jobId = (int) $db->insertID();

        service('importStaging')->save($jobId, [
            'phase'      => 'review',
            'file'       => 'import.xlsx',
            'columns'    => ['sex' => 'H', 'lastname' => 'C'],
            'fileErrors' => [],
            'changes'    => [],
            'rows'       => [
                ['sheetRow' => 3, 'data' => [
                    'familyno' => '6001', 'relationship' => 'Head', 'lastname' => 'Cruz',
                    'firstname' => 'Juan', 'birthday' => '03-03-1980', 'sex' => 'Male',
                    'address' => '1 Street', 'barangay' => 'Canlalay',
                    'civilstatus' => 'Single', 'education' => 'College', 'job' => 'Driver',
                    'monthlyincome' => '5000',
                ]],
                ['sheetRow' => 4, 'data' => [
                    'familyno' => '6001', 'relationship' => 'Child', 'lastname' => 'Cruz',
                    'firstname' => 'Ana', 'birthday' => '02-02-2010', 'sex' => 'Mail',
                    'address' => '1 Street', 'barangay' => 'Canlalay',
                    'civilstatus' => 'Single', 'education' => 'Elementary', 'job' => 'None',
                    'monthlyincome' => '0',
                ]],
            ],
            'errors' => [[
                'sheetRow' => 4, 'familyNo' => '6001', 'code' => 'SEX', 'field' => 'sex',
                'message' => 'Sex must be Male or Female.', 'severity' => 'blocking',
            ]],
            'counts' => ['rows' => 2, 'blocking' => 1, 'warnings' => 0],
        ]);

        $this->stagedJobIds[] = $jobId;

        return $jobId;
    }

    private function encoder(): int
    {
        $db = db_connect();
        $db->table('users')->insert(['account_level' => 'encoder']);

        return (int) $db->insertID();
    }

    /** @param array<string, mixed> $session */
    private function session(int $userId, string $role = 'encoder'): array
    {
        return ['is_logged_in' => true, 'role' => $role, 'user_id' => $userId];
    }

    public function testItReturnsTheFirstPageOfRows(): void
    {
        $userId = $this->encoder();
        $jobId  = $this->stageJob($userId);

        $result = $this->withSession($this->session($userId))
            ->get('records/import/review/' . $jobId . '/rows?page=1&per=25');

        $result->assertStatus(200);
        $json = json_decode((string) $result->response()->getBody(), true);

        $this->assertCount(2, $json['rows']);
        $this->assertSame(2, $json['total']);
        $this->assertSame(2, $json['filtered']);
        $this->assertSame(1, $json['page']);
        $this->assertSame(25, $json['per']);
    }

    public function testItHonoursTheSeverityFilter(): void
    {
        $userId = $this->encoder();
        $jobId  = $this->stageJob($userId);

        $result = $this->withSession($this->session($userId))
            ->get('records/import/review/' . $jobId . '/rows?severity=blocking');

        $json = json_decode((string) $result->response()->getBody(), true);

        $this->assertSame(1, $json['filtered']);
        $this->assertSame(4, $json['rows'][0]['sheetRow']);
        $this->assertSame('sex', $json['rows'][0]['fields'][0]['field']);
    }

    public function testItReturns404WhenTheStagingFileIsGone(): void
    {
        $userId = $this->encoder();
        $jobId  = $this->stageJob($userId);

        service('importStaging')->delete($jobId);

        $result = $this->withSession($this->session($userId))
            ->get('records/import/review/' . $jobId . '/rows');

        $result->assertStatus(404);
        $this->assertStringContainsString('no longer available', (string) $result->response()->getBody());
    }

    /**
     * A role the records-import manifest entry does not list never reaches the
     * controller's own 403 guard: RoleNavFilter (app/Filters/RoleNavFilter.php)
     * rejects it first with a bare 404, by design (its docblock: a role without
     * a manifest entry gets a 404 rather than a redirect, so the response does
     * not confirm a page it may not use even exists).
     */
    public function testItReturns404ForARoleWithoutImportAccess(): void
    {
        $db = db_connect();
        $db->table('users')->insert(['account_level' => 'viewer']);
        $userId = (int) $db->insertID();
        $jobId  = $this->stageJob($userId);

        $result = $this->withSession($this->session($userId, 'viewer'))
            ->get('records/import/review/' . $jobId . '/rows');

        $result->assertStatus(404);
    }

    public function testApplyPatchesTheRowAndClearsItsFlag(): void
    {
        $userId = $this->encoder();
        $jobId  = $this->stageJob($userId);

        $result = $this->withSession($this->session($userId))
            ->post('records/import/review/' . $jobId . '/apply', [
                'import_row' => 4,
                'fields'     => ['sex' => 'Female'],
            ]);

        $result->assertStatus(200);
        $json = json_decode((string) $result->response()->getBody(), true);

        $this->assertSame('', $json['row']['severity']);
        $this->assertSame([], $json['row']['fields']);
        $this->assertSame(0, $json['counts']['blocking']);

        $staged = service('importStaging')->load($jobId);
        $this->assertSame('Female', $staged['rows'][1]['data']['sex']);
    }

    public function testApplyRejectsAFieldThatIsNotAnImporterField(): void
    {
        $userId = $this->encoder();
        $jobId  = $this->stageJob($userId);

        $before = service('importStaging')->load($jobId);

        $result = $this->withSession($this->session($userId))
            ->post('records/import/review/' . $jobId . '/apply', [
                'import_row' => 4,
                'fields'     => ['password' => 'x'],
            ]);

        $result->assertStatus(422);

        // Refusing outright, not skipping the bad key: nothing may be written.
        $this->assertSame($before['rows'], service('importStaging')->load($jobId)['rows']);
    }

    public function testApplyRejectsAnUnknownSheetRow(): void
    {
        $userId = $this->encoder();
        $jobId  = $this->stageJob($userId);

        $result = $this->withSession($this->session($userId))
            ->post('records/import/review/' . $jobId . '/apply', [
                'import_row' => 99999,
                'fields'     => ['sex' => 'Female'],
            ]);

        $result->assertStatus(422);
    }

    public function testApplyAsksForARefreshWhenItTouchesACrossRowField(): void
    {
        // familyno / relationship / address / barangay drive rules that reach other
        // rows, so the client must refetch instead of splicing the one row back in.
        $userId = $this->encoder();
        $jobId  = $this->stageJob($userId);

        $result = $this->withSession($this->session($userId))
            ->post('records/import/review/' . $jobId . '/apply', [
                'import_row' => 4,
                'fields'     => ['relationship' => 'Head'],
            ]);

        $json = json_decode((string) $result->response()->getBody(), true);

        $this->assertTrue($json['refresh']);
    }

    public function testApplyDoesNotAskForARefreshOnAnOrdinaryField(): void
    {
        $userId = $this->encoder();
        $jobId  = $this->stageJob($userId);

        $result = $this->withSession($this->session($userId))
            ->post('records/import/review/' . $jobId . '/apply', [
                'import_row' => 4,
                'fields'     => ['sex' => 'Female'],
            ]);

        $json = json_decode((string) $result->response()->getBody(), true);

        $this->assertFalse($json['refresh']);
    }

    public function testApplyReturns404WhenTheStagingFileIsGone(): void
    {
        $userId = $this->encoder();
        $jobId  = $this->stageJob($userId);

        service('importStaging')->delete($jobId);

        $result = $this->withSession($this->session($userId))
            ->post('records/import/review/' . $jobId . '/apply', [
                'import_row' => 4,
                'fields'     => ['sex' => 'Female'],
            ]);

        $result->assertStatus(404);
    }

    public function testApplyStillValidatesAfterANonInvalidatingEditReusesTheCache(): void
    {
        // A smoke check that the cached path produces a usable report end to end. The
        // claim the cache actually rests on - that nothing but familyno and lastname can
        // stale the lookups - is pinned without a database in
        // FamilyExcelImporterTest::testLookupKeysDeriveOnlyFromQrAndLastname().
        $userId = $this->encoder();
        $jobId  = $this->stageJob($userId);

        $this->withSession($this->session($userId))
            ->post('records/import/review/' . $jobId . '/apply', [
                'import_row' => 4, 'fields' => ['sex' => 'Female'],
            ]);

        $result = $this->withSession($this->session($userId))
            ->post('records/import/review/' . $jobId . '/apply', [
                'import_row' => 4, 'fields' => ['birthday' => '02-02-2011'],
            ]);

        $result->assertStatus(200);
        $json = json_decode((string) $result->response()->getBody(), true);

        $this->assertSame(0, $json['counts']['blocking']);
    }
}
