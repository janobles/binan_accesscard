<?php

namespace App\Libraries;

/**
 * Shapes a staged family-import job (the review-phase result_json produced by
 * App\Jobs\FamilyImportJob) into the grouped, edit-ready payload the Import Review
 * screen renders. Errors are bucketed by code (QR problems first, then structure,
 * then per-field), each item carrying the current cell value + a little row context so
 * the operator can fix it inline. Pure presentation — no DB, request, or session.
 */
class ImportReviewPresenter
{
    /**
     * Ordered buckets. QR problems come first (the operator's priority), then family
     * structure, then per-field issues, then warnings, then whole-file problems.
     *
     * @var array<string, array{label: string, hint: string}>
     */
    private const GROUPS = [
        'QR-01'     => ['label' => 'Missing QR Number',        'hint' => 'Every person needs the family QR number.'],
        'QR-FORMAT' => ['label' => 'Invalid QR Number',        'hint' => 'Must be a whole number — no letters, decimals, or commas.'],
        'QR-05'     => ['label' => 'QR Number is zero',         'hint' => 'The QR number must be greater than zero.'],
        'QR-07'     => ['label' => 'QR Number too large',       'hint' => 'The QR number is above the allowed maximum.'],
        'QR-08'     => ['label' => 'QR Number is an error cell', 'hint' => 'The cell holds an Excel error value. Retype the number.'],
        'QR-11'     => ['label' => 'Merged QR cells',           'hint' => 'Unmerge the QR column in Excel and re-upload.'],
        'QR-12'     => ['label' => 'QR Number is a formula',     'hint' => 'Type the number itself, not a formula.'],
        'HEAD-NONE' => ['label' => 'No Head in the family',      'hint' => 'Set Relationship = Head on exactly one person.'],
        'HEAD-MULTI' => ['label' => 'More than one Head',        'hint' => 'Only one person per family can be the Head.'],
        'FP-ADDR'   => ['label' => 'Mixed address in one family', 'hint' => 'One QR number must be one household. Check for a copied block or a shifted row.'],
        'REQUIRED'  => ['label' => 'Missing required field',      'hint' => 'Fill in the required value.'],
        'BDAY'      => ['label' => 'Invalid birthday',           'hint' => 'Use the format MM-DD-YYYY.'],
        'SEX'       => ['label' => 'Invalid sex',                'hint' => 'Use Male or Female.'],
        'INCOME'    => ['label' => 'Invalid monthly income',      'hint' => 'Use a bracket label or a number.'],
        'SERVICE'   => ['label' => 'Unknown service code',        'hint' => 'Use a code from the Reference sheet.'],
        'LENGTH'    => ['label' => 'Value too long',              'hint' => 'Shorten it to fit the database limit.'],
        'ADD-MEMBER' => ['label' => 'Members for an existing family', 'hint' => 'This QR already belongs to a family in the system. Choose Add to append the person to that family, or Remove to skip them.'],
        'BDAY-RANGE' => ['label' => 'Birthday out of range',       'hint' => 'Over 150 years old or in the future — check the year. Imports anyway.'],
        'DUP-PERSON' => ['label' => 'Possible duplicate person',    'hint' => 'Same name, birthday, and address as another row — check it is not a duplicate. Imports anyway.'],
        'BRGY'      => ['label' => 'Barangay not recognised',       'hint' => 'Not one of the official Biñan barangays — check the spelling. Imports as typed.'],
        'CONTACT'   => ['label' => 'Contact number format',         'hint' => 'Should start with 09 and be 11 digits. Imports as typed.'],
        'SUFFIX'    => ['label' => 'Suffix adjusted',               'hint' => 'Changed to the matching dropdown value (Jr, Sr, I–V), or left blank if it matches none.'],
        'DUP-EXISTS' => ['label' => 'Already in the system',       'hint' => 'These families already exist (matched by QR number) and will be skipped if you import.'],
        'QR-CONTIG' => ['label' => 'Family rows not together',     'hint' => 'Warning only — the family imports, but check the grouping.'],
        'EMPTY'     => ['label' => 'Empty file',                 'hint' => 'No family rows were found.'],
        'FILE'      => ['label' => 'File problem',               'hint' => 'The file could not be read. Fix it in Excel and re-upload.'],
    ];

    /** Family-structure errors resolved from a panel of the whole family's rows. */
    private const FAMILY_CODES = ['FP-ADDR', 'HEAD-NONE', 'HEAD-MULTI'];

    /** Relationship dropdown options ("Head" first) for the family-panel picker. */
    private const RELATIONSHIP_OPTIONS = [
        'Head', 'Spouse', 'Child', 'Parent', 'Sibling', 'Grandparent', 'Grandchild', 'In-law', 'Relative', 'Other',
    ];

    /**
     * Builds the review payload from a decoded review-phase result_json.
     *
     * @param array $result job_queue.result_json decoded: {rows, errors, counts, file}
     * @return array{file: string, counts: array, groups: list<array>}
     */
    public function build(array $result, array $pinnedQrs = []): array
    {
        $rows   = is_array($result['rows'] ?? null) ? $result['rows'] : [];
        $errors = is_array($result['errors'] ?? null) ? $result['errors'] : [];
        $counts = is_array($result['counts'] ?? null) ? $result['counts'] : [];

        // Index the current cell values + a name per sheet row, and the rows of each QR
        // group, for prefill + the family-structure panels.
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

        $pinnedSet = [];
        foreach ($pinnedQrs as $pq) {
            $p = trim((string) $pq);
            if ($p !== '') {
                $pinnedSet[$p] = true;
            }
        }

        // Route a pinned family's OWN errors into its "Working on" panel instead of the
        // flat groups, so a family you are editing stays in one stable place and never
        // jumps between an error group and the bottom.
        $buckets      = [];
        $familyStatus = [];   // qr => ['code','message','severity'] (family-level state)
        $fieldIssues  = [];   // qr => list<item>                    (per-field fixes)

        foreach ($errors as $error) {
            $code  = (string) ($error['code'] ?? 'FILE');
            $qr    = trim((string) ($error['familyNo'] ?? ''));
            $field = $error['field'] ?? null;
            $pinnedFamily = $qr !== '' && isset($pinnedSet[$qr]) && isset($byQr[$qr]);

            if ($pinnedFamily && $code !== 'ADD-MEMBER') {
                if (in_array($code, self::FAMILY_CODES, true)) {
                    if (! isset($familyStatus[$qr])) {
                        $familyStatus[$qr] = ['code' => $code, 'message' => (string) ($error['message'] ?? ''), 'severity' => (string) ($error['severity'] ?? 'blocking')];
                    }
                    continue;
                }
                if ($field !== null && $field !== '_decision') {
                    $fieldIssues[$qr][] = $this->item($error, $byRow, $byQr);
                    continue;
                }
            }

            $buckets[$code][] = $this->item($error, $byRow, $byQr);
        }

        $groups = [];
        foreach (array_keys(self::GROUPS) as $code) {
            if (isset($buckets[$code])) {
                $groups[] = $this->group($code, $buckets[$code]);
                unset($buckets[$code]);
            }
        }
        foreach ($buckets as $code => $items) {
            $groups[] = $this->group($code, $items);
        }

        // "Working on" at the top: every family you've touched, in one stable panel that
        // holds its status + all its fixes and updates in place.
        $working = $this->workingGroup($pinnedQrs, $familyStatus, $fieldIssues, $byQr, $byRow);
        if ($working !== null) {
            array_unshift($groups, $working);
        }

        $families = (int) ($counts['families'] ?? 0);
        $existing = (int) ($counts['existing'] ?? 0);

        return [
            'file'   => (string) ($result['file'] ?? 'import.xlsx'),
            'counts' => [
                'families'    => $families,
                'members'     => (int) ($counts['members'] ?? 0),
                // Every individual (heads + members) — what "total members" should show.
                'people'      => (int) ($counts['people'] ?? $families + (int) ($counts['members'] ?? 0)),
                'existing'    => $existing,
                // Families not already on file — how many the import would actually add.
                'newFamilies' => max(0, $families - $existing),
                // Members bound for an already-existing family (append), and how many
                // still need an add/remove decision.
                'appends'         => (int) ($counts['appends'] ?? 0),
                'appendsPending'  => (int) ($counts['appendsPending'] ?? 0),
                'appendsToImport' => (int) ($counts['appendsToImport'] ?? 0),
                'blocking'    => (int) ($counts['blocking'] ?? 0),
                'warnings'    => (int) ($counts['warnings'] ?? 0),
            ],
            'relationshipOptions' => self::RELATIONSHIP_OPTIONS,
            'groups' => $groups,
        ];
    }

    /** One review item: the errored cell + its current value and row context. */
    private function item(array $error, array $byRow, array $byQr): array
    {
        $sheetRow = $error['sheetRow'] ?? null;
        $field    = $error['field'] ?? null;
        $code     = (string) ($error['code'] ?? '');
        $data     = ($sheetRow !== null && isset($byRow[(int) $sheetRow])) ? $byRow[(int) $sheetRow] : [];

        $name = trim((string) ($data['firstname'] ?? '') . ' ' . (string) ($data['lastname'] ?? ''));

        $out = [
            'sheetRow' => $sheetRow,
            'familyNo' => (string) ($error['familyNo'] ?? ''),
            'field'    => $field,
            'message'  => (string) ($error['message'] ?? ''),
            'severity' => (string) ($error['severity'] ?? 'blocking'),
            'value'    => $field !== null ? (string) ($data[$field] ?? '') : '',
            'name'     => $name,
            // Editable only when we can target a specific cell.
            'editable' => $sheetRow !== null && $field !== null,
        ];

        // Family-structure errors (wrong/missing head, split address) are fixed from a
        // panel showing the whole family's rows — each row's QR + relationship editable.
        if (in_array($code, self::FAMILY_CODES, true)) {
            $out['familyRows'] = $this->familyRows((string) ($error['familyNo'] ?? ''), $byQr, $byRow);
        }

        return $out;
    }

    /**
     * Builds the "Working on" group: one editable panel per touched family, carrying its
     * current family-level status and any per-field fixes. Returns null when empty.
     *
     * @param list<int|string>            $pinnedQrs
     * @param array<string, array>        $familyStatus qr => ['code','message','severity']
     * @param array<string, list<array>>  $fieldIssues  qr => field-fix items
     */
    private function workingGroup(array $pinnedQrs, array $familyStatus, array $fieldIssues, array $byQr, array $byRow): ?array
    {
        $items = [];
        $seen  = [];

        foreach ($pinnedQrs as $qr) {
            $qr = trim((string) $qr);

            if ($qr === '' || isset($seen[$qr]) || ! isset($byQr[$qr])) {
                continue;
            }

            $seen[$qr] = true;
            $status = $familyStatus[$qr] ?? null;

            $items[] = [
                'sheetRow'    => null,
                'familyNo'    => $qr,
                'field'       => null,
                'message'     => (string) ($status['message'] ?? ''),
                'severity'    => (string) ($status['severity'] ?? 'ok'),
                'statusCode'  => (string) ($status['code'] ?? ''),
                'value'       => '',
                'name'        => '',
                'editable'    => false,
                'familyRows'  => $this->familyRows($qr, $byQr, $byRow),
                'fieldIssues' => $fieldIssues[$qr] ?? [],
            ];
        }

        if ($items === []) {
            return null;
        }

        return [
            'code'     => 'WORKING',
            'label'    => 'Working on',
            'hint'     => 'Families you are editing — fixes update here in place, nothing jumps around.',
            'severity' => 'ok',
            'count'    => count($items),
            'items'    => $items,
        ];
    }

    /**
     * The rows of one QR group, shaped for the family-fix panel.
     *
     * @return list<array{sheetRow: int, name: string, relationship: string, barangay: string, address: string, qr: string}>
     */
    private function familyRows(string $qr, array $byQr, array $byRow): array
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
}
