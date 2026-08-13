<?php

namespace App\Commands;

use App\Commands\Concerns\DumpsTableBackup;
use App\Libraries\SectorIds;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * One-time copy of `member.sectorID` (a JSON list held in a varchar) into the
 * `member_sectors` junction table that v22-normalize.sql creates.
 *
 * The old column could not be joined, indexed or constrained, so a sector
 * filter had to match ids inside the string and a deleted sector left its id
 * behind in every member row that carried it. The junction is the same shape
 * member_services has always had.
 *
 * PHP rather than SQL because MariaDB 10.4 (what XAMPP ships) has no
 * JSON_TABLE, and because SectorIds::normalize() already accepts every form
 * the column grew over time: '[1,2]', '1,2', '' and NULL.
 *
 * Ids pointing at a sector row that no longer exists are reported and skipped,
 * not written - the junction has a foreign key and would reject them anyway.
 * Safe to re-run: rows already present are left alone.
 *
 * Usage:
 *   php spark members:split-sectors            back up + copy
 *   php spark members:split-sectors --dry-run  preview, write nothing
 */
class SplitMemberSectors extends BaseCommand
{
    use DumpsTableBackup;

    protected $group       = 'Migration';
    protected $name        = 'members:split-sectors';
    protected $description = 'Copy the member.sectorID JSON list into the member_sectors junction table.';
    protected $usage       = 'members:split-sectors [--dry-run]';
    protected $options     = ['--dry-run' => 'Preview the copy, without backing up or writing.'];

    public function run(array $params)
    {
        $dryRun = (bool) CLI::getOption('dry-run');
        $db     = Database::connect();

        if (! $db->tableExists('member_sectors')) {
            CLI::error('Table member_sectors not found - run sql/patches/v22-normalize.sql first.');

            return EXIT_ERROR;
        }

        if (! $db->fieldExists('sectorID', 'member')) {
            CLI::write('member.sectorID is already gone - nothing to copy.', 'green');

            return EXIT_SUCCESS;
        }

        $knownSectors = array_map(
            static fn (array $row): int => (int) $row['sectorID'],
            $db->table('sector')->select('sectorID')->get()->getResultArray()
        );
        $knownSectors = array_flip($knownSectors);

        $existing = [];

        foreach ($db->table('member_sectors')->select('memberID, sectorID')->get()->getResultArray() as $row) {
            $existing[(int) $row['memberID'] . ':' . (int) $row['sectorID']] = true;
        }

        $rows = $db->table('member')
            ->select('memberID, sectorID')
            ->where("sectorID IS NOT NULL AND sectorID NOT IN ('', '[]')", null, false)
            ->get()->getResultArray();

        $pairs   = [];
        $orphans = []; // sectorID that no sector row matches => how many members carried it

        foreach ($rows as $row) {
            $memberId = (int) $row['memberID'];

            foreach (SectorIds::normalize($row['sectorID']) as $sectorId) {
                if (! isset($knownSectors[$sectorId])) {
                    $orphans[$sectorId] = ($orphans[$sectorId] ?? 0) + 1;

                    continue;
                }

                if (isset($existing[$memberId . ':' . $sectorId])) {
                    continue;
                }

                $pairs[] = ['memberID' => $memberId, 'sectorID' => $sectorId];
            }
        }

        CLI::write('Member rows carrying at least one sector: ' . count($rows), 'cyan');
        CLI::write('Pairs to insert: ' . count($pairs), 'green');
        CLI::write('Pairs already in member_sectors: ' . count($existing), 'cyan');

        if ($orphans !== []) {
            CLI::write('Sector ids with no sector row (skipped - the junction would reject them):', 'yellow');

            foreach ($orphans as $sectorId => $count) {
                CLI::write("  sectorID {$sectorId} carried by {$count} member row(s)");
            }
        }

        if ($dryRun) {
            CLI::write('Dry run - no backup taken and nothing written.', 'yellow');

            return EXIT_SUCCESS;
        }

        if ($pairs === []) {
            CLI::write('Nothing to write.', 'green');

            return EXIT_SUCCESS;
        }

        $backup = $this->backupTable('member', 'split-member-sectors');

        if ($backup === null) {
            CLI::error('Backup failed - aborting without writing anything.');

            return EXIT_ERROR;
        }

        CLI::write('Backup written: ' . $backup, 'green');

        // Chunked so a 20K-family import's worth of pairs does not build one
        // statement longer than max_allowed_packet.
        foreach (array_chunk($pairs, 500) as $chunk) {
            $db->table('member_sectors')->insertBatch($chunk);
        }

        CLI::write('Inserted ' . count($pairs) . ' member_sectors row(s).', 'green');
        CLI::write('Verify, then run sql/patches/v22-normalize-drop.sql to drop member.sectorID.', 'yellow');

        return EXIT_SUCCESS;
    }
}
