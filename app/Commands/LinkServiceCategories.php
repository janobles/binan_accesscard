<?php

namespace App\Commands;

use App\Commands\Concerns\DumpsTableBackup;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * One-time resolve of `services.category` (a grouping NAME, copied as free
 * text) into the `services.categoryID` / `services.sectorID` keys that
 * v22-normalize.sql adds.
 *
 * The text pointed at either of two tables: a standalone category row
 * (FA/SWPS/EDA) or a sector, because a sector acts as its own service category
 * once `categories:dedupe-sectors` has run. Both are matched here, category
 * first, and whichever hits gets the id - so a service labelled "Senior
 * Citizen" ends up on sectorID, not stranded.
 *
 * Matching folds case and whitespace only. Anything looser would risk filing a
 * service under a group nobody chose, so unmatched text is reported and left
 * for a human. Those rows block v22-normalize-drop.sql, whose CHECK requires
 * exactly one of the two keys - which is the point: an ungrouped service was
 * never a state anyone intended, the text column just could not say so.
 *
 * Safe to re-run: a row that already carries either key is skipped.
 *
 * Usage:
 *   php spark services:link-categories            back up + resolve
 *   php spark services:link-categories --dry-run  preview, write nothing
 */
class LinkServiceCategories extends BaseCommand
{
    use DumpsTableBackup;

    protected $group       = 'Migration';
    protected $name        = 'services:link-categories';
    protected $description = 'Resolve the services.category text into services.categoryID.';
    protected $usage       = 'services:link-categories [--dry-run]';
    protected $options     = ['--dry-run' => 'Preview the resolve, without backing up or writing.'];

    public function run(array $params)
    {
        $dryRun = (bool) CLI::getOption('dry-run');
        $db     = Database::connect();

        if (! $db->fieldExists('categoryID', 'services')) {
            CLI::error('services.categoryID not found - run sql/patches/v22-normalize.sql first.');

            return EXIT_ERROR;
        }

        if (! $db->fieldExists('category', 'services')) {
            CLI::write('services.category is already gone - nothing to resolve.', 'green');

            return EXIT_SUCCESS;
        }

        // Both the name and the code are accepted on each side: rows written by
        // the lookup CRUD stored the display name, the Excel importer stored
        // the code.
        $categoryIds = self::foldMap(
            $db->table('category')->select('categoryID, code, name')->get()->getResultArray(),
            'categoryID',
            ['name', 'code']
        );

        $sectorIds = self::foldMap(
            $db->table('sector')->select('sectorID, shortcode, name')->get()->getResultArray(),
            'sectorID',
            ['name', 'shortcode']
        );

        $rows = $db->table('services')
            ->select('serviceID, category')
            ->where('categoryID IS NULL', null, false)
            ->where('sectorID IS NULL', null, false)
            ->get()->getResultArray();

        $matches   = []; // serviceID => ['categoryID' => int] | ['sectorID' => int]
        $unmatched = []; // grouping text that resolved to neither table => how many services

        foreach ($rows as $row) {
            $text = self::fold((string) ($row['category'] ?? ''));

            if ($text === '') {
                $unmatched['(blank)'] = ($unmatched['(blank)'] ?? 0) + 1;

                continue;
            }

            if (isset($categoryIds[$text])) {
                $matches[(int) $row['serviceID']] = ['categoryID' => $categoryIds[$text]];

                continue;
            }

            if (isset($sectorIds[$text])) {
                $matches[(int) $row['serviceID']] = ['sectorID' => $sectorIds[$text]];

                continue;
            }

            $unmatched[(string) $row['category']] = ($unmatched[(string) $row['category']] ?? 0) + 1;
        }

        CLI::write('Service rows with neither key set: ' . count($rows), 'cyan');
        CLI::write('Would resolve: ' . count($matches), 'green');

        if ($unmatched !== []) {
            CLI::write('Grouping text matching no category and no sector (fix by hand, then re-run):', 'yellow');

            foreach ($unmatched as $text => $count) {
                CLI::write("  {$text} ({$count} service row(s))");
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

        $backup = $this->backupTable('services', 'link-service-categories');

        if ($backup === null) {
            CLI::error('Backup failed - aborting without writing anything.');

            return EXIT_ERROR;
        }

        CLI::write('Backup written: ' . $backup, 'green');

        $updated = 0;

        foreach ($matches as $serviceId => $key) {
            $db->table('services')
                ->where('serviceID', $serviceId)
                ->where('categoryID IS NULL', null, false)
                ->where('sectorID IS NULL', null, false)
                ->update($key);
            $updated += $db->affectedRows();
        }

        CLI::write("Updated {$updated} service row(s).", 'green');
        CLI::write('Verify, then run sql/patches/v22-normalize-drop.sql to drop services.category.', 'yellow');

        return EXIT_SUCCESS;
    }

    /** Case- and whitespace-insensitive key for matching a category name or code. */
    private static function fold(string $value): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $value)), 'UTF-8');
    }

    /**
     * [folded text => row id] over the given columns of one reference table.
     * A key two different rows both claim is dropped rather than left pointing
     * at whichever row came last: an ambiguous grouping text should end up in
     * the unmatched report for a human to resolve, not silently linked to one
     * of the two. Blank folds are dropped for the same reason.
     *
     * @param list<array<string, mixed>> $rows
     * @param list<string>               $columns
     * @return array<string, int>
     */
    private static function foldMap(array $rows, string $idColumn, array $columns): array
    {
        $map      = [];
        $conflict = [];

        foreach ($rows as $row) {
            $id = (int) $row[$idColumn];

            foreach ($columns as $column) {
                $key = self::fold((string) ($row[$column] ?? ''));

                if ($key === '' || isset($conflict[$key])) {
                    continue;
                }

                if (isset($map[$key]) && $map[$key] !== $id) {
                    $conflict[$key] = true;
                    unset($map[$key]);

                    continue;
                }

                $map[$key] = $id;
            }
        }

        return $map;
    }
}
