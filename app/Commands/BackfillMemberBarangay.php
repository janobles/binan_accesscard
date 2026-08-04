<?php

namespace App\Commands;

use App\Models\Lookups\BarangayModel;
use App\Support\MemberFieldNormalizer;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * One-time backfill for `member.barangayID`, NULL on every family imported
 * before the eligibility feature (accesscardV20.sql added the column with no
 * data migration - sql/patches/v20-eligibility.sql is schema only).
 *
 * `member.address` has no dedicated barangay column: MemberFieldNormalizer::
 * combineAddressBarangay() folds it into "street address, barangay" at write
 * time, so the barangay is whatever text follows the LAST comma. This reads
 * that segment, folds it through MemberFieldNormalizer::barangayKey() - the
 * same fold the Excel importer and the family form already use to resolve a
 * typed barangay to an id - and looks it up via BarangayModel::
 * idByNormalizedName(). Reusing that exact pair guarantees this backfill can
 * never disagree with what the importer would have written for the same text.
 *
 * A pure-SQL backfill was ruled out: the fold does multibyte lowercasing, an
 * ñ->n substitution, "Sto./Sta." expansion and punctuation stripping (see
 * MemberFieldNormalizer::barangayKey()), none of which MySQL can reproduce
 * without silently drifting from the PHP fold as the barangay list or the
 * fold rules change.
 *
 * Only ever fills a NULL barangayID (never overwrites one already set) and
 * is safe to re-run: a second run finds no NULL rows left to match and
 * reports zero matched. Every run prints which addresses had no fold match,
 * so staff can fix the free-text spelling by hand (the same class of typo
 * validateBarangay() already flags as a BRGY warning on import).
 *
 * Usage:
 *   php spark members:backfill-barangay            back up + fill matching barangayID
 *   php spark members:backfill-barangay --dry-run   preview matches/misses, write nothing
 */
class BackfillMemberBarangay extends BaseCommand
{
    protected $group       = 'Migration';
    protected $name        = 'members:backfill-barangay';
    protected $description = 'Fill member.barangayID from the free-text barangay in member.address, for rows left NULL by the V20 patch.';
    protected $usage       = 'members:backfill-barangay [--dry-run]';
    protected $options     = ['--dry-run' => 'Preview matches/misses, without backing up or writing.'];

    public function run(array $params)
    {
        $dryRun = (bool) CLI::getOption('dry-run');
        $db     = Database::connect();

        foreach (['member', 'barangay'] as $table) {
            if (! $db->tableExists($table)) {
                CLI::error("Table {$table} not found - aborting.");

                return EXIT_ERROR;
            }
        }

        $barangayIds = (new BarangayModel())->idByNormalizedName();

        $rows = $db->table('member')
            ->select('memberID, address')
            ->where('barangayID IS NULL', null, false)
            ->where('address IS NOT NULL', null, false)
            ->where("address !=", '')
            ->get()->getResultArray();

        if ($rows === []) {
            CLI::write('No member rows with a NULL barangayID and a non-empty address - nothing to do.', 'green');

            return EXIT_SUCCESS;
        }

        $matches   = []; // memberID => barangayID
        $unmatched = []; // distinct raw barangay text that folded to no known barangay

        foreach ($rows as $row) {
            $barangayText = self::lastCommaSegment((string) $row['address']);
            if ($barangayText === '') {
                continue;
            }

            $key = MemberFieldNormalizer::barangayKey($barangayText);
            if ($key === '' || ! isset($barangayIds[$key])) {
                $unmatched[$barangayText] = true;

                continue;
            }

            $matches[(int) $row['memberID']] = $barangayIds[$key];
        }

        CLI::write('Rows with a NULL barangayID and a non-empty address: ' . count($rows), 'cyan');
        CLI::write('Would match: ' . count($matches), 'green');
        CLI::write('Would leave unmatched: ' . (count($rows) - count($matches)), 'yellow');

        if ($unmatched !== []) {
            CLI::write('Unmatched barangay text (fix the spelling by hand, then re-run):', 'yellow');
            foreach (array_keys($unmatched) as $text) {
                CLI::write('  ' . $text);
            }
        }

        if ($dryRun) {
            CLI::write('Dry run - no backup taken and nothing written.', 'yellow');

            return EXIT_SUCCESS;
        }

        if ($matches === []) {
            CLI::write('Nothing to write.', 'green');

            return EXIT_SUCCESS;
        }

        $backup = $this->backup($db);
        if ($backup === null) {
            CLI::error('Backup failed - aborting without writing anything.');

            return EXIT_ERROR;
        }
        CLI::write('Backup written: ' . $backup, 'green');

        $updated = 0;
        foreach ($matches as $memberId => $barangayId) {
            // Re-guards barangayID IS NULL per row, not just in the SELECT above,
            // so this stays safe to re-run even against a table that changed
            // between the SELECT and here.
            $updated += $db->table('member')
                ->where('memberID', $memberId)
                ->where('barangayID IS NULL', null, false)
                ->update(['barangayID' => $barangayId]);
        }

        CLI::write("Updated {$updated} member row(s).", 'green');

        return EXIT_SUCCESS;
    }

    /**
     * The text after the last comma in a combined "address, barangay" value,
     * trimmed. Empty when there is no comma (an address with no barangay
     * segment, or a legacy free-text row this fold cannot help).
     */
    private static function lastCommaSegment(string $address): string
    {
        $pos = strrpos($address, ',');
        if ($pos === false) {
            return '';
        }

        return trim(substr($address, $pos + 1));
    }

    /**
     * Dumps `member` to writable/backups via mysqldump before writing. Returns
     * the backup path on success, or null on failure. Mirrors
     * RemoveSectorDuplicateCategories::backup().
     */
    private function backup(\CodeIgniter\Database\BaseConnection $db): ?string
    {
        $config = (new \Config\Database())->default;
        $dir    = rtrim(WRITEPATH, '/\\') . DIRECTORY_SEPARATOR . 'backups';

        if (! is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $path = $dir . DIRECTORY_SEPARATOR . 'backfill-member-barangay-' . date('Ymd-His') . '.sql';
        $err  = $path . '.err';

        $host = (string) ($config['hostname'] ?? 'localhost');
        $port = (string) ($config['port'] ?? 3306);
        $user = (string) ($config['username'] ?? 'root');
        $pass = (string) ($config['password'] ?? '');
        $name = (string) ($config['database'] ?? '');

        $credsFile = $this->writeMysqlCredsFile($user, $pass);
        if ($credsFile === null) {
            CLI::error('Failed to create a temp credentials file for mysqldump.');

            return null;
        }

        $cmd = 'mysqldump'
            . ' --defaults-extra-file=' . escapeshellarg($credsFile)
            . ' -h ' . escapeshellarg($host)
            . ' -P ' . escapeshellarg($port)
            . ' ' . escapeshellarg($name)
            . ' member'
            . ' > ' . escapeshellarg($path)
            . ' 2> ' . escapeshellarg($err);

        $output = [];
        $code   = 0;
        exec($cmd, $output, $code);
        unlink($credsFile);

        if ($code !== 0 || ! is_file($path) || filesize($path) === 0) {
            CLI::error('mysqldump exit code ' . $code . '. See ' . $err);

            return null;
        }

        @unlink($err);

        return $path;
    }

    /**
     * Writes a --defaults-extra-file for mysqldump so the DB password never
     * appears as a CLI argument. Returns the temp file path, or null on
     * failure. Caller must unlink it.
     */
    private function writeMysqlCredsFile(string $user, string $pass): ?string
    {
        $path = tempnam(sys_get_temp_dir(), 'binan-mysqldump-');
        if ($path === false) {
            return null;
        }

        $written = file_put_contents($path, "[client]\nuser={$user}\npassword={$pass}\n");
        if ($written === false) {
            @unlink($path);

            return null;
        }
        @chmod($path, 0600);

        return $path;
    }
}
