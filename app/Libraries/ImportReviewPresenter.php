<?php

namespace App\Libraries;

/**
 * Shapes a staged family-import job (the review-phase result_json produced by
 * App\Jobs\FamilyImportJob) into a report.
 *
 * Every issue names the EXACT Excel cell (e.g. "H42"), the column, the current value, and
 * what to do — so the operator can fix it in the spreadsheet and upload again. A flagged
 * family can also be fixed in place: familiesToFix() drives the per-family Edit/Remove
 * actions on the review screen, which restage the corrected group without a re-upload.
 *
 * Pure presentation — no DB, request, or session.
 */
class ImportReviewPresenter
{
    /**
     * Ordered buckets. Blocking problems first (they stop the import), then the
     * informational ones.
     *
     * @var array<string, array{label: string, hint: string}>
     */
    private const GROUPS = [
        'FILE'       => ['label' => 'File problem',                'hint' => 'The file could not be read. Fix it in Excel and upload again.'],
        'EMPTY'      => ['label' => 'Empty file',                  'hint' => 'No family rows were found.'],
        'QR-11'      => ['label' => 'Merged QR cells',             'hint' => 'Unmerge the QR column and repeat the QR number on every row of the family.'],
        'QR-01'      => ['label' => 'Missing QR Number',           'hint' => 'Every person needs their family QR number.'],
        'QR-FORMAT'  => ['label' => 'Invalid QR Number',           'hint' => 'Must be a whole number — no letters, decimals, or commas.'],
        'QR-05'      => ['label' => 'QR Number is zero',           'hint' => 'The QR number must be greater than zero.'],
        'QR-07'      => ['label' => 'QR Number too large',         'hint' => 'Above the allowed maximum.'],
        'QR-08'      => ['label' => 'QR Number is an error cell',  'hint' => 'The cell holds an Excel error value. Retype the number.'],
        'QR-12'      => ['label' => 'QR Number is a formula',      'hint' => 'Type the number itself, not a formula.'],
        'QR-TAKEN'   => ['label' => 'QR belongs to someone else',  'hint' => 'That QR is already used by a DIFFERENT family in the system. Correct the QR number, or give this family its own.'],
        'HEAD-NONE'  => ['label' => 'No Head in the family',       'hint' => 'Set Relationship = Head on exactly one person.'],
        'HEAD-MULTI' => ['label' => 'More than one Head',          'hint' => 'Only one person per family can be the Head.'],
        'FP-ADDR'    => ['label' => 'Two addresses under one QR',  'hint' => 'One QR = one household. Fix the mistyped QR, or give the other household its own QR.'],
        'REQUIRED'   => ['label' => 'Missing required value',      'hint' => 'Fill in the cell.'],
        'BDAY'       => ['label' => 'Invalid birthday',            'hint' => 'Use the format MM-DD-YYYY.'],
        'SEX'        => ['label' => 'Invalid sex',                 'hint' => 'Use Male or Female.'],
        'INCOME'     => ['label' => 'Invalid monthly income',      'hint' => 'Use a bracket label or a number.'],
        'SERVICE'    => ['label' => 'Unknown service code',        'hint' => 'Use a code from the Reference sheet.'],
        'LENGTH'     => ['label' => 'Value too long',              'hint' => 'Shorten it to fit the database limit.'],
        'ADD-MEMBER' => ['label' => 'Will be added to an existing family', 'hint' => 'The QR already belongs to a family. These people are ADDED to it on import — to skip one, delete the row from the file.'],
        'DUP-EXISTS' => ['label' => 'Already in the system',       'hint' => 'Same QR, same head (name + birthday) as a family already on file. SKIPPED on import.'],
        'DUP-DB'     => ['label' => 'Person already in the system','hint' => 'This person is already on file under another family. A HEAD already on file means the whole group is skipped — check the QR.'],
        'DUP-DIFF'   => ['label' => 'Details differ from the system', 'hint' => 'Same family, but the file disagrees with what is stored. The import skips it, so nothing here is saved — edit the record in Manage Family.'],
        'DUP-PERSON' => ['label' => 'Possible duplicate person',   'hint' => 'Same name, birthday and address as another row. Imports anyway — delete a row if it really is a duplicate.'],
        'BRGY'       => ['label' => 'Barangay not recognised',     'hint' => 'Not an official Biñan barangay. Imports as typed.'],
        'CONTACT'    => ['label' => 'Contact number format',       'hint' => 'Should start with 09 and be 11 digits. Imports as typed.'],
        'SUFFIX'     => ['label' => 'Suffix adjusted',             'hint' => 'Changed to the matching dropdown value, or left blank if it matches none.'],
        'BDAY-RANGE' => ['label' => 'Birthday out of range',       'hint' => 'Over 150 years old or in the future. Imports anyway.'],
        'QR-CONTIG'  => ['label' => 'Family rows not together',    'hint' => 'Warning only — the family imports, but check the grouping.'],
    ];

    /** Family-structure problems: shown with the whole family's rows for context. */
    private const FAMILY_CODES = ['FP-ADDR', 'HEAD-NONE', 'HEAD-MULTI'];

    /** Friendly column names, keyed by the importer's normalized field. */
    private const FIELD_LABELS = [
        'familyno' => 'QR Number', 'relationship' => 'Relationship', 'lastname' => 'LastName',
        'firstname' => 'FirstName', 'middlename' => 'MiddleName', 'suffix' => 'Suffix',
        'birthday' => 'Birthday', 'sex' => 'Sex', 'civilstatus' => 'CivilStatus',
        'contactnumber' => 'ContactNumber', 'religion' => 'Religion', 'education' => 'Education',
        'job' => 'Job', 'monthlyincome' => 'MonthlyIncome', 'address' => 'Address',
        'barangay' => 'Barangay', 'sector' => 'Sector', 'services' => 'Services',
    ];

    /**
     * Builds the read-only report from a decoded review-phase result_json.
     *
     * @param array $result {rows, errors, counts, columns, file}
     */
    public function build(array $result): array
    {
        $rows    = is_array($result['rows'] ?? null) ? $result['rows'] : [];
        $errors  = is_array($result['errors'] ?? null) ? $result['errors'] : [];
        $counts  = is_array($result['counts'] ?? null) ? $result['counts'] : [];
        $columns = is_array($result['columns'] ?? null) ? $result['columns'] : [];

        // Index cell values per sheet row, and the rows of each QR group.
        $byRow = [];
        $byQr  = [];
        foreach ($rows as $entry) {
            $sheetRow = (int) ($entry['sheetRow'] ?? 0);
            $data = is_array($entry['data'] ?? null) ? $entry['data'] : [];
            $byRow[$sheetRow] = $data;
            $qr = trim((string) ($data['familyno'] ?? ''));
            if ($qr !== '') {
                $byQr[$qr][] = $sheetRow;
            }
        }

        $items = [];
        foreach ($errors as $error) {
            $items[] = $this->item($error, $byRow, $byQr, $columns);
        }

        // Two ways to read the same list: grouped by problem type (fix all the bad QRs at
        // once), or straight down the sheet by row (work top-to-bottom in Excel).
        $groups  = $this->groupByCode($items);
        $byRowIdx = $this->orderBySheetRow($items);

        $families = (int) ($counts['families'] ?? 0);
        $existing = (int) ($counts['existing'] ?? 0);
        $ready    = $this->readyFamilies($byQr, $byRow, $errors);

        return [
            'file'   => (string) ($result['file'] ?? 'import.xlsx'),
            'counts' => [
                // What is in the file — every person row and QR group, broken ones included.
                'rows'        => (int) ($counts['rows'] ?? count($rows)),
                'groups'      => (int) ($counts['groups'] ?? $families),
                // What the importer could build (a head-less or bad-QR group builds nothing).
                'families'    => $families,
                'members'     => (int) ($counts['members'] ?? 0),
                'people'      => (int) ($counts['people'] ?? $families + (int) ($counts['members'] ?? 0)),
                'existing'    => $existing,
                'newFamilies' => max(0, $families - $existing),
                'appends'     => (int) ($counts['appends'] ?? 0),
                'blocking'    => (int) ($counts['blocking'] ?? 0),
                'warnings'    => (int) ($counts['warnings'] ?? 0),
                'ready'       => count($ready),
            ],
            'groups'     => $groups,
            'byRow'      => $byRowIdx,
            'ready'      => $ready,
            // The worker's in-review edit history (newest last), so the screen can show what
            // they changed before they commit.
            'changes'    => is_array($result['changes'] ?? null) ? array_values($result['changes']) : [],
            'families'   => $this->familiesToFix($byQr, $byRow, $errors),
            // Rows with a blank QR are never grouped into a family — surfaced so the operator
            // can give them a QR and fix them in place.
            'unassigned' => $this->unassignedRows($rows, $errors),
            // Whole-file problems (unreadable / empty) — nothing to edit; upload a fixed file.
            'fileNotices' => $this->fileNotices($errors),
        ];
    }

    /**
     * One entry per QR group that has any blocking error OR warning — the families the
     * operator can open in the Edit modal (or Remove). Clean, warning-free groups are
     * omitted; they need no attention. Groups with a blank QR are omitted too — with no QR
     * there is nothing to key the in-app fix on (fix those in the file).
     *
     * @param array<string, list<int>>          $byQr   [qr => sheet rows]
     * @param array<int, array<string, string>> $byRow  [sheet row => cell values]
     * @param list<array>                       $errors
     *
     * @return list<array>
     */
    private function familiesToFix(array $byQr, array $byRow, array $errors): array
    {
        // Codes that mean this QR/family (or its people) are already on file.
        $existingCodes = ['DUP-EXISTS' => true, 'DUP-DIFF' => true, 'ADD-MEMBER' => true];

        $blocking = [];
        $warnings = [];
        $types    = [];   // [qr => [code => severity]] — distinct issue kinds per family
        $existing = [];   // [qr => true] — already in the system

        foreach ($errors as $error) {
            $qr = trim((string) ($error['familyNo'] ?? ''));

            if ($qr === '') {
                continue;
            }

            $code = (string) ($error['code'] ?? '');
            $sev  = (($error['severity'] ?? 'blocking') === 'blocking') ? 'blocking' : 'warning';

            if ($sev === 'blocking') {
                $blocking[$qr] = ($blocking[$qr] ?? 0) + 1;
            } else {
                $warnings[$qr] = ($warnings[$qr] ?? 0) + 1;
            }

            // Keep one entry per code, upgrading to blocking if any instance blocks.
            if ($code !== '' && (! isset($types[$qr][$code]) || $sev === 'blocking')) {
                $types[$qr][$code] = $sev;
            }

            if (isset($existingCodes[$code])) {
                $existing[$qr] = true;
            }

            // Only a HEAD already on file marks the whole family as on file (mirrors ready()).
            if ($code === 'DUP-DB' && $this->isHeadRow($byRow[(int) ($error['sheetRow'] ?? 0)] ?? [])) {
                $existing[$qr] = true;
            }
        }

        $out = [];

        foreach ($byQr as $qr => $sheetRows) {
            $qr = (string) $qr;
            $b  = (int) ($blocking[$qr] ?? 0);
            $w  = (int) ($warnings[$qr] ?? 0);

            if ($b === 0 && $w === 0) {
                continue;
            }

            $headRow = null;

            foreach ($sheetRows as $sheetRow) {
                if ($this->isHeadRow($byRow[$sheetRow] ?? [])) {
                    $headRow = $sheetRow;
                    break;
                }
            }

            if ($headRow === null) {
                $headRow = $sheetRows[0] ?? null;
            }

            $head = $headRow !== null ? ($byRow[$headRow] ?? []) : [];

            $out[] = [
                'qr'       => $qr,
                'sheetRow' => $headRow,
                'head'     => trim((string) ($head['firstname'] ?? '') . ' ' . (string) ($head['lastname'] ?? '')),
                'members'  => max(0, count($sheetRows) - 1),
                'blocking' => $b,
                'warnings' => $w,
                'existing' => ! empty($existing[$qr]),
                // Each distinct problem as {label, severity} so the row can list them all.
                'types'    => $this->issueTypes($types[$qr] ?? []),
            ];
        }

        usort($out, static fn (array $a, array $b): int => ((int) ($a['sheetRow'] ?? 0)) <=> ((int) ($b['sheetRow'] ?? 0)));

        return $out;
    }

    /**
     * Turns a [code => severity] map into an ordered list of {code, label, severity}, blocking
     * kinds first, using the same friendly labels as the grouped report.
     *
     * @param array<string, string> $codes
     * @return list<array{code:string, label:string, severity:string}>
     */
    private function issueTypes(array $codes): array
    {
        $out = [];

        foreach ($codes as $code => $severity) {
            $out[] = [
                'code'     => $code,
                'label'    => self::GROUPS[$code]['label'] ?? $code,
                'severity' => $severity,
            ];
        }

        usort($out, static fn (array $a, array $b): int =>
            (($a['severity'] === 'blocking') ? 0 : 1) <=> (($b['severity'] === 'blocking') ? 0 : 1));

        return $out;
    }

    /**
     * Every staged row with a blank QR — the importer never groups these into a family, so
     * they carry no QR to Edit by. Each is listed with its own issue types so the operator can
     * open it, type a QR, and fix it in place (keyed by sheet row, not QR).
     *
     * @param list<array>  $rows
     * @param list<array>  $errors
     * @return list<array{sheetRow:int, person:string, types:list<array>}>
     */
    private function unassignedRows(array $rows, array $errors): array
    {
        $codesByRow = [];

        foreach ($errors as $error) {
            $sheetRow = $error['sheetRow'] ?? null;
            $code     = (string) ($error['code'] ?? '');

            if ($sheetRow === null || $code === '') {
                continue;
            }

            $sev = (($error['severity'] ?? 'blocking') === 'blocking') ? 'blocking' : 'warning';

            if (! isset($codesByRow[(int) $sheetRow][$code]) || $sev === 'blocking') {
                $codesByRow[(int) $sheetRow][$code] = $sev;
            }
        }

        $out = [];

        foreach ($rows as $entry) {
            $data = is_array($entry['data'] ?? null) ? $entry['data'] : [];

            if (trim((string) ($data['familyno'] ?? '')) !== '') {
                continue;
            }

            $sheetRow = (int) ($entry['sheetRow'] ?? 0);

            $out[] = [
                'sheetRow' => $sheetRow,
                'person'   => trim((string) ($data['firstname'] ?? '') . ' ' . (string) ($data['lastname'] ?? '')),
                'types'    => $this->issueTypes($codesByRow[$sheetRow] ?? []),
            ];
        }

        return $out;
    }

    /**
     * Whole-file problems (unreadable / empty) — there is nothing to edit, so these surface as
     * a single page notice rather than an editable row.
     *
     * @param list<array> $errors
     * @return list<string>
     */
    private function fileNotices(array $errors): array
    {
        $out = [];

        foreach ($errors as $error) {
            if (in_array((string) ($error['code'] ?? ''), ['FILE', 'EMPTY'], true)) {
                $out[] = (string) ($error['message'] ?? '');
            }
        }

        return $out;
    }

    /**
     * The families that are CORRECT — what the import will actually create.
     *
     * The rest of this report is nothing but bad news, which leaves the operator no way to
     * see that the other 300 families are fine, or to eyeball a head and address before
     * committing. This is the other half of the picture.
     *
     * A family is ready when nothing in its group blocks, it is not already on file
     * (DUP-EXISTS / a head caught by DUP-DB are SKIPPED, never written), and it is not an
     * add-to-an-existing-family group (those are listed under ADD-MEMBER). Warning-only
     * families ARE ready — they import as typed — but their warning count rides along so
     * nothing is quietly glossed over.
     *
     * @param array<string, list<int>>          $byQr   [qr => sheet rows]
     * @param array<int, array<string, string>> $byRow  [sheet row => cell values]
     * @param list<array>                       $errors
     *
     * @return list<array>
     */
    private function readyFamilies(array $byQr, array $byRow, array $errors): array
    {
        $blocked  = [];
        $skipped  = [];
        $appended = [];
        $warnings = [];

        foreach ($errors as $error) {
            $qr = trim((string) ($error['familyNo'] ?? ''));

            if ($qr === '') {
                continue;
            }

            $code = (string) ($error['code'] ?? '');

            if (($error['severity'] ?? 'blocking') === 'blocking') {
                $blocked[$qr] = true;
            } else {
                $warnings[$qr] = ($warnings[$qr] ?? 0) + 1;
            }

            if ($code === 'DUP-EXISTS') {
                $skipped[$qr] = true;
            }

            if ($code === 'ADD-MEMBER') {
                $appended[$qr] = true;
            }

            // Only a HEAD already on file skips the family; a member just gets a warning.
            if ($code === 'DUP-DB' && $this->isHeadRow($byRow[(int) ($error['sheetRow'] ?? 0)] ?? [])) {
                $skipped[$qr] = true;
            }
        }

        $out = [];

        foreach ($byQr as $qr => $sheetRows) {
            $qr = (string) $qr;

            if (isset($blocked[$qr]) || isset($skipped[$qr]) || isset($appended[$qr])) {
                continue;
            }

            $headRow = null;

            foreach ($sheetRows as $sheetRow) {
                if ($this->isHeadRow($byRow[$sheetRow] ?? [])) {
                    $headRow = $sheetRow;
                    break;
                }
            }

            // No head = HEAD-NONE, which blocks; it can't reach here. Guard anyway.
            if ($headRow === null) {
                continue;
            }

            $head = $byRow[$headRow] ?? [];

            $out[] = [
                'qr'       => $qr,
                'sheetRow' => $headRow,
                'head'     => trim((string) ($head['firstname'] ?? '') . ' ' . (string) ($head['lastname'] ?? '')),
                'members'  => max(0, count($sheetRows) - 1),
                'barangay' => (string) ($head['barangay'] ?? ''),
                'address'  => (string) ($head['address'] ?? ''),
                'warnings' => (int) ($warnings[$qr] ?? 0),
            ];
        }

        usort($out, static fn (array $a, array $b): int => $a['sheetRow'] <=> $b['sheetRow']);

        return $out;
    }

    /** @param array<string, string> $data */
    private function isHeadRow(array $data): bool
    {
        return strcasecmp(trim((string) ($data['relationship'] ?? '')), 'Head') === 0;
    }

    /** One report line: the exact cell, what's wrong, and the value that's there now. */
    private function item(array $error, array $byRow, array $byQr, array $columns): array
    {
        $sheetRow = $error['sheetRow'] ?? null;
        $field    = $error['field'] ?? null;
        $code     = (string) ($error['code'] ?? '');
        $data     = ($sheetRow !== null && isset($byRow[(int) $sheetRow])) ? $byRow[(int) $sheetRow] : [];

        // The literal Excel cell, e.g. "H42" — paste into Excel's Go To (Ctrl+G).
        $letter = ($field !== null && isset($columns[$field])) ? (string) $columns[$field] : '';
        $cell   = ($letter !== '' && $sheetRow !== null) ? $letter . $sheetRow : '';

        $out = [
            'code'     => $code,
            'sheetRow' => $sheetRow,
            'familyNo' => (string) ($error['familyNo'] ?? ''),
            'cell'     => $cell,
            'column'   => $field !== null ? (self::FIELD_LABELS[$field] ?? $field) : '',
            'value'    => $field !== null ? (string) ($data[$field] ?? '') : '',
            'name'     => trim((string) ($data['firstname'] ?? '') . ' ' . (string) ($data['lastname'] ?? '')),
            'message'  => (string) ($error['message'] ?? ''),
            'severity' => (string) ($error['severity'] ?? 'blocking'),
        ];

        // Family-structure problems only make sense with the whole family in view.
        if (in_array($code, self::FAMILY_CODES, true)) {
            $out['familyRows'] = $this->familyRows((string) ($error['familyNo'] ?? ''), $byQr, $byRow, $columns);
        }

        return $out;
    }

    /** @param list<array> $items */
    private function groupByCode(array $items): array
    {
        $buckets = [];
        foreach ($items as $item) {
            $buckets[$item['code']][] = $item;
        }

        $groups = [];
        foreach (array_keys(self::GROUPS) as $code) {
            if (isset($buckets[$code])) {
                $groups[] = $this->group($code, $buckets[$code]);
                unset($buckets[$code]);
            }
        }
        foreach ($buckets as $code => $list) {
            $groups[] = $this->group($code, $list);
        }

        return $groups;
    }

    /**
     * The same issues ordered by sheet row, so the operator can walk straight down their
     * spreadsheet. Rows with several problems are listed together.
     *
     * @param list<array> $items
     */
    private function orderBySheetRow(array $items): array
    {
        $byRow = [];
        foreach ($items as $item) {
            $key = $item['sheetRow'] ?? 0;
            $byRow[$key][] = $item;
        }
        ksort($byRow);

        $out = [];
        foreach ($byRow as $sheetRow => $list) {
            $out[] = [
                'sheetRow' => $sheetRow > 0 ? (int) $sheetRow : null,
                'familyNo' => (string) ($list[0]['familyNo'] ?? ''),
                'name'     => (string) ($list[0]['name'] ?? ''),
                'issues'   => $list,
            ];
        }

        return $out;
    }

    /** @param list<array> $items */
    private function group(string $code, array $items): array
    {
        $meta = self::GROUPS[$code] ?? ['label' => $code, 'hint' => ''];

        return [
            'code'     => $code,
            'label'    => $meta['label'],
            'hint'     => $meta['hint'],
            'severity' => $items[0]['severity'] ?? 'blocking',
            'count'    => count($items),
            'items'    => $items,
        ];
    }

    /**
     * The rows of one QR group, for read-only family context.
     *
     * @return list<array>
     */
    private function familyRows(string $qr, array $byQr, array $byRow, array $columns): array
    {
        $out = [];

        foreach (($byQr[$qr] ?? []) as $sheetRow) {
            $data = $byRow[$sheetRow] ?? [];
            $out[] = [
                'sheetRow'     => (int) $sheetRow,
                'name'         => trim((string) ($data['firstname'] ?? '') . ' ' . (string) ($data['lastname'] ?? '')),
                'relationship' => (string) ($data['relationship'] ?? ''),
                'barangay'     => (string) ($data['barangay'] ?? ''),
                'address'      => (string) ($data['address'] ?? ''),
                'qr'           => (string) ($data['familyno'] ?? ''),
                // The cells most likely to need the fix.
                'qrCell'       => isset($columns['familyno']) ? $columns['familyno'] . $sheetRow : '',
                'relCell'      => isset($columns['relationship']) ? $columns['relationship'] . $sheetRow : '',
            ];
        }

        return $out;
    }
}
