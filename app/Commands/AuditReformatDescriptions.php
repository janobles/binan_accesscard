<?php

namespace App\Commands;

use App\Models\Audit\AuditTrailsModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * One-time backfill: rebuilds audit_trails.full_description into the clean
 * labeled-line narrative the app now stores —
 *
 *   What: <action> — <summary> — <detail>
 *   Who: <username> (<role>, #id)
 *   When: <timestamp>
 *   Where: <ip>
 *   Device: <user agent>
 *
 * The six facets are reconstructed from the row's own columns (userID, dt_created,
 * ip_address, user_agent) plus the "What" clause recovered from whatever the
 * full_description currently holds (an earlier clean WHAT sentence, a legacy
 * "WHO: … · WHAT: …" dump, or — for pre-narrative NULLs — the action/description
 * columns). Formatting is delegated to AuditTrailsModel::assembleNarrative() so
 * historical rows match newly written ones exactly.
 *
 * Deliberately bypasses the append-only AuditTrailsModel write path — an
 * intentional, audited one-time data migration. Idempotent: re-running re-derives
 * the same value and reports zero changes.
 *
 * Usage:
 *   php spark audit:reformat-descriptions            run the backfill
 *   php spark audit:reformat-descriptions --dry-run  preview only, no writes
 */
class AuditReformatDescriptions extends BaseCommand
{
    protected $group       = 'Audit';
    protected $name        = 'audit:reformat-descriptions';
    protected $description = 'Backfill audit_trails.full_description to the clean six-facet labeled-line format.';
    protected $usage       = 'audit:reformat-descriptions [--dry-run]';
    protected $options     = ['--dry-run' => 'Preview changes without writing to the database.'];

    public function run(array $params)
    {
        $dryRun = (bool) CLI::getOption('dry-run');
        $db     = Database::connect();

        if (! $db->tableExists('audit_trails')) {
            CLI::error('Table audit_trails not found.');

            return EXIT_ERROR;
        }

        $model = new AuditTrailsModel();

        $rows = $db->table('audit_trails')
            ->select('auditID, userID, user_action, description, full_description, ip_address, user_agent, dt_created')
            ->get()
            ->getResultArray();

        if ($rows === []) {
            CLI::write('No audit rows to process.', 'yellow');

            return EXIT_SUCCESS;
        }

        $changed = 0;
        $samples = [];

        if (! $dryRun) {
            $db->transBegin();
        }

        foreach ($rows as $row) {
            $old    = (string) ($row['full_description'] ?? '');
            $action = (string) ($row['user_action'] ?? '');

            $what = $this->whatFrom($old, $action, (string) ($row['description'] ?? ''));
            $new  = $model->assembleNarrative(
                $what,
                (int) ($row['userID'] ?? 0),
                $action,
                $row['ip_address'] ?? null,
                $row['user_agent'] ?? null,
                (string) ($row['dt_created'] ?? '')
            );

            if ($new === $old) {
                continue;
            }

            $changed++;

            if (count($samples) < 5) {
                $samples[] = ['id' => $row['auditID'], 'new' => $new];
            }

            if (! $dryRun) {
                $db->table('audit_trails')
                    ->where('auditID', $row['auditID'])
                    ->update(['full_description' => $new]);
            }
        }

        if (! $dryRun) {
            if ($db->transStatus() === false) {
                $db->transRollback();
                CLI::error('Transaction failed — rolled back. No rows changed.');

                return EXIT_ERROR;
            }

            $db->transCommit();
        }

        foreach ($samples as $sample) {
            CLI::write('#' . $sample['id'], 'cyan');
            CLI::write($sample['new'], 'green');
            CLI::newLine();
        }

        CLI::write(sprintf(
            '%s %d of %d audit rows.%s',
            $dryRun ? 'Would reformat' : 'Reformatted',
            $changed,
            count($rows),
            $dryRun ? ' (dry run — no writes)' : ''
        ), 'green');

        return EXIT_SUCCESS;
    }

    /**
     * Recovers the "What" clause from a row's current full_description, whatever
     * shape it is in, so the narrative can be rebuilt without double-wrapping:
     *   - new labeled format  → the value of the leading "What: " line;
     *   - legacy caps dump    → the clause between "WHAT: " and " · WHEN:";
     *   - clean WHAT sentence → the string as-is;
     *   - empty/pre-narrative → rebuilt from the action + description columns.
     */
    private function whatFrom(string $full, string $action, string $description): string
    {
        if (str_starts_with($full, 'What: ')) {
            $firstLine = explode("\n", $full, 2)[0];

            return trim(substr($firstLine, strlen('What: ')));
        }

        $pos = strpos($full, 'WHAT: ');

        if ($pos !== false) {
            $what = substr($full, $pos + strlen('WHAT: '));
            $end  = strpos($what, ' · WHEN:');

            if ($end !== false) {
                $what = substr($what, 0, $end);
            }

            return trim($what);
        }

        if (trim($full) !== '') {
            return trim($full);
        }

        $what    = trim($action);
        $summary = trim($description);

        if ($summary !== '') {
            $what .= ' — ' . $summary;
        }

        return $what;
    }
}
