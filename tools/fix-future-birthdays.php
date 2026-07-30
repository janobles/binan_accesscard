<?php

/**
 * Rewrites future-dated birthdays in a family-import workbook so the file imports cleanly, while
 * leaving EVERYTHING else byte-for-byte identical — dropdowns, styles, data-validations, layout.
 *
 * A future birthday is rejected by MemberModel's not_future_date rule on write, and one bad member
 * rolls back its whole family (one family = one transaction), so a file full of them silently loses
 * hundreds of families. Each future date is shifted back in 10-year steps until it lands in the
 * past, keeping a plausible age.
 *
 *   php tools/fix-future-birthdays.php excel/in.xlsx excel/out.xlsx
 *
 * HOW (and why it is instant, not the minutes a PhpSpreadsheet load+save takes): an .xlsx is a ZIP
 * of XML. Birthdays are shared strings in xl/sharedStrings.xml, and only birthdays are formatted
 * MM-DD-YYYY (QRs are numbers, contacts have no dashes), so a strict pattern edit of that ONE entry
 * list touches nothing else. We copy the file, patch that single XML member in place, and leave
 * every other part of the archive exactly as it was.
 */

$in  = $argv[1] ?? '';
$out = $argv[2] ?? '';
if ($in === '' || $out === '' || ! is_file($in)) {
    fwrite(STDERR, "Usage: php tools/fix-future-birthdays.php <in.xlsx> <out.xlsx>\n");
    exit(1);
}

$today = new DateTimeImmutable('today');

// Fresh copy so the original is never touched; we edit the copy's ZIP in place.
if (! copy($in, $out)) {
    fwrite(STDERR, "Could not create {$out}.\n");
    exit(1);
}

$zip = new ZipArchive();
if ($zip->open($out) !== true) {
    fwrite(STDERR, "Could not open {$out} as a ZIP.\n");
    exit(1);
}

$member = 'xl/sharedStrings.xml';
$xml    = $zip->getFromName($member);
if ($xml === false) {
    fwrite(STDERR, "No {$member} in the workbook.\n");
    $zip->close();
    exit(1);
}

$fixed = 0;
// Match a MM-DD-YYYY run sitting as element text (between > and <). Only birthdays take this shape.
$patched = preg_replace_callback(
    '/>(\d{2})-(\d{2})-(\d{4})</',
    static function (array $m) use ($today, &$fixed): string {
        [$whole, $mm, $dd, $yyyy] = $m;
        $month = (int) $mm;
        $day   = (int) $dd;
        $year  = (int) $yyyy;

        if (! checkdate($month, $day, $year)) {
            return $whole; // not a real date — leave it for the importer to flag
        }
        if ((new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day))) <= $today) {
            return $whole; // already in the past — nothing to do
        }

        // Shift back a decade at a time until the date is in the past.
        while (true) {
            $year -= 10;
            $safeDay = ($month === 2 && $day === 29 && ! checkdate(2, 29, $year)) ? 28 : $day;
            if ((new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $safeDay))) <= $today) {
                $fixed++;

                return sprintf('>%02d-%02d-%04d<', $month, $safeDay, $year);
            }
        }
    },
    $xml,
);

$zip->deleteName($member);
$zip->addFromString($member, $patched);
$zip->close();

echo "Fixed {$fixed} future-dated birthday string(s) (all shared cells using them).\n";
echo "Wrote {$out}\n";
