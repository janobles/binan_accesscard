<?php

namespace App\Commands;

use App\Models\Scanner\DistributionBatchModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * One-time backfill for batches opened before the eligibility feature. A V19
 * batch has no `batch_eligibility` rows and an `eligible_count` of 0, so the
 * dashboard correctly but uselessly reports "This batch has no eligible
 * families" for every batch that predates sql/patches/v20-eligibility.sql.
 *
 * Runs DistributionBatchModel::rebuildRoster(), the same path an admin gets
 * when profiling data changes after a batch opens, so a backfilled roster is
 * byte-identical to one the batch would have frozen at open time. A legacy
 * batch also has no stored barangay or sector filters, which filtersFor()
 * reports as empty arrays, so its roster comes out citywide and all-sectors -
 * the widest reading, and the only honest one when nobody recorded a narrower
 * intent.
 *
 * By default it only touches batches whose roster is empty, because a batch
 * that already has one holds a coverage figure that may have been printed and
 * signed. `--batch ID` overrides that for a single batch, on purpose.
 *
 * Usage (spark reads an option value as the next argument, not after an `=`):
 *   php spark distribution:backfill-rosters             fill every empty roster
 *   php spark distribution:backfill-rosters --dry-run   preview, write nothing
 *   php spark distribution:backfill-rosters --batch 3   rebuild batch 3, empty or not
 */
class BackfillBatchRosters extends BaseCommand
{
    protected $group       = 'Migration';
    protected $name        = 'distribution:backfill-rosters';
    protected $description = 'Freeze an eligibility roster for batches opened before the V20 eligibility patch.';
    protected $usage       = 'distribution:backfill-rosters [--dry-run] [--batch ID]';
    protected $options     = [
        '--dry-run' => 'List the batches that would be rebuilt, without writing.',
        '--batch'   => 'Rebuild this batch only, even if it already has a roster.',
    ];

    public function run(array $params)
    {
        $dryRun    = (bool) CLI::getOption('dry-run');
        $requested = (int) CLI::getOption('batch');
        $db        = Database::connect();

        foreach (['distribution_batch', 'batch_eligibility'] as $table) {
            if (! $db->tableExists($table)) {
                CLI::error("Table {$table} not found - apply sql/patches/v20-eligibility.sql first.");

                return EXIT_ERROR;
            }
        }

        $batchModel = new DistributionBatchModel();
        $targets    = $this->targets($db, $batchModel, $requested);

        if ($targets === []) {
            CLI::write($requested > 0
                ? "Batch {$requested} not found."
                : 'Every batch already has a roster - nothing to do.', 'green');

            return $requested > 0 ? EXIT_ERROR : EXIT_SUCCESS;
        }

        foreach ($targets as $batch) {
            CLI::write('Batch ' . $batch['batch_id'] . ' "' . $batch['name'] . '"'
                . ' (roster rows: ' . $batch['roster'] . ', eligible_count: ' . $batch['eligible_count'] . ')', 'cyan');
        }

        if ($dryRun) {
            CLI::write('Dry run - nothing written.', 'yellow');

            return EXIT_SUCCESS;
        }

        $failed = 0;

        foreach ($targets as $batch) {
            $eligible = $batchModel->rebuildRoster((int) $batch['batch_id']);

            if ($eligible === 0) {
                // Zero is ambiguous: rebuildRoster() reports a write failure and
                // a genuinely empty roster the same way. Say so rather than
                // print a success line the operator cannot trust.
                CLI::write('  Batch ' . $batch['batch_id'] . ': 0 eligible families.'
                    . ' Either no family head matches its filters, or the write failed.', 'yellow');
                $failed++;

                continue;
            }

            CLI::write('  Batch ' . $batch['batch_id'] . ': ' . $eligible . ' eligible families.', 'green');
        }

        CLI::write('Rebuilt ' . (count($targets) - $failed) . ' of ' . count($targets) . ' batch roster(s).',
            $failed > 0 ? 'yellow' : 'green');

        return EXIT_SUCCESS;
    }

    /**
     * The batches this run will rebuild, each carrying its current roster size
     * so the operator sees what is being replaced. With no --batch, this is
     * every batch holding zero roster rows.
     *
     * @return list<array{batch_id:int,name:string,eligible_count:int,roster:int}>
     */
    private function targets(
        \CodeIgniter\Database\BaseConnection $db,
        DistributionBatchModel $batchModel,
        int $requested
    ): array {
        $batches = $requested > 0
            ? array_filter([$batchModel->find($requested)])
            : $batchModel->orderBy('batch_id', 'ASC')->findAll();

        $out = [];

        foreach ($batches as $batch) {
            $batchId = (int) $batch['batch_id'];
            $roster  = $db->table('batch_eligibility')->where('batch_id', $batchId)->countAllResults();

            if ($requested === 0 && $roster > 0) {
                continue;
            }

            $out[] = [
                'batch_id'       => $batchId,
                'name'           => (string) $batch['name'],
                'eligible_count' => (int) $batch['eligible_count'],
                'roster'         => $roster,
            ];
        }

        return $out;
    }
}
