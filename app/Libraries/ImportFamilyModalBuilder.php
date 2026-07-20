<?php

namespace App\Libraries;

use App\Models\Families\FamilyFormOptionsModel;
use App\Models\Lookups\SectorModel;
use App\Models\Lookups\ServiceModel;
use DateTime;

/**
 * Bridges the Excel-import staging rows and the shared Add/Update family modal so an
 * operator can FIX a flagged family in the browser instead of editing the .xlsx and
 * re-uploading.
 *
 * Two directions:
 *   - viewData():     staged rows (one sheet row per person) -> the exact view variables
 *                     Family/family-modal.php expects, prefilled and with the right
 *                     sectors/services pre-checked.
 *   - toStagedRows(): the modal's POST -> raw staged rows the importer re-validates,
 *                     reversing the field/code mapping viewData() applied.
 *
 * The modal reuses store()/update()'s field names, so nothing about the form changes —
 * only its `action` (a staging-save endpoint) and its prefill source differ.
 */
class ImportFamilyModalBuilder
{
    /** Friendly column names, keyed by the importer's normalized field (mirrors the review). */
    private const FIELD_LABELS = [
        'familyno' => 'QR Number', 'relationship' => 'Relationship', 'lastname' => 'Last Name',
        'firstname' => 'First Name', 'middlename' => 'Middle Name', 'suffix' => 'Suffix',
        'birthday' => 'Birthday', 'sex' => 'Sex', 'civilstatus' => 'Civil Status',
        'contactnumber' => 'Contact Number', 'religion' => 'Religion', 'education' => 'Education',
        'job' => 'Job', 'monthlyincome' => 'Monthly Income', 'address' => 'Address',
        'barangay' => 'Barangay', 'sector' => 'Sector', 'services' => 'Services',
    ];

    /** [id => SHORTCODE] and [SHORTCODE => id] for active sectors, built once per instance. */
    private ?array $sectorIdToCode = null;
    private ?array $sectorCodeToId = null;
    private ?array $serviceIdToCode = null;
    private ?array $serviceCodeToId = null;

    /** [lowercased bracket label => stored value] plus the set of valid values. */
    private ?array $incomeByLabel = null;
    private ?array $incomeValues  = null;

    /**
     * Builds the family-modal view data for one QR group in the staged bundle.
     *
     * @param array  $bundle    the staged review bundle (rows, columns, ...)
     * @param string $familyNo  the QR group to edit
     * @param string $action    the staging-save URL the form posts to
     */
    public function viewData(array $bundle, string $familyNo, string $action): array
    {
        $rows = $this->familyRows($bundle, $familyNo);

        [$headRow, $memberRows] = $this->splitHeadAndMembers($rows);

        $headData = is_array($headRow['data'] ?? null) ? $headRow['data'] : [];

        $headSectorIds  = $this->sectorIds((string) ($headData['sector'] ?? ''));
        $headServiceIds = $this->serviceIds((string) ($headData['services'] ?? ''));

        $existingMembers = [];
        $assignedSectorIds  = $headSectorIds;
        $assignedServiceIds = $headServiceIds;

        foreach ($memberRows as $memberRow) {
            $data = is_array($memberRow['data'] ?? null) ? $memberRow['data'] : [];
            $sectorIds  = $this->sectorIds((string) ($data['sector'] ?? ''));
            $serviceIds = $this->serviceIds((string) ($data['services'] ?? ''));
            $assignedSectorIds  = array_merge($assignedSectorIds, $sectorIds);
            $assignedServiceIds = array_merge($assignedServiceIds, $serviceIds);

            $existingMembers[] = [
                'lastname'      => (string) ($data['lastname'] ?? ''),
                'firstname'     => (string) ($data['firstname'] ?? ''),
                'middlename'    => (string) ($data['middlename'] ?? ''),
                'suffix'        => (string) ($data['suffix'] ?? ''),
                'birthday'      => $this->toDateInput((string) ($data['birthday'] ?? '')),
                'sex'           => (string) ($data['sex'] ?? ''),
                'civilstatus'   => $this->fullFromCode((string) ($data['civilstatus'] ?? ''), FamilyExcelTemplate::CIVIL_STATUS_CODES),
                'contactnumber' => (string) ($data['contactnumber'] ?? ''),
                'religion'      => (string) ($data['religion'] ?? ''),
                'education'     => $this->fullFromCode((string) ($data['education'] ?? ''), FamilyExcelTemplate::EDUCATION_CODES),
                'job'           => (string) ($data['job'] ?? ''),
                'salary'        => $this->incomeValue((string) ($data['monthlyincome'] ?? '')),
                'relationship'  => (string) ($data['relationship'] ?? ''),
                'sector_ids'    => $sectorIds,
                'service_ids'   => $serviceIds,
            ];
        }

        $formValues = [
            'head_lastname'      => (string) ($headData['lastname'] ?? ''),
            'head_firstname'     => (string) ($headData['firstname'] ?? ''),
            'head_middlename'    => (string) ($headData['middlename'] ?? ''),
            'head_suffix'        => (string) ($headData['suffix'] ?? ''),
            'head_birthday'      => $this->toDateInput((string) ($headData['birthday'] ?? '')),
            'head_sex'           => (string) ($headData['sex'] ?? ''),
            'head_civilstatus'   => $this->fullFromCode((string) ($headData['civilstatus'] ?? ''), FamilyExcelTemplate::CIVIL_STATUS_CODES),
            'head_contactnumber' => (string) ($headData['contactnumber'] ?? ''),
            'head_religion'      => (string) ($headData['religion'] ?? ''),
            'head_education'     => $this->fullFromCode((string) ($headData['education'] ?? ''), FamilyExcelTemplate::EDUCATION_CODES),
            'head_job'           => (string) ($headData['job'] ?? ''),
            'head_salary'        => $this->incomeValue((string) ($headData['monthlyincome'] ?? '')),
            'head_address'       => (string) ($headData['address'] ?? ''),
            'head_barangay'      => (string) ($headData['barangay'] ?? ''),
            // The QR the operator is fixing — editable (it may be the very thing that's wrong).
            'qr_control_no'      => (string) ($headData['familyno'] ?? $familyNo),
        ];

        $options = (new FamilyFormOptionsModel())->getViewDataForEdit(
            array_values(array_unique(array_map('intval', $assignedSectorIds))),
            array_values(array_unique(array_map('intval', $assignedServiceIds)))
        );

        return array_merge($options, [
            'action'             => $action,
            'fieldPrefix'        => 'family-import',
            'modalTitle'         => 'Fix Family ' . $familyNo,
            'modalMode'          => 'update',
            'submitLabel'        => 'Save fixes',
            'headId'             => 0,
            'saveDisabled'       => false,
            'qrLocked'           => false,
            'formValues'         => $formValues,
            'selectedSectorIds'  => $headSectorIds,
            'selectedServiceIds' => $headServiceIds,
            'existingMembers'    => $existingMembers,
            // Rendered as a hidden field so the save endpoint knows which group to replace.
            'importFamilyNo'     => $familyNo,
            // The staged errors/warnings for this group, shown inside the modal so the worker
            // sees exactly what to fix.
            'importIssues'       => $this->issues($bundle, $familyNo),
            // Per-field errors keyed to the exact input name, so the modal can highlight the
            // box and show the message beneath it.
            'importFieldIssues'  => $this->fieldIssues($bundle, $familyNo),
        ]);
    }

    /**
     * Resolves each of a group's field errors to the exact modal input name (head_*,
     * members[k][*], or qr_control_no) so the form can flag the box. Errors that have no
     * single input in the modal (sector/service checkbox groups, a member's address, or
     * structural codes with no field) are left to the summary panel. The member index
     * matches the rendered members[] order (head split out exactly as viewData does).
     *
     * @return list<array{name:string, severity:string, message:string}>
     */
    public function fieldIssues(array $bundle, string $familyNo): array
    {
        [$headRow, $memberRows] = $this->splitHeadAndMembers($this->familyRows($bundle, $familyNo));

        $errors = is_array($bundle['errors'] ?? null) ? $bundle['errors'] : [];

        $targetBySheetRow = [(int) ($headRow['sheetRow'] ?? -1) => 'head'];

        foreach ($memberRows as $index => $memberRow) {
            $targetBySheetRow[(int) ($memberRow['sheetRow'] ?? -1)] = (string) $index;
        }

        $out = [];

        foreach ($errors as $error) {
            if (trim((string) ($error['familyNo'] ?? '')) !== $familyNo) {
                continue;
            }

            $field    = (string) ($error['field'] ?? '');
            $sheetRow = $error['sheetRow'] ?? null;

            if ($field === '' || $sheetRow === null || ! array_key_exists((int) $sheetRow, $targetBySheetRow)) {
                continue;
            }

            $name = $this->inputName($targetBySheetRow[(int) $sheetRow], $field);

            if ($name === null) {
                continue;
            }

            $out[] = [
                'name'     => $name,
                'severity' => (string) ($error['severity'] ?? 'blocking'),
                'message'  => (string) ($error['message'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * The modal input name for a normalized importer field on the head ('head') or a member
     * (its index as a string), or null when the modal has no single input for it.
     */
    private function inputName(string $target, string $field): ?string
    {
        // Normalized importer field => the modal's field suffix (monthlyincome -> salary).
        $scalar = [
            'lastname' => 'lastname', 'firstname' => 'firstname', 'middlename' => 'middlename',
            'suffix' => 'suffix', 'birthday' => 'birthday', 'sex' => 'sex',
            'civilstatus' => 'civilstatus', 'contactnumber' => 'contactnumber',
            'religion' => 'religion', 'education' => 'education', 'job' => 'job',
            'monthlyincome' => 'salary',
        ];

        if ($target === 'head') {
            if ($field === 'familyno') {
                return 'qr_control_no';
            }

            if ($field === 'address' || $field === 'barangay') {
                return 'head_' . $field;
            }

            return isset($scalar[$field]) ? 'head_' . $scalar[$field] : null;
        }

        $index = (int) $target;

        if ($field === 'relationship') {
            return 'members[' . $index . '][relationship]';
        }

        return isset($scalar[$field]) ? 'members[' . $index . '][' . $scalar[$field] . ']' : null;
    }

    /**
     * The staged errors + warnings for one QR group, shaped for the modal's issue panel
     * (blocking first). Each entry carries the person's name (when the error is tied to a
     * row), the friendly column, the message, and the severity.
     *
     * @return list<array{severity:string, person:string, column:string, message:string}>
     */
    public function issues(array $bundle, string $familyNo): array
    {
        $errors = is_array($bundle['errors'] ?? null) ? $bundle['errors'] : [];
        $rows   = is_array($bundle['rows'] ?? null) ? $bundle['rows'] : [];

        $byRow = [];
        foreach ($rows as $row) {
            $byRow[(int) ($row['sheetRow'] ?? 0)] = is_array($row['data'] ?? null) ? $row['data'] : [];
        }

        $out = [];

        foreach ($errors as $error) {
            if (trim((string) ($error['familyNo'] ?? '')) !== $familyNo) {
                continue;
            }

            $sheetRow = $error['sheetRow'] ?? null;
            $data     = ($sheetRow !== null && isset($byRow[(int) $sheetRow])) ? $byRow[(int) $sheetRow] : [];
            $field    = $error['field'] ?? null;

            $out[] = [
                'severity' => (string) ($error['severity'] ?? 'blocking'),
                'person'   => trim((string) ($data['firstname'] ?? '') . ' ' . (string) ($data['lastname'] ?? '')),
                'column'   => ($field !== null) ? (self::FIELD_LABELS[$field] ?? (string) $field) : '',
                'message'  => (string) ($error['message'] ?? ''),
            ];
        }

        // Blocking issues first — those are what stops the import.
        usort($out, static fn (array $a, array $b): int =>
            (($a['severity'] === 'blocking') ? 0 : 1) <=> (($b['severity'] === 'blocking') ? 0 : 1));

        return $out;
    }

    /**
     * Turns the modal POST back into raw staged rows (one per person) for the QR group,
     * reversing viewData()'s field/code mapping. The rows reuse the group's existing sheet
     * numbers where possible so the report's ordering stays stable; extra members get fresh
     * numbers past the current maximum.
     *
     * @param array  $post           the submitted form data ($request->getPost())
     * @param array  $bundle         the current staged bundle (for sheet-row allocation)
     * @param string $oldFamilyNo    the QR group being replaced
     * @return list<array{sheetRow:int, data:array<string,string>}>
     */
    public function toStagedRows(array $post, array $bundle, string $oldFamilyNo): array
    {
        $qr = trim((string) ($post['qr_control_no'] ?? $oldFamilyNo));

        $people = [];

        // Head row.
        $people[] = $this->personData($qr, 'Head', [
            'lastname'      => $post['head_lastname']      ?? '',
            'firstname'     => $post['head_firstname']     ?? '',
            'middlename'    => $post['head_middlename']    ?? '',
            'suffix'        => $post['head_suffix']        ?? '',
            'birthday'      => $post['head_birthday']      ?? '',
            'sex'           => $post['head_sex']           ?? '',
            'civilstatus'   => $post['head_civilstatus']   ?? '',
            'contactnumber' => $post['head_contactnumber'] ?? '',
            'religion'      => $post['head_religion']      ?? '',
            'education'     => $post['head_education']      ?? '',
            'job'           => $post['head_job']           ?? '',
            'salary'        => $post['head_salary']        ?? '',
            'address'       => $post['head_address']       ?? '',
            'barangay'      => $post['head_barangay']      ?? '',
            'sector_ids'    => $post['sector_ids']         ?? [],
            'service_ids'   => $post['service_ids']        ?? [],
        ]);

        foreach ($this->postMembers($post) as $member) {
            $people[] = $this->personData($qr, (string) ($member['relationship'] ?? ''), $member);
        }

        return $this->assignSheetRows($people, $bundle, $oldFamilyNo);
    }

    // -- prefill helpers -------------------------------------------------------

    /** @return list<array{sheetRow:int, data:array<string,string>}> */
    private function familyRows(array $bundle, string $familyNo): array
    {
        $rows = is_array($bundle['rows'] ?? null) ? $bundle['rows'] : [];
        $out  = [];

        foreach ($rows as $row) {
            $data = is_array($row['data'] ?? null) ? $row['data'] : [];

            if (trim((string) ($data['familyno'] ?? '')) === $familyNo) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /**
     * The first Head row (else the first row promoted to head, resolving HEAD-NONE) and the
     * remaining rows as members (an extra head becomes a member, resolving HEAD-MULTI).
     *
     * @param list<array> $rows
     * @return array{0: array, 1: list<array>}
     */
    private function splitHeadAndMembers(array $rows): array
    {
        $headIndex = null;

        foreach ($rows as $i => $row) {
            $relationship = trim((string) (($row['data'] ?? [])['relationship'] ?? ''));

            if (strcasecmp($relationship, 'Head') === 0) {
                $headIndex = $i;
                break;
            }
        }

        if ($headIndex === null) {
            $headIndex = 0;
        }

        $head    = $rows[$headIndex] ?? ['sheetRow' => 0, 'data' => []];
        $members = [];

        foreach ($rows as $i => $row) {
            if ($i !== $headIndex) {
                $members[] = $row;
            }
        }

        return [$head, $members];
    }

    /** Comma-separated sector shortcodes -> string sector IDs (unknown -> the OTHER sector). */
    private function sectorIds(string $cell): array
    {
        $codes = $this->splitCodes($cell);

        if ($codes === []) {
            return [];
        }

        $this->loadSectorMaps();
        $ids = [];

        foreach ($codes as $code) {
            if (isset($this->sectorCodeToId[$code])) {
                $ids[] = (string) $this->sectorCodeToId[$code];
            } elseif (isset($this->sectorCodeToId['OTHER'])) {
                $ids[] = (string) $this->sectorCodeToId['OTHER'];
            }
        }

        return array_values(array_unique($ids));
    }

    /** Comma-separated service shortcodes -> string service IDs (unknown codes are dropped). */
    private function serviceIds(string $cell): array
    {
        $codes = $this->splitCodes($cell);

        if ($codes === []) {
            return [];
        }

        $this->loadServiceMaps();
        $ids = [];

        foreach ($codes as $code) {
            if (isset($this->serviceCodeToId[$code])) {
                $ids[] = (string) $this->serviceCodeToId[$code];
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * A code / "CODE - Name" / full value -> the full stored value (mirrors the importer's
     * fullValueFromCode so the modal's select pre-selects correctly).
     *
     * @param array<string,string> $codeMap
     */
    private function fullFromCode(string $value, array $codeMap): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $token = str_contains($value, ' - ') ? trim(explode(' - ', $value)[0]) : $value;

        foreach ($codeMap as $code => $full) {
            if (strcasecmp($token, (string) $code) === 0) {
                return (string) $full;
            }
        }

        return $value;
    }

    /** A bracket label or numeric income -> the modal's stored option value, else '' (repick). */
    private function incomeValue(string $raw): string
    {
        $raw = trim($raw);

        if ($raw === '') {
            return '';
        }

        $this->loadIncomeMap();
        $key = strtolower($raw);

        if (isset($this->incomeByLabel[$key])) {
            return (string) $this->incomeByLabel[$key];
        }

        if (in_array($raw, $this->incomeValues, true)) {
            return $raw;
        }

        return '';
    }

    /** Any recognised date string -> "Y-m-d" for the modal's date input, else '' (repick). */
    private function toDateInput(string $raw): string
    {
        $raw = trim($raw);

        if ($raw === '') {
            return '';
        }

        foreach (['m-d-Y', 'Y-m-d', 'm/d/Y', 'n/j/Y'] as $format) {
            $date = DateTime::createFromFormat('!' . $format, $raw);

            if ($date instanceof DateTime && $date->format($format) === $raw) {
                return $date->format('Y-m-d');
            }
        }

        return '';
    }

    // -- reverse (POST -> rows) helpers ----------------------------------------

    /**
     * One raw person row from posted field values, reversing viewData()'s mapping.
     *
     * @param array<string,mixed> $fields
     * @return array<string,string>
     */
    private function personData(string $qr, string $relationship, array $fields): array
    {
        return [
            'familyno'      => $qr,
            'relationship'  => trim($relationship),
            'lastname'      => trim((string) ($fields['lastname'] ?? '')),
            'firstname'     => trim((string) ($fields['firstname'] ?? '')),
            'middlename'    => trim((string) ($fields['middlename'] ?? '')),
            'suffix'        => trim((string) ($fields['suffix'] ?? '')),
            'birthday'      => trim((string) ($fields['birthday'] ?? '')),
            'sex'           => trim((string) ($fields['sex'] ?? '')),
            // Full stored value; the importer accepts it unchanged.
            'civilstatus'   => trim((string) ($fields['civilstatus'] ?? '')),
            'contactnumber' => trim((string) ($fields['contactnumber'] ?? '')),
            'religion'      => trim((string) ($fields['religion'] ?? '')),
            'education'     => trim((string) ($fields['education'] ?? '')),
            'job'           => trim((string) ($fields['job'] ?? '')),
            // Numeric value; the importer's resolveIncome() takes a number or a bracket label.
            'monthlyincome' => trim((string) ($fields['salary'] ?? '')),
            'address'       => trim((string) ($fields['address'] ?? '')),
            'barangay'      => trim((string) ($fields['barangay'] ?? '')),
            'sector'        => $this->sectorCodes((array) ($fields['sector_ids'] ?? [])),
            'services'      => $this->serviceCodes((array) ($fields['service_ids'] ?? [])),
        ];
    }

    /**
     * Normalises the posted members[] array into a clean, gap-free list of member field
     * arrays (drops fully empty rows the way store()/update() do).
     *
     * @param array<string,mixed> $post
     * @return list<array<string,mixed>>
     */
    private function postMembers(array $post): array
    {
        $members = $post['members'] ?? [];

        if (! is_array($members)) {
            return [];
        }

        $out = [];

        foreach ($members as $member) {
            if (! is_array($member)) {
                continue;
            }

            // Skip rows with no name at all (an empty template row).
            $hasData = trim((string) ($member['firstname'] ?? '')) !== ''
                || trim((string) ($member['lastname'] ?? '')) !== '';

            if ($hasData) {
                $out[] = $member;
            }
        }

        return $out;
    }

    /** String sector IDs -> comma-joined shortcodes for the raw `sector` cell. */
    private function sectorCodes(array $ids): string
    {
        if ($ids === []) {
            return '';
        }

        $this->loadSectorMaps();
        $codes = [];

        foreach ($ids as $id) {
            $id = (int) $id;

            if (isset($this->sectorIdToCode[$id])) {
                $codes[] = $this->sectorIdToCode[$id];
            }
        }

        return implode(', ', array_values(array_unique($codes)));
    }

    /** String service IDs -> comma-joined shortcodes for the raw `services` cell. */
    private function serviceCodes(array $ids): string
    {
        if ($ids === []) {
            return '';
        }

        $this->loadServiceMaps();
        $codes = [];

        foreach ($ids as $id) {
            $id = (int) $id;

            if (isset($this->serviceIdToCode[$id])) {
                $codes[] = $this->serviceIdToCode[$id];
            }
        }

        return implode(', ', array_values(array_unique($codes)));
    }

    /**
     * Assigns sheet numbers to the rebuilt rows: reuse the replaced group's own numbers in
     * order (keeps report ordering stable), and allocate fresh numbers past the bundle's
     * current maximum for any extra members.
     *
     * @param list<array<string,string>> $people
     * @return list<array{sheetRow:int, data:array<string,string>}>
     */
    private function assignSheetRows(array $people, array $bundle, string $oldFamilyNo): array
    {
        $rows = is_array($bundle['rows'] ?? null) ? $bundle['rows'] : [];

        $reusable = [];
        $maxRow   = 1;

        foreach ($rows as $row) {
            $sheetRow = (int) ($row['sheetRow'] ?? 0);
            $maxRow   = max($maxRow, $sheetRow);

            if (trim((string) (($row['data'] ?? [])['familyno'] ?? '')) === $oldFamilyNo) {
                $reusable[] = $sheetRow;
            }
        }

        sort($reusable);
        $next = $maxRow + 1;
        $out  = [];

        foreach ($people as $data) {
            $sheetRow = array_shift($reusable);

            if ($sheetRow === null) {
                $sheetRow = $next++;
            }

            $out[] = ['sheetRow' => $sheetRow, 'data' => $data];
        }

        return $out;
    }

    // -- lookup-map loaders ----------------------------------------------------

    /** Comma-split shortcodes, trimmed + uppercased, matching the importer's splitList. */
    private function splitCodes(string $cell): array
    {
        if (trim($cell) === '') {
            return [];
        }

        $out = [];

        foreach (explode(',', $cell) as $token) {
            $token = strtoupper(trim($token));

            if ($token !== '') {
                $out[] = $token;
            }
        }

        return $out;
    }

    private function loadSectorMaps(): void
    {
        if ($this->sectorCodeToId !== null) {
            return;
        }

        $this->sectorCodeToId = [];
        $this->sectorIdToCode = [];
        $otherId = 0;

        foreach ((new SectorModel())->getActive() as $sector) {
            $code = strtoupper(trim((string) ($sector['shortcode'] ?? '')));
            $name = strtoupper(trim((string) ($sector['name'] ?? '')));
            $id   = (int) ($sector['sectorID'] ?? 0);

            if ($code === '' || $id <= 0) {
                continue;
            }

            $this->sectorCodeToId[$code] = $id;
            $this->sectorIdToCode[$id]   = $code;

            if ($otherId === 0 && (str_contains($code, 'OTHER') || str_contains($name, 'OTHER'))) {
                $otherId = $id;
            }
        }

        if ($otherId > 0) {
            $this->sectorCodeToId['OTHER'] = $otherId;
        }
    }

    private function loadServiceMaps(): void
    {
        if ($this->serviceCodeToId !== null) {
            return;
        }

        $this->serviceCodeToId = [];
        $this->serviceIdToCode = [];

        foreach ((new ServiceModel())->getActive() as $service) {
            $code = strtoupper(trim((string) ($service['shortcode'] ?? '')));
            $id   = (int) ($service['serviceID'] ?? 0);

            if ($code === '' || $id <= 0) {
                continue;
            }

            $this->serviceCodeToId[$code] = $id;
            $this->serviceIdToCode[$id]   = $code;
        }
    }

    private function loadIncomeMap(): void
    {
        if ($this->incomeByLabel !== null) {
            return;
        }

        $this->incomeByLabel = [];
        $this->incomeValues  = [];

        foreach ((new FamilyFormOptionsModel())->getOptions()['income_ranges'] ?? [] as $range) {
            $value = (string) ($range['value'] ?? '');
            $label = strtolower(trim((string) ($range['label'] ?? '')));

            if ($value === '') {
                continue;
            }

            $this->incomeValues[] = $value;

            if ($label !== '') {
                $this->incomeByLabel[$label] = $value;
            }
        }
    }
}
