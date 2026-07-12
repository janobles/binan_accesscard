<?php

namespace App\Libraries;

/**
 * Shapes a staged family-import job (the review-phase result_json produced by
 * App\Jobs\FamilyImportJob) into a READ-ONLY report.
 *
 * Nothing is edited here: the spreadsheet is the single source of truth. If fixes were
 * applied in the browser the file would still hold the mistakes, and the next person to
 * re-use it would import them again. So the report's only job is to make fixing the file
 * effortless — every issue names the EXACT Excel cell (e.g. "H42"), the column, the
 * current value, and what to do. Fix the file, upload it again.
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
        'DUP-EXISTS' => ['label' => 'Already in the system',       'hint' => 'These families already exist (matched by QR) and are SKIPPED on import.'],
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

        return [
            'file'   => (string) ($result['file'] ?? 'import.xlsx'),
            'counts' => [
                'families'    => $families,
                'members'     => (int) ($counts['members'] ?? 0),
                'people'      => (int) ($counts['people'] ?? $families + (int) ($counts['members'] ?? 0)),
                'existing'    => $existing,
                'newFamilies' => max(0, $families - $existing),
                'appends'     => (int) ($counts['appends'] ?? 0),
                'blocking'    => (int) ($counts['blocking'] ?? 0),
                'warnings'    => (int) ($counts['warnings'] ?? 0),
            ],
            'groups'  => $groups,
            'byRow'   => $byRowIdx,
        ];
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
