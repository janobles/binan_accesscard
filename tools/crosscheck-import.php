<?php

/**
 * Non-destructive dup check between two import workbooks. Predicts whether importing them as a
 * contiguous sequence (fileA first, then fileB into the same DB) would trip the importer's
 * person-level duplicate codes — WITHOUT writing anything to the database.
 *
 *   php tools/crosscheck-import.php excel/family-import-20000-members.xlsx excel/family-import-C-10k-clean.xlsx
 *
 * Reports:
 *   - QR overlap        (would cause QR-TAKEN / DUP-DB-by-QR)
 *   - person identity   last|first|birthday collisions, matching FamilyExcelImporter::identityKey
 *     (would cause DUP-PERSON / DUP-DB when the same person is re-entered under a new QR)
 */

require 'vendor/autoload.php';
ini_set('memory_limit', '1024M');

use PhpOffice\PhpSpreadsheet\IOFactory;

$fileA = $argv[1] ?? '';
$fileB = $argv[2] ?? '';
if ($fileA === '' || $fileB === '') {
    fwrite(STDERR, "Usage: php tools/crosscheck-import.php <fileA.xlsx> <fileB.xlsx>\n");
    exit(1);
}

/** Mirrors FamilyExcelImporter::normalizeText(). */
function normText(string $v): string
{
    return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $v)));
}

/**
 * Reads a workbook into [qrs => set, identities => set]. Columns: A=QR, C=LastName, D=FirstName,
 * G=Birthday. Identity mirrors the importer: blank first/last are skipped (they build no key).
 *
 * @return array{qrs: array<string,true>, ids: array<string,true>, rows: int}
 */
function scan(string $file): array
{
    $reader = IOFactory::createReaderForFile($file);
    $reader->setReadDataOnly(true);
    $sheet = $reader->load($file)->getActiveSheet();

    $hdr = 1;
    for ($r = 1; $r <= 15; $r++) {
        if (stripos((string) $sheet->getCell('A' . $r)->getValue(), 'QR') === 0) {
            $hdr = $r;
            break;
        }
    }

    $qrs  = [];
    $ids  = [];
    $rows = 0;
    $max  = $sheet->getHighestRow();

    for ($r = $hdr + 1; $r <= $max; $r++) {
        $qr    = trim((string) $sheet->getCell('A' . $r)->getValue());
        $last  = (string) $sheet->getCell('C' . $r)->getValue();
        $first = (string) $sheet->getCell('D' . $r)->getValue();
        $bday  = trim((string) $sheet->getCell('G' . $r)->getValue());

        if ($qr === '' && trim($last) === '' && trim($first) === '') {
            continue;
        }
        $rows++;

        if ($qr !== '') {
            $qrs[$qr] = true;
        }
        $nf = normText($first);
        $nl = normText($last);
        if ($nf !== '' && $nl !== '') {
            $ids[$nf . '|' . $nl . '|' . $bday] = true;
        }
    }

    return ['qrs' => $qrs, 'ids' => $ids, 'rows' => $rows];
}

$a = scan($fileA);
$b = scan($fileB);

$qrOverlap = array_intersect_key($a['qrs'], $b['qrs']);
$idOverlap = array_intersect_key($a['ids'], $b['ids']);

printf("A: %s\n   rows=%d unique-QR=%d unique-people=%d\n", basename($fileA), $a['rows'], count($a['qrs']), count($a['ids']));
printf("B: %s\n   rows=%d unique-QR=%d unique-people=%d\n\n", basename($fileB), $b['rows'], count($b['qrs']), count($b['ids']));

printf("QR overlap:     %d  %s\n", count($qrOverlap), count($qrOverlap) ? '<< would trip QR-TAKEN / DUP-DB' : 'none — QR ranges disjoint');
printf("Person overlap: %d  %s\n", count($idOverlap), count($idOverlap) ? '<< would trip DUP-PERSON / DUP-DB' : 'none — no shared identities');

foreach ([['QR', $qrOverlap], ['PERSON', $idOverlap]] as [$label, $set]) {
    if ($set) {
        echo "\nfirst {$label} collisions:\n";
        foreach (array_slice(array_keys($set), 0, 10) as $k) {
            echo "  {$k}\n";
        }
    }
}

echo "\nVERDICT: " . ($qrOverlap || $idOverlap
    ? "CONFLICT — sequencing these would raise duplicate errors.\n"
    : "CLEAN — no QR or person overlap; importing A then B raises no cross-file duplicates.\n");
