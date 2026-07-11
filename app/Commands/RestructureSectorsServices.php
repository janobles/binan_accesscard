<?php

namespace App\Commands;

use App\Libraries\SectorIds;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * One-time Phase A migration: realigns the sector/services/category tables to the
 * CSWD Family Profiling Form v2 model, then rewrites member assignments by REUSING
 * the existing member.sectorID JSON arrays (no schema change to that column).
 *
 * Before: `sector` held programs (SC1-9, PWD1-5, SP1-2, B1-3, OTHERS) grouped under
 * `category`; `services` held FA/SWPS/EDA. After:
 *   - `sector`   = 10 flat classifications (SC, PWD, SP, B, LGBT, OFW, IP, IDP, PDL, OTHER)
 *   - `services` = the existing 27 + the 19 migrated programs (46), each with a category
 *   - `category` = the 7 service categories (SC->Senior Citizen ... EDA->Emergency...)
 *
 * Member reuse: each old program ID already in member.sectorID becomes (a) a
 * member_services row (the program is now a service) and (b) its parent classification
 * in the rewritten member.sectorID array. Nothing is lost; the JSON-array pipeline
 * (SectorIds / MemberModel::normalizeSectorIdStorage) is untouched.
 *
 * Safety: dumps sector/services/category/member/member_services to writable/backups
 * first (aborts if the dump fails) and runs the whole transform in one transaction.
 * Idempotency: refuses to run if the sector table already looks restructured (a row
 * with shortcode LGBT), unless --force.
 *
 * Usage:
 *   php spark restructure:sectors-services            run the migration (backs up first)
 *   php spark restructure:sectors-services --dry-run  preview counts, no backup, no writes
 *   php spark restructure:sectors-services --force     bypass the already-restructured guard
 */
class RestructureSectorsServices extends BaseCommand
{
    protected $group       = 'Migration';
    protected $name        = 'restructure:sectors-services';
    protected $description = 'Move sector programs into services, flatten sectors to classifications, and rewrite members from their existing sector JSON arrays.';
    protected $usage       = 'restructure:sectors-services [--dry-run] [--force]';
    protected $options     = [
        '--dry-run' => 'Preview the planned changes without backing up or writing.',
        '--force'   => 'Run even if the sector table already looks restructured.',
    ];

    /** The 10 flat classifications the sector table becomes (code => name), in display order. */
    private const SECTORS = [
        'SC'    => 'Senior Citizen',
        'PWD'   => 'Person with Disability',
        'SP'    => 'Solo Parent',
        'B'     => 'Bata (Children)',
        'LGBT'  => 'LGBTQIA+',
        'OFW'   => 'Overseas Filipino Worker',
        'IP'    => 'Indigenous People',
        'IDP'   => 'Internally Displaced Person',
        'PDL'   => 'Persons Deprived of Liberty',
        'OTHER' => 'Other Sectors',
    ];

    /**
     * Service category names by code. Used to LABEL the migrated SC/PWD/SP/B programs
     * (services.category). SC/PWD/SP/B are backed by sectors — see STANDALONE_CATEGORIES
     * for what actually gets seeded into the `category` table.
     */
    private const SERVICE_CATEGORIES = [
        'SC'   => 'Senior Citizen',
        'PWD'  => 'Person with Disability',
        'SP'   => 'Solo Parent',
        'B'    => 'Bata (Children)',
        'FA'   => 'Financial Assistance Programs',
        'SWPS' => 'Social Welfare Programs and Services',
        'EDA'  => 'Emergency / Disaster Assistance Programs',
    ];

    /**
     * The standalone service categories the `category` table is seeded with — only the
     * ones with NO matching sector (a sector doubles as its own service category, so
     * SC/PWD/SP/B are intentionally excluded here). Phase B.
     */
    private const STANDALONE_CATEGORIES = [
        'FA'   => 'Financial Assistance Programs',
        'SWPS' => 'Social Welfare Programs and Services',
        'EDA'  => 'Emergency / Disaster Assistance Programs',
    ];

    /** Classification prefixes whose numbered sector rows are real programs to migrate into services. */
    private const PROGRAM_PREFIXES = ['SC', 'PWD', 'SP', 'B'];

    public function run(array $params)
    {
        $dryRun = (bool) CLI::getOption('dry-run');
        $force  = (bool) CLI::getOption('force');
        $db     = Database::connect();

        foreach (['sector', 'services', 'category', 'member', 'member_services'] as $table) {
            if (! $db->tableExists($table)) {
                CLI::error("Table {$table} not found — aborting.");

                return EXIT_ERROR;
            }
        }

        // Idempotency guard: a flat classification row means we already ran.
        $alreadyDone = $db->table('sector')->where('UPPER(shortcode)', 'LGBT')->countAllResults() > 0;

        if ($alreadyDone && ! $force) {
            CLI::error('The sector table already looks restructured (found shortcode LGBT). Re-run with --force to override.');

            return EXIT_ERROR;
        }

        // 1. Snapshot old sector rows and classify each.
        $oldSectors = $db->table('sector')->select('sectorID, shortcode, name')->get()->getResultArray();
        $snapshot   = [];   // oldSectorID => [shortcode, name, class, isProgram]

        foreach ($oldSectors as $row) {
            $id        = (int) ($row['sectorID'] ?? 0);
            $shortcode = strtoupper(trim((string) ($row['shortcode'] ?? '')));
            $prefix    = $this->prefixOf($shortcode);
            $isProgram = in_array($prefix, self::PROGRAM_PREFIXES, true) && preg_match('/\d/', $shortcode) === 1;
            $class     = isset(self::SECTORS[$prefix]) ? $prefix : null; // null = junk (e.g. TC)

            $snapshot[$id] = [
                'shortcode' => $shortcode,
                'name'      => trim((string) ($row['name'] ?? '')),
                'class'     => $class,
                'isProgram' => $isProgram,
            ];
        }

        // 2. Plan the migrated services (deterministic order: classification, then number).
        $programs = array_filter($snapshot, static fn (array $s): bool => $s['isProgram']);
        uasort($programs, fn (array $a, array $b): int => $this->programSortKey($a['shortcode']) <=> $this->programSortKey($b['shortcode']));

        $maxServiceId = (int) ($db->table('services')->selectMax('serviceID', 'm')->get()->getRowArray()['m'] ?? 0);
        $nextId       = $maxServiceId + 1;
        $oldToService = [];         // oldSectorID => newServiceID
        $newServices  = [];         // rows to insert

        foreach ($programs as $oldId => $s) {
            $categoryName          = self::SERVICE_CATEGORIES[$s['class']] ?? '';
            $oldToService[$oldId]  = $nextId;
            $newServices[]         = [
                'serviceID'   => $nextId,
                'shortcode'   => $s['shortcode'],
                'category'    => $categoryName,
                'name'        => $s['name'],
                'description' => '',
            ];
            $nextId++;
        }

        // 3. Load members up front so we compute new arrays before writing anything.
        $members = $db->table('member')->select('memberID, sectorID')->get()->getResultArray();

        // Existing member->service links (to skip re-inserts / dedupe).
        $existingLinks = [];
        foreach ($db->table('member_services')->select('memberID, serviceID')->get()->getResultArray() as $link) {
            $existingLinks[(int) $link['memberID']][(int) $link['serviceID']] = true;
        }

        $this->reportPlan($snapshot, $newServices, count($members));

        if ($dryRun) {
            CLI::write('Dry run — no backup taken and no changes written.', 'yellow');

            return EXIT_SUCCESS;
        }

        // Backup before any write.
        $backup = $this->backup($db);

        if ($backup === null) {
            CLI::error('Backup failed — aborting without writing anything.');

            return EXIT_ERROR;
        }

        CLI::write('Backup written: ' . $backup, 'green');

        $db->transBegin();

        // 4. Insert migrated services.
        if ($newServices !== []) {
            $db->table('services')->insertBatch($newServices);
        }

        // 5. Rebuild category as the standalone service categories only (sector-backed
        //    categories SC/PWD/SP/B are represented by their sectors — Phase B).
        // DELETE, not TRUNCATE: TRUNCATE implicit-commits in MySQL even inside a
        // transaction, so a later failure couldn't roll it back. Neither table's
        // rows are referenced by hardcoded id elsewhere (only by shortcode/code/
        // name, read back below), so losing the auto-increment reset is harmless.
        $db->query('DELETE FROM `category`');
        $categoryRows = [];
        foreach (self::STANDALONE_CATEGORIES as $code => $name) {
            $categoryRows[] = ['code' => $code, 'name' => $name];
        }
        $db->table('category')->insertBatch($categoryRows);

        // 6. Rebuild sector as the 10 classifications, then read back their new IDs.
        $db->query('DELETE FROM `sector`');
        $sectorRows = [];
        foreach (self::SECTORS as $code => $name) {
            $sectorRows[] = ['shortcode' => $code, 'name' => $name, 'description' => ''];
        }
        $db->table('sector')->insertBatch($sectorRows);

        $classToSectorId = [];
        foreach ($db->table('sector')->select('sectorID, shortcode')->get()->getResultArray() as $row) {
            $classToSectorId[strtoupper(trim((string) $row['shortcode']))] = (int) $row['sectorID'];
        }

        // 7. Rewrite members: reuse their old sector arrays for both services and classifications.
        $serviceInserts = [];
        $memberUpdates  = 0;

        foreach ($members as $member) {
            $memberId = (int) ($member['memberID'] ?? 0);
            $oldIds   = SectorIds::normalize($member['sectorID'] ?? '[]');

            $newSectorIds = [];

            foreach ($oldIds as $oldId) {
                $s = $snapshot[$oldId] ?? null;

                if ($s === null) {
                    continue; // references a sector that no longer exists — drop
                }

                // The program becomes a service this member received.
                if (isset($oldToService[$oldId])) {
                    $serviceId = $oldToService[$oldId];

                    if (! isset($existingLinks[$memberId][$serviceId])) {
                        $existingLinks[$memberId][$serviceId] = true;
                        $serviceInserts[] = ['memberID' => $memberId, 'serviceID' => $serviceId];
                    }
                }

                // The parent classification becomes the member's sector.
                if ($s['class'] !== null && isset($classToSectorId[$s['class']])) {
                    $newSectorIds[$classToSectorId[$s['class']]] = true;
                }
            }

            $encoded = SectorIds::toStorage(array_keys($newSectorIds));

            if ($encoded !== (string) ($member['sectorID'] ?? '')) {
                $db->table('member')->where('memberID', $memberId)->update(['sectorID' => $encoded]);
                $memberUpdates++;
            }
        }

        foreach (array_chunk($serviceInserts, 500) as $chunk) {
            $db->table('member_services')->insertBatch($chunk);
        }

        if ($db->transStatus() === false) {
            $db->transRollback();
            CLI::error('Transaction failed — rolled back. Restore from the backup if needed: ' . $backup);

            return EXIT_ERROR;
        }

        $db->transCommit();

        CLI::write('Migration complete.', 'green');
        CLI::write(sprintf(
            '  sectors: %d  |  services added: %d  |  categories: %d  |  members rewritten: %d  |  service links added: %d',
            count(self::SECTORS),
            count($newServices),
            count(self::STANDALONE_CATEGORIES),
            $memberUpdates,
            count($serviceInserts)
        ), 'green');

        return EXIT_SUCCESS;
    }

    /** Leading alpha prefix of a shortcode, folding OSCA/OSWA -> SC and OTHERS -> OTHER. */
    private function prefixOf(string $shortcode): string
    {
        $prefix = preg_match('/^([A-Z]+)/', $shortcode, $m) === 1 ? $m[1] : $shortcode;

        if ($prefix === 'OSCA' || $prefix === 'OSWA') {
            return 'SC';
        }

        if ($prefix === 'OTHERS') {
            return 'OTHER';
        }

        return $prefix;
    }

    /** Sort key so migrated services land as SC1..SC9, PWD1.., SP1.., B1.. */
    private function programSortKey(string $shortcode): int
    {
        $order  = array_flip(self::PROGRAM_PREFIXES);          // SC=0, PWD=1, SP=2, B=3
        $prefix = $this->prefixOf($shortcode);
        $number = preg_match('/(\d+)/', $shortcode, $m) === 1 ? (int) $m[1] : 0;

        return ($order[$prefix] ?? 9) * 1000 + $number;
    }

    /** Prints the planned change counts before executing. */
    private function reportPlan(array $snapshot, array $newServices, int $memberCount): void
    {
        $junk = array_filter($snapshot, static fn (array $s): bool => $s['class'] === null);

        CLI::write('Planned restructure:', 'cyan');
        CLI::write('  old sector rows        : ' . count($snapshot));
        CLI::write('  -> migrated to services: ' . count($newServices));
        CLI::write('  -> dropped as junk     : ' . count($junk) . ($junk === [] ? '' : ' (' . implode(', ', array_map(static fn (array $s): string => $s['shortcode'], $junk)) . ')'));
        CLI::write('  new sector count       : ' . count(self::SECTORS));
        CLI::write('  new category count     : ' . count(self::STANDALONE_CATEGORIES));
        CLI::write('  members to scan        : ' . $memberCount);
        CLI::newLine();
    }

    /**
     * Dumps the five affected tables to writable/backups via mysqldump. Returns the
     * backup path on success, or null on failure.
     */
    private function backup(\CodeIgniter\Database\BaseConnection $db): ?string
    {
        $config = (new \Config\Database())->default;
        $dir    = rtrim(WRITEPATH, '/\\') . DIRECTORY_SEPARATOR . 'backups';

        if (! is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $path = $dir . DIRECTORY_SEPARATOR . 'restructure-' . date('Ymd-His') . '.sql';
        $err  = $path . '.err';

        $host = (string) ($config['hostname'] ?? 'localhost');
        $port = (string) ($config['port'] ?? 3306);
        $user = (string) ($config['username'] ?? 'root');
        $pass = (string) ($config['password'] ?? '');
        $name = (string) ($config['database'] ?? '');

        // Credentials go in a --defaults-extra-file, not a -p CLI arg, so the
        // password never appears in the process list while mysqldump runs.
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
            . ' sector services category member member_services'
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
     * appears as a CLI argument (visible in the process list while it runs).
     * Returns the temp file path, or null on failure. Caller must unlink it.
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
