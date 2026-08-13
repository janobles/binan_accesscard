<?php

namespace App\Commands;

use App\Commands\Concerns\DumpsTableBackup;
use App\Models\Lookups\BarangayModel;
use App\Support\MemberFieldNormalizer;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * One-time cleanup that takes the barangay back out of `member.address`.
 *
 * V20 added member.barangayID but left combineAddressBarangay() appending the
 * barangay name to the address, so the same fact lived in two places and only
 * the free-text copy was displayed. That copy is what made every barangay
 * filter and sort unreliable: the text is whatever a worker or a spreadsheet
 * typed ("Sto. Tomas", "SANTO TOMAS (CALABUSO)"), while barangayID is exact.
 *
 * Runs AFTER members:backfill-barangay, and refuses to run before it: a row
 * whose barangayID is still NULL has the address text as its only record of
 * the barangay, and stripping it there would destroy the value. The strip only
 * removes a trailing segment that folds - via MemberFieldNormalizer::
 * barangayKey(), the same fold the importer and the form use - to the barangay
 * the row already points at, so a segment that means something else is left
 * alone.
 *
 * Safe to re-run: an address with no matching trailing barangay is skipped.
 *
 * Usage:
 *   php spark members:split-address            back up + strip
 *   php spark members:split-address --dry-run  preview, write nothing
 */
class SplitMemberAddress extends BaseCommand
{
    use DumpsTableBackup;

    protected $group       = 'Migration';
    protected $name        = 'members:split-address';
    protected $description = 'Remove the barangay name from member.address, leaving member.barangayID as its only source.';
    protected $usage       = 'members:split-address [--dry-run]';
    protected $options     = ['--dry-run' => 'Preview the strip, without backing up or writing.'];

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

        $pending = $db->table('member')
            ->where('barangayID IS NULL', null, false)
            ->where("address LIKE '%,%'", null, false)
            ->where('dt_deleted IS NULL', null, false)
            ->countAllResults();

        if ($pending > 0) {
            CLI::error("{$pending} member row(s) still have a NULL barangayID and a comma in their address.");
            CLI::write('Run `php spark members:backfill-barangay` first - stripping now would lose their barangay.', 'yellow');

            return EXIT_ERROR;
        }

        $names = (new BarangayModel())->nameMap();

        // No barangay rows means every address below is skipped and the command
        // reports "Nothing to write." on a database it never actually looked at.
        if ($names === []) {
            CLI::error('The barangay table returned no rows - aborting rather than reporting a no-op.');

            return EXIT_ERROR;
        }

        $rows = $db->table('member')
            ->select('memberID, address, barangayID')
            ->where('barangayID IS NOT NULL', null, false)
            ->where('address IS NOT NULL', null, false)
            ->where('address !=', '')
            ->get()->getResultArray();

        $updates = []; // memberID => new address

        foreach ($rows as $row) {
            $address  = (string) $row['address'];
            $barangay = (string) ($names[(int) $row['barangayID']] ?? '');

            if ($barangay === '') {
                continue;
            }

            $stripped = self::stripBarangay($address, $barangay);

            if ($stripped !== $address) {
                $updates[(int) $row['memberID']] = $stripped;
            }
        }

        CLI::write('Member rows with a barangayID and an address: ' . count($rows), 'cyan');
        CLI::write('Addresses to strip: ' . count($updates), 'green');
        CLI::write('Already clean: ' . (count($rows) - count($updates)), 'cyan');

        if ($dryRun) {
            foreach (array_slice($updates, 0, 10, true) as $memberId => $address) {
                CLI::write("  #{$memberId}: " . ($address === '' ? '(address becomes empty)' : $address));
            }

            CLI::write('Dry run - no backup taken and nothing written.', 'yellow');

            return EXIT_SUCCESS;
        }

        if ($updates === []) {
            CLI::write('Nothing to write.', 'green');

            return EXIT_SUCCESS;
        }

        $backup = $this->backupTable('member', 'split-member-address');

        if ($backup === null) {
            CLI::error('Backup failed - aborting without writing anything.');

            return EXIT_ERROR;
        }

        CLI::write('Backup written: ' . $backup, 'green');

        $updated = 0;

        foreach ($updates as $memberId => $address) {
            $db->table('member')
                ->where('memberID', $memberId)
                ->update(['address' => $address === '' ? null : $address]);
            $updated += $db->affectedRows();
        }

        CLI::write("Updated {$updated} member row(s).", 'green');

        return EXIT_SUCCESS;
    }

    /**
     * Drops a trailing ", BARANGAY" (or an address that is nothing but the
     * barangay) when that segment folds to the same barangay the row points at.
     * Returns the address unchanged when it does not, so a street literally
     * named after a barangay survives.
     */
    private static function stripBarangay(string $address, string $barangay): string
    {
        $key = MemberFieldNormalizer::barangayKey($barangay);
        $pos = strrpos($address, ',');

        if ($pos === false) {
            return MemberFieldNormalizer::barangayKey($address) === $key ? '' : $address;
        }

        $tail = trim(substr($address, $pos + 1));

        return MemberFieldNormalizer::barangayKey($tail) === $key
            ? rtrim(substr($address, 0, $pos))
            : $address;
    }
}
