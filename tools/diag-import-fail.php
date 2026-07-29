<?php

/**
 * Reproduces a failing family from a workbook and prints the REAL reason a member insert was
 * rejected (which the import job swallows as "One family member could not be saved."). Everything
 * runs inside a rolled-back transaction, so nothing is persisted.
 *
 *   php tools/diag-import-fail.php excel/family-import-A&B-20K-clean.xlsx 4 39 50
 *
 * Extra args are the QR numbers of families to probe (defaults to a few if omitted).
 */

use CodeIgniter\Boot;
use Config\Paths;

ini_set('memory_limit', '2048M');

$root = dirname(__DIR__);
$file = $argv[1] ?? '';
if ($file === '' || ! is_file($file)) {
    $file = $root . DIRECTORY_SEPARATOR . $file;
}
if (! is_file($file)) {
    fwrite(STDERR, "File not found.\n");
    exit(1);
}
$file    = realpath($file);
$wantQrs = array_slice($argv, 2);
if ($wantQrs === []) {
    $wantQrs = ['4', '39', '50'];
}
$wantQrs = array_fill_keys(array_map('strval', $wantQrs), true);

define('FCPATH', $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR);
chdir(FCPATH);
require $root . '/app/Config/Paths.php';
$paths = new Paths();
require $paths->systemDirectory . '/Boot.php';
Boot::bootWorker($paths);

use App\Libraries\FamilyExcelImporter;
use App\Models\Families\MemberModel;
use Config\Database;

$importer = new FamilyExcelImporter();
$staged   = $importer->stage($file);

$families = $staged['families'] ?? [];
echo 'Built families: ' . count($families) . "\n\n";

$memberModel = new MemberModel();
$db          = Database::connect();

$probed = 0;
foreach ($families as $family) {
    $qr = (string) ($family['familyNo'] ?? '');
    if (! isset($wantQrs[$qr])) {
        continue;
    }
    $probed++;
    echo "===== FAMILY QR {$qr} =====\n";

    $db->transBegin();

    $headId = $memberModel->createHead($family['headPayload']);
    if ($headId === false) {
        echo "  HEAD insert FAILED. model errors: " . json_encode($memberModel->errors()) . "\n";
        echo "  db error: " . json_encode($db->error()) . "\n";
        $db->transRollback();
        continue;
    }
    echo "  head OK (id {$headId})\n";

    foreach ($family['memberPayloads'] as $n => $entry) {
        $payload  = $entry['payload'] ?? [];
        $memberId = $memberModel->addFamilyMember($headId, $payload);
        if ($memberId === false) {
            echo "  MEMBER #{$n} insert FAILED\n";
            echo "    model errors: " . json_encode($memberModel->errors()) . "\n";
            echo "    db error:     " . json_encode($db->error()) . "\n";
            echo "    payload:      " . json_encode($payload) . "\n";
        } else {
            echo "  member #{$n} OK (id {$memberId})\n";
        }
    }

    $db->transRollback();
    echo "\n";
}

echo $probed === 0 ? "None of the requested QRs were found in the built families.\n" : "Done (all rolled back).\n";
