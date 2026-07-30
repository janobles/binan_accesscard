<?php

/**
 * Runs a family-import workbook through the REAL importer/validator and prints the outcome —
 * row/family/member counts, blocking + warning totals, and the first few of each. A quick way to
 * confirm a generated test file parses clean (or trips exactly the errors you seeded) without
 * going through the browser upload + background worker.
 *
 *   php tools/validate-import.php excel/family-import-C-10k-clean.xlsx
 *
 * Needs the DB up (the importer checks existing heads / sector / service / barangay).
 */

use CodeIgniter\Boot;
use Config\Paths;

// Full-load + validate of a 10k-row workbook peaks ~100MB+; give the CLI room so the load does
// not OOM (which the importer would otherwise report as a generic "cannot read" file error).
ini_set('memory_limit', '1024M');

$root = dirname(__DIR__);

$file = $argv[1] ?? '';
if ($file === '') {
    fwrite(STDERR, "Usage: php tools/validate-import.php <path-to.xlsx>\n");
    exit(1);
}
if (! is_file($file)) {
    $file = $root . DIRECTORY_SEPARATOR . $file;
}
if (! is_file($file)) {
    fwrite(STDERR, "File not found: {$argv[1]}\n");
    exit(1);
}
// Absolute now — the boot below chdir()s into public/, which would break a relative path.
$file = realpath($file);

define('FCPATH', $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR);
chdir(FCPATH);

require $root . '/app/Config/Paths.php';
$paths = new Paths();
require $paths->systemDirectory . '/Boot.php';
Boot::bootWorker($paths);

use App\Libraries\FamilyExcelImporter;

$start  = microtime(true);
$result = (new FamilyExcelImporter())->stage($file);
$secs   = number_format(microtime(true) - $start, 1);

echo 'File:  ' . $file . "\n";
echo 'Parse: ' . ($result['ok'] ? 'OK' : 'FAILED') . "  ({$secs}s)\n";

$counts = $result['counts'] ?? [];
echo sprintf(
    "Counts: families=%d members=%d blocking=%d warnings=%d  (data rows=%d)\n",
    $counts['families'] ?? 0,
    $counts['members'] ?? 0,
    $counts['blocking'] ?? 0,
    $counts['warnings'] ?? 0,
    count($result['rows'] ?? []),
);

$errors   = $result['errors'] ?? [];
$blocking = array_values(array_filter($errors, static fn (array $e): bool => ($e['severity'] ?? '') === 'blocking'));
$warnings = array_values(array_filter($errors, static fn (array $e): bool => ($e['severity'] ?? '') !== 'blocking'));

$show = static function (string $label, array $list): void {
    echo "\n{$label}: " . count($list) . "\n";
    foreach (array_slice($list, 0, 8) as $e) {
        echo sprintf(
            "  row %-6s %-12s %s\n",
            (string) ($e['sheetRow'] ?? '-'),
            (string) ($e['code'] ?? '-'),
            (string) ($e['message'] ?? ''),
        );
    }
    if (count($list) > 8) {
        echo '  ... and ' . (count($list) - 8) . " more\n";
    }
};

$show('BLOCKING', $blocking);
$show('WARNINGS', $warnings);
