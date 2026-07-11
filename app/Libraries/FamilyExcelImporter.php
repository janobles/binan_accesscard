<?php

namespace App\Libraries;

use App\Models\Families\FamilyFormOptionsModel;
use App\Models\Lookups\SectorModel;
use App\Models\Lookups\ServiceModel;
use App\Support\MemberFieldNormalizer;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

/**
 * Reads and validates a filled family-records .xlsx (produced from
 * App\Libraries\FamilyExcelTemplate) into ready-to-persist family payloads.
 *
 * Layout: the "Families" sheet has one row per person; a "FamilyNo" column groups a
 * family; the row with Relationship = "Head" is the head. Sectors and Services are
 * comma-separated CODES (sector.shortcode / services.shortcode). Civil status and
 * education accept the CSWD form short codes (translated to the full stored value).
 *
 * Validation mirrors FamilyController::rulesForEntryType('head') and the member rules
 * and is ALL-OR-NOTHING: process() returns false if any row has a problem, and the
 * caller imports nothing. On success, getFamilies() returns entries shaped exactly for
 * FamilyRecordWriter::persistFamily.
 */
class FamilyExcelImporter
{
    /** @var list<array{familyNo: string, headName: string, headPayload: array, headServiceIds: int[], memberPayloads: list<array{payload: array, serviceIds: int[]}>}> */
    private array $families = [];

    /** @var list<array{sheetRow: ?int, familyNo: string, message: string}> */
    private array $errors = [];

    private int $memberCount = 0;

    /**
     * Parses + validates the workbook. Returns true only when every row is valid.
     */
    public function process(string $filePath): bool
    {
        try {
            $spreadsheet = IOFactory::load($filePath);
        } catch (Throwable $exception) {
            return $this->fail('The file could not be read as an Excel workbook. Make sure it is a .xlsx file saved from the template.');
        }

        $sheet = $spreadsheet->getSheetByName(FamilyExcelTemplate::DATA_SHEET);

        if ($sheet === null) {
            return $this->fail('The "' . FamilyExcelTemplate::DATA_SHEET . '" sheet was not found. Please use the downloaded template.');
        }

        $headerRow = $this->headerRowIndex($sheet);
        $columnMap = $this->mapHeaders($sheet, $headerRow);

        if ($columnMap === null) {
            return false;
        }

        $sectorByCode  = $this->sectorCodeMap();
        $serviceByCode = $this->serviceCodeMap();
        $incomeByLabel = $this->incomeLabelMap();

        $grouped = $this->groupRows($sheet, $columnMap, $headerRow);

        if ($grouped === [] && $this->errors === []) {
            return $this->fail('No family rows were found on the "' . FamilyExcelTemplate::DATA_SHEET . '" sheet.');
        }

        foreach ($grouped as $familyNo => $rows) {
            $this->processFamily((string) $familyNo, $rows, $sectorByCode, $serviceByCode, $incomeByLabel);
        }

        return $this->errors === [];
    }

    /** @return list<array{sheetRow: ?int, familyNo: string, message: string}> */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /** @return list<array{familyNo: string, headName: string, headPayload: array, headServiceIds: int[], memberPayloads: list<array{payload: array, serviceIds: int[]}>}> */
    public function getFamilies(): array
    {
        return $this->families;
    }

    /** Counts for the success summary: ['families' => int, 'members' => int]. */
    public function getSummary(): array
    {
        return [
            'families' => count($this->families),
            'members'  => $this->memberCount,
        ];
    }

    // -- parsing ---------------------------------------------------------------

    /**
     * Finds the header row by locating the "FamilyNo" cell within the first rows, so a
     * decorative banner row above the headers does not break parsing. Falls back to 1.
     */
    private function headerRowIndex(Worksheet $sheet): int
    {
        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $maxScan = min(20, max(1, $sheet->getHighestDataRow()));

        for ($row = 1; $row <= $maxScan; $row++) {
            for ($i = 1; $i <= $highestColumnIndex; $i++) {
                $header = $this->normalizeHeader($sheet->getCell(Coordinate::stringFromColumnIndex($i) . $row)->getValue());

                // The family-group key column is "FamilyNo" or (newer templates) "QR Number".
                if ($header === 'familyno' || $header === 'qrnumber') {
                    return $row;
                }
            }
        }

        return 1;
    }

    /**
     * Reads the header row into [normalizedHeader => columnLetter]. Returns null
     * (and records an error) when a required column is missing.
     *
     * @return array<string, string>|null
     */
    private function mapHeaders(Worksheet $sheet, int $headerRow): ?array
    {
        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $map = [];

        for ($i = 1; $i <= $highestColumnIndex; $i++) {
            $letter = Coordinate::stringFromColumnIndex($i);
            $header = $this->normalizeHeader($sheet->getCell($letter . $headerRow)->getValue());

            if ($header !== '') {
                $map[$header] = $letter;
            }
        }

        // Drop the template's helper column(s) — they hold Excel formulas, not data.
        foreach (['check', 'status', 'validation', 'notes'] as $helper) {
            unset($map[$helper]);
        }

        // The family-group key column may be headed "FamilyNo" or "QR Number"; alias it
        // to 'familyno' so the rest of the importer is agnostic to the label.
        if (! isset($map['familyno']) && isset($map['qrnumber'])) {
            $map['familyno'] = $map['qrnumber'];
        }

        $required = ['familyno', 'relationship', 'firstname', 'lastname'];
        $missing  = array_diff($required, array_keys($map));

        if ($missing !== []) {
            $this->fail('The template is missing required column(s): ' . implode(', ', $missing) . '. Please use the downloaded template.');

            return null;
        }

        return $map;
    }

    /**
     * Reads every non-empty data row and groups it by FamilyNo.
     *
     * @param array<string, string> $columnMap
     * @return array<string, list<array{row: int, data: array<string, string>}>>
     */
    private function groupRows(Worksheet $sheet, array $columnMap, int $headerRow): array
    {
        $highestRow = $sheet->getHighestDataRow();
        $grouped = [];

        for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
            $values = $this->readRow($sheet, $columnMap, $row);

            if ($this->rowIsEmpty($values)) {
                continue;
            }

            $familyNo = trim((string) ($values['familyno'] ?? ''));

            if ($familyNo === '') {
                $this->addError($row, '', 'QR Number is required (use the same number for everyone in one family).');
                continue;
            }

            // Must be digits only: it becomes the paper QR control number via an
            // (int) cast downstream, so "5A"/"5B" would both collapse to 5 and one
            // family's QR would silently overwrite the other's.
            if (! ctype_digit($familyNo)) {
                $this->addError($row, $familyNo, 'QR Number "' . $familyNo . '" must contain digits only.');
                continue;
            }

            $grouped[$familyNo][] = ['row' => $row, 'data' => $values];
        }

        return $grouped;
    }

    /**
     * Reads one sheet row into [normalizedHeader => trimmed string value]. Birthday is
     * date-aware so a real Excel date becomes "MM-DD-YYYY" (the sheet's display format).
     *
     * @param array<string, string> $columnMap
     * @return array<string, string>
     */
    private function readRow(Worksheet $sheet, array $columnMap, int $row): array
    {
        $values = [];

        foreach ($columnMap as $key => $letter) {
            $cell  = $sheet->getCell($letter . $row);
            $value = $cell->getValue();

            if ($key === 'birthday' && $value !== null && $value !== '' && ExcelDate::isDateTime($cell)) {
                $values[$key] = ExcelDate::excelToDateTimeObject($value)->format('m-d-Y');
                continue;
            }

            // Placeholder words ("none", "n/a", "blank", …) are treated as an empty
            // cell, so they never store as data and required fields still flag missing.
            $values[$key] = MemberFieldNormalizer::blankIfNoData($value);
        }

        return $values;
    }

    // -- per-family validation + payload build ---------------------------------

    /**
     * Validates one family group and, when valid, appends its persist-ready payload.
     *
     * @param list<array{row: int, data: array<string, string>}> $rows
     * @param array<string, int>    $sectorByCode
     * @param array<string, int>    $serviceByCode
     * @param array<string, string> $incomeByLabel
     */
    private function processFamily(string $familyNo, array $rows, array $sectorByCode, array $serviceByCode, array $incomeByLabel): void
    {
        $heads   = [];
        $members = [];

        foreach ($rows as $entry) {
            if (strcasecmp(trim((string) ($entry['data']['relationship'] ?? '')), 'Head') === 0) {
                $heads[] = $entry;
            } else {
                $members[] = $entry;
            }
        }

        if (count($heads) === 0) {
            $this->addError($rows[0]['row'], $familyNo, 'Family ' . $familyNo . ' has no Head row (set Relationship = Head on exactly one person).');

            return;
        }

        if (count($heads) > 1) {
            $this->addError($heads[1]['row'], $familyNo, 'Family ' . $familyNo . ' has more than one Head row (only one person can be the Head).');

            return;
        }

        $headPayload    = $this->buildPersonPayload($heads[0], $familyNo, true, $sectorByCode, $incomeByLabel);
        $headServiceIds = $this->mapServices($heads[0], $familyNo, $serviceByCode);

        $memberPayloads = [];

        foreach ($members as $memberEntry) {
            $memberPayload = $this->buildPersonPayload($memberEntry, $familyNo, false, $sectorByCode, $incomeByLabel);
            // Members share the head's address — auto-fill it so workers never copy-paste
            // (or mistype) the address on every member row. Any address typed on a member
            // row is ignored in favor of the head's.
            $memberPayload['address'] = $headPayload['address'];

            $memberPayloads[] = [
                'payload'    => $memberPayload,
                'serviceIds' => $this->mapServices($memberEntry, $familyNo, $serviceByCode),
            ];
        }

        $this->families[] = [
            'familyNo'       => $familyNo,
            'headName'       => trim(($headPayload['firstname'] ?? '') . ' ' . ($headPayload['lastname'] ?? '')),
            'headPayload'    => $headPayload,
            'headServiceIds' => $headServiceIds,
            'memberPayloads' => $memberPayloads,
        ];

        $this->memberCount += count($memberPayloads);
    }

    /**
     * Validates and shapes one person into a `member` row payload, recording field
     * errors. Civil status and education accept short codes (translated to full values).
     *
     * @param array{row: int, data: array<string, string>} $entry
     * @param array<string, int>    $sectorByCode
     * @param array<string, string> $incomeByLabel
     */
    private function buildPersonPayload(array $entry, string $familyNo, bool $isHead, array $sectorByCode, array $incomeByLabel): array
    {
        $row  = $entry['row'];
        $data = $entry['data'];

        $firstName = (string) ($data['firstname'] ?? '');
        $lastName  = (string) ($data['lastname'] ?? '');

        $this->requireField($row, $familyNo, 'First name', $firstName);
        $this->requireField($row, $familyNo, 'Last name', $lastName);

        $birthday = $this->validateBirthday($row, $familyNo, (string) ($data['birthday'] ?? ''), $isHead);
        $sex      = $this->validateSex($row, $familyNo, (string) ($data['sex'] ?? ''), $isHead);

        // Civil status / education accept a code, "CODE - Name", or the full name.
        $civilStatus = $this->fullValueFromCode((string) ($data['civilstatus'] ?? ''), FamilyExcelTemplate::CIVIL_STATUS_CODES);
        $education   = $this->fullValueFromCode((string) ($data['education'] ?? ''), FamilyExcelTemplate::EDUCATION_CODES);

        if ($isHead) {
            $this->requireField($row, $familyNo, 'Civil status', $civilStatus);
            $this->requireField($row, $familyNo, 'Education', $education);
            $this->requireField($row, $familyNo, 'Job', (string) ($data['job'] ?? ''));
            $this->requireField($row, $familyNo, 'Address', (string) ($data['address'] ?? ''));
            $this->requireField($row, $familyNo, 'Barangay', (string) ($data['barangay'] ?? ''));
        } else {
            $this->requireField($row, $familyNo, 'Relationship', (string) ($data['relationship'] ?? ''));
        }

        $income = $this->resolveIncome($row, $familyNo, (string) ($data['monthlyincome'] ?? ''), $isHead, $incomeByLabel);
        $sectorIds = $this->mapSectors($entry, $familyNo, $sectorByCode);

        return [
            'firstname'     => MemberFieldNormalizer::cleanName($firstName),
            'middlename'    => MemberFieldNormalizer::cleanName((string) ($data['middlename'] ?? '')),
            'lastname'      => MemberFieldNormalizer::cleanName($lastName),
            'suffix'        => MemberFieldNormalizer::nullableText((string) ($data['suffix'] ?? '')),
            'birthday'      => $birthday,
            'civilstatus'   => MemberFieldNormalizer::nullableText($civilStatus),
            'sex'           => $sex,
            'education'     => MemberFieldNormalizer::nullableText($education),
            'job'           => MemberFieldNormalizer::nullableText((string) ($data['job'] ?? '')),
            'Salary'        => MemberFieldNormalizer::moneyOrNull($income),
            'contactnumber' => MemberFieldNormalizer::nullableText((string) ($data['contactnumber'] ?? '')),
            'religion'      => MemberFieldNormalizer::nullableText((string) ($data['religion'] ?? '')),
            'address'       => MemberFieldNormalizer::combineAddressBarangay(
                (string) ($data['address'] ?? ''),
                (string) ($data['barangay'] ?? '')
            ),
            'relationship'  => $isHead ? 'Head' : (MemberFieldNormalizer::nullableText((string) ($data['relationship'] ?? '')) ?? 'Member'),
            'sectorID'      => $sectorIds,
        ];
    }

    /** Records an error when a required field is blank. */
    private function requireField(int $row, string $familyNo, string $label, string $value): void
    {
        if (trim($value) === '') {
            $this->addError($row, $familyNo, $label . ' is required.');
        }
    }

    /**
     * Validates a birthday cell. The sheet uses MM-DD-YYYY; legacy YYYY-MM-DD files are
     * still accepted. Required for heads. Returns the stored Y-m-d value or null.
     */
    private function validateBirthday(int $row, string $familyNo, string $value, bool $required): ?string
    {
        $value = trim($value);

        if ($value === '') {
            if ($required) {
                $this->addError($row, $familyNo, 'Birthday is required (format MM-DD-YYYY).');
            }

            return null;
        }

        // Primary entry format is MM-DD-YYYY; fall back to the legacy YYYY-MM-DD.
        foreach (['m-d-Y', 'Y-m-d'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!' . $format, $value);

            if ($date !== false && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        $this->addError($row, $familyNo, 'Birthday "' . $value . '" is not a valid date (use MM-DD-YYYY).');

        return null;
    }

    /** Validates a sex cell against Male/Female. Required for heads. */
    private function validateSex(int $row, string $familyNo, string $value, bool $required): ?string
    {
        $value = trim($value);

        if ($value === '') {
            if ($required) {
                $this->addError($row, $familyNo, 'Sex is required (Male or Female).');
            }

            return null;
        }

        if (strcasecmp($value, 'Male') === 0) {
            return 'Male';
        }

        if (strcasecmp($value, 'Female') === 0) {
            return 'Female';
        }

        $this->addError($row, $familyNo, 'Sex "' . $value . '" must be Male or Female.');

        return null;
    }

    /**
     * Resolves a monthly-income cell (a bracket label or a number) to its stored value.
     * Required for heads.
     *
     * @param array<string, string> $incomeByLabel
     */
    private function resolveIncome(int $row, string $familyNo, string $value, bool $required, array $incomeByLabel): ?string
    {
        $value = trim($value);

        if ($value === '') {
            if ($required) {
                $this->addError($row, $familyNo, 'Monthly income is required.');
            }

            return null;
        }

        $key = strtolower($value);

        if (isset($incomeByLabel[$key])) {
            return $incomeByLabel[$key];
        }

        $numeric = str_replace(',', '', $value);

        if (is_numeric($numeric)) {
            return $numeric;
        }

        $this->addError($row, $familyNo, 'Monthly income "' . $value . '" is not a valid bracket or number.');

        return null;
    }

    /**
     * Maps a row's comma-separated sector codes to IDs. Sector mirrors the form's
     * "Others" dropdown: an unrecognized code is filed under the "Other Sectors"
     * catch-all rather than aborting the import, so a worker's free-text sector never
     * blocks the whole batch. If no Other sector exists, the unknown token is skipped.
     *
     * @param array{row: int, data: array<string, string>} $entry
     * @param array<string, int> $sectorByCode
     * @return int[]
     */
    private function mapSectors(array $entry, string $familyNo, array $sectorByCode): array
    {
        $ids     = [];
        $otherId = $sectorByCode['OTHER'] ?? null;

        foreach ($this->splitList((string) ($entry['data']['sector'] ?? '')) as $token) {
            $code = strtoupper($token);

            if (isset($sectorByCode[$code])) {
                $ids[] = $sectorByCode[$code];
                continue;
            }

            // Unrecognized sector text -> "Other Sectors" (or skip if there is none).
            if ($otherId !== null) {
                $ids[] = $otherId;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Maps a row's comma-separated service codes to IDs, recording an error for any
     * unknown code.
     *
     * @param array{row: int, data: array<string, string>} $entry
     * @param array<string, int> $serviceByCode
     * @return int[]
     */
    private function mapServices(array $entry, string $familyNo, array $serviceByCode): array
    {
        $ids = [];

        foreach ($this->splitList((string) ($entry['data']['services'] ?? '')) as $token) {
            $code = strtoupper($token);

            if (isset($serviceByCode[$code])) {
                $ids[] = $serviceByCode[$code];
                continue;
            }

            $this->addError($entry['row'], $familyNo, 'Unknown service code "' . $token . '" (see the Reference sheet).');
        }

        return array_values(array_unique($ids));
    }

    // -- lookups + helpers -----------------------------------------------------

    /**
     * [UPPER shortcode => sectorID] of active sectors. The literal codes "OTHERS"/"OTHER"
     * are aliased to the catch-all "Other" sector (matched by shortcode or name) so a
     * worker can type "OTHERS" without knowing its real shortcode.
     */
    private function sectorCodeMap(): array
    {
        $map     = [];
        $otherId = 0;

        foreach ((new SectorModel())->getActive() as $sector) {
            $code = strtoupper(trim((string) ($sector['shortcode'] ?? '')));
            $name = strtoupper(trim((string) ($sector['name'] ?? '')));
            $id   = (int) ($sector['sectorID'] ?? 0);

            if ($code !== '' && $id > 0) {
                $map[$code] = $id;
            }

            if ($otherId === 0 && $id > 0 && (str_contains($code, 'OTHER') || str_contains($name, 'OTHER'))) {
                $otherId = $id;
            }
        }

        if ($otherId > 0) {
            $map['OTHERS'] = $otherId;
            $map['OTHER']  = $otherId;
        }

        return $map;
    }

    /** [UPPER shortcode => serviceID] of active services. */
    private function serviceCodeMap(): array
    {
        $map = [];

        foreach ((new ServiceModel())->getActive() as $service) {
            $code = strtoupper(trim((string) ($service['shortcode'] ?? '')));
            $id   = (int) ($service['serviceID'] ?? 0);

            if ($code !== '' && $id > 0) {
                $map[$code] = $id;
            }
        }

        return $map;
    }

    /** [lowercase bracket label => stored numeric value] for income resolution. */
    private function incomeLabelMap(): array
    {
        $map = [];

        foreach ((new FamilyFormOptionsModel())->getOptions()['income_ranges'] ?? [] as $range) {
            $value = (string) ($range['value'] ?? '');
            $label = strtolower(trim((string) ($range['label'] ?? '')));

            if ($value !== '' && $label !== '') {
                $map[$label] = $value;
            }
        }

        return $map;
    }

    /**
     * Translates a civil-status / education cell to its full stored value. Accepts a
     * bare code ("M"), a "CODE - Name" pick ("M - Married"), or the full name (returned
     * unchanged so existing full-name files and "Others" still work).
     *
     * @param array<string,string> $codeMap code => full value
     */
    private function fullValueFromCode(string $value, array $codeMap): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $token = str_contains($value, ' - ') ? trim(explode(' - ', $value)[0]) : $value;

        foreach ($codeMap as $code => $full) {
            if (strcasecmp($token, $code) === 0) {
                return $full;
            }
        }

        return $value;
    }

    /** Normalizes a header label to lowercase alphanumerics ("Monthly Income" -> "monthlyincome"). */
    private function normalizeHeader(mixed $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower(trim((string) $value)));
    }

    /** @return list<string> Non-empty, trimmed tokens from a comma-separated cell. */
    private function splitList(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        $tokens = array_map('trim', explode(',', $value));

        // Drop blanks and no-data placeholders ("SR1, none" -> just SR1) so a
        // placeholder among codes doesn't trip the "Unknown code" error.
        return array_values(array_filter(
            $tokens,
            static fn (string $t): bool => $t !== '' && ! MemberFieldNormalizer::isNoData($t)
        ));
    }

    /** @param array<string, string> $values */
    private function rowIsEmpty(array $values): bool
    {
        foreach ($values as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /** Records an error and returns false (for one-line early returns). */
    private function fail(string $message): bool
    {
        $this->addError(null, '', $message);

        return false;
    }

    private function addError(?int $sheetRow, string $familyNo, string $message): void
    {
        $this->errors[] = [
            'sheetRow' => $sheetRow,
            'familyNo' => $familyNo,
            'message'  => $message,
        ];
    }
}
