<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * Phase B cleanup: removes the `category` rows that merely duplicate a sector.
 *
 * After Phase A a sector (SC = Senior Citizen) also acts as the service category for
 * its own programs, so keeping an identical SC row in Manage Categories is redundant.
 * This deletes every `category` row whose code OR name matches an active sector,
 * leaving only the standalone service categories that have no sector (FA/SWPS/EDA).
 *
 * `services.category` is a NAME string and is not touched — SC programs keep the
 * "Senior Citizen" label, now backed by the sector instead of a category row. Safe:
 * dumps `category` (+ services, sector) to writable/backups first, and is idempotent
 * (a second run finds nothing to delete).
 *
 * Usage:
 *   php spark categories:dedupe-sectors            back up + delete sector-duplicate categories
 *   php spark categories:dedupe-sectors --dry-run  preview which rows would be deleted
 */
class RemoveSectorDuplicateCategories extends BaseCommand
{
    protected $group       = 'Migration';
    protected $name        = 'categories:dedupe-sectors';
    protected $description = 'Delete category rows that duplicate a sector (a sector is its own service category).';
    protected $usage       = 'categories:dedupe-sectors [--dry-run]';
    protected $options     = ['--dry-run' => 'Preview the rows that would be deleted, without backing up or writing.'];

    public function run(array $params)
    {
        $dryRun = (bool) CLI::getOption('dry-run');
        $db     = Database::connect();

        foreach (['category', 'sector'] as $table) {
            if (! $db->tableExists($table)) {
                CLI::error("Table {$table} not found — aborting.");

                return EXIT_ERROR;
            }
        }

        // Active sector codes + names (the categories these duplicate).
        $sectorCodes = [];
        $sectorNames = [];
        $sectorBuilder = $db->table('sector');
        if ($db->fieldExists('dt_deleted', 'sector')) {
            $sectorBuilder->where('dt_deleted IS NULL', null, false);
        }
        foreach ($sectorBuilder->select('shortcode, name')->get()->getResultArray() as $row) {
            $sectorCodes[] = strtoupper(trim((string) ($row['shortcode'] ?? '')));
            $sectorNames[] = strtolower(trim((string) ($row['name'] ?? '')));
        }

        // Categories that duplicate a sector (by code or name).
        $doomed = [];
        foreach ($db->table('category')->select('categoryID, code, name')->get()->getResultArray() as $row) {
            $code = strtoupper(trim((string) ($row['code'] ?? '')));
            $name = strtolower(trim((string) ($row['name'] ?? '')));

            if (in_array($code, $sectorCodes, true) || in_array($name, $sectorNames, true)) {
                $doomed[] = $row;
            }
        }

        if ($doomed === []) {
            CLI::write('No sector-duplicate categories found — nothing to do.', 'green');

            return EXIT_SUCCESS;
        }

        CLI::write('Categories that duplicate a sector (will be removed):', 'cyan');
        foreach ($doomed as $row) {
            CLI::write('  ' . str_pad((string) $row['code'], 6) . (string) $row['name']);
        }

        if ($dryRun) {
            CLI::write('Dry run — no backup taken and nothing deleted.', 'yellow');

            return EXIT_SUCCESS;
        }

        $backup = $this->backup($db);

        if ($backup === null) {
            CLI::error('Backup failed — aborting without deleting anything.');

            return EXIT_ERROR;
        }

        CLI::write('Backup written: ' . $backup, 'green');

        $ids = array_map(static fn (array $r): int => (int) $r['categoryID'], $doomed);
        $db->table('category')->whereIn('categoryID', $ids)->delete();

        CLI::write('Deleted ' . count($ids) . ' sector-duplicate category row(s). Manage Categories now holds only standalone service categories.', 'green');

        return EXIT_SUCCESS;
    }

    /**
     * Dumps the affected tables to writable/backups via mysqldump. Returns the backup
     * path on success, or null on failure.
     */
    private function backup(\CodeIgniter\Database\BaseConnection $db): ?string
    {
        $config = (new \Config\Database())->default;
        $dir    = rtrim(WRITEPATH, '/\\') . DIRECTORY_SEPARATOR . 'backups';

        if (! is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $path = $dir . DIRECTORY_SEPARATOR . 'dedupe-categories-' . date('Ymd-His') . '.sql';
        $err  = $path . '.err';

        $dump = is_file('C:\\xampp\\mysql\\bin\\mysqldump.exe') ? 'C:\\xampp\\mysql\\bin\\mysqldump.exe' : 'mysqldump';

        $host = (string) ($config['hostname'] ?? 'localhost');
        $port = (string) ($config['port'] ?? 3306);
        $user = (string) ($config['username'] ?? 'root');
        $pass = (string) ($config['password'] ?? '');
        $name = (string) ($config['database'] ?? '');

        $cmd = escapeshellarg($dump)
            . ' -h ' . escapeshellarg($host)
            . ' -P ' . escapeshellarg($port)
            . ' -u ' . escapeshellarg($user)
            . ($pass !== '' ? ' -p' . escapeshellarg($pass) : '')
            . ' ' . escapeshellarg($name)
            . ' category services sector'
            . ' > ' . escapeshellarg($path)
            . ' 2> ' . escapeshellarg($err);

        $output = [];
        $code   = 0;
        exec($cmd, $output, $code);

        if ($code !== 0 || ! is_file($path) || filesize($path) === 0) {
            CLI::error('mysqldump exit code ' . $code . '. See ' . $err);

            return null;
        }

        @unlink($err);

        return $path;
    }
}
