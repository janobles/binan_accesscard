<?php

namespace App\Libraries;

use App\Models\Families\FamilyFormOptionsModel;
use App\Models\Families\MemberModel;
use App\Models\Lookups\SectorModel;
use App\Models\Lookups\ServiceModel;
use App\Models\Scanner\QrControlModel;
use App\Support\FamilyProfilingFormV2;
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
 * Layout: the "Families" sheet has one row per person; a "FamilyNo" / "QR Number"
 * column groups a family; the row with Relationship = "Head" is the head. Sectors and
 * Services are comma-separated CODES. Civil status and education accept the CSWD form
 * short codes (translated to the full stored value).
 *
 * Two-step pipeline so the SAME validators run on an uploaded file AND on rows a
 * reviewer has edited in the browser:
 *   1. parseFile()       — PhpSpreadsheet read -> a normalized row set (+ file-level errors)
 *   2. validateAndBuild() — runs every validator over a row set -> families + errors
 * stage() runs both and returns a review-ready bundle. Unlike the old all-or-nothing
 * flow, validateAndBuild reports EVERY row's problems (it does not stop at the first bad
 * family), so the reviewer sees the whole picture up front.
 *
 * An error is `['sheetRow'=>?int, 'familyNo'=>string, 'field'=>?string, 'code'=>string,
 * 'message'=>string, 'severity'=>'blocking'|'warning']`. `code` groups the review UI's
 * buckets; `field` targets the editable cell; `severity` decides whether it blocks the
 * import. Built families are shaped exactly for FamilyRecordWriter::persistFamily.
 */
class FamilyExcelImporter
{
    /** MySQL signed INT max — the QR control number ceiling. */
    public const QR_MAX = 2147483647;

    /**
     * Common suffix spellings → the canonical dropdown value (Jr/Sr/I–V). Keys are
     * lowercased with dots removed. Lets "Junior", "the 3rd", "2nd" map to a valid enum
     * value instead of being dropped, so the DB never sees an out-of-enum suffix.
     */
    private const SUFFIX_ALIASES = [
        'jr' => 'Jr', 'junior' => 'Jr',
        'sr' => 'Sr', 'senior' => 'Sr',
        'i' => 'I', '1' => 'I', '1st' => 'I', 'first' => 'I',
        'ii' => 'II', '2' => 'II', '2nd' => 'II', 'second' => 'II',
        'iii' => 'III', '3' => 'III', '3rd' => 'III', 'third' => 'III',
        'iv' => 'IV', '4' => 'IV', '4th' => 'IV', 'fourth' => 'IV',
        'v' => 'V', '5' => 'V', '5th' => 'V', 'fifth' => 'V',
    ];

    /** @var list<array{familyNo: string, headName: string, headPayload: array, headServiceIds: int[], memberPayloads: list<array{payload: array, serviceIds: int[]}>}> */
    private array $families = [];

    /** @var list<array{sheetRow: ?int, familyNo: string, field: ?string, code: string, message: string, severity: string}> */
    private array $errors = [];

    /** Members whose QR already belongs to a family — added to it on import. */
    private array $appends = [];

    /** [int qr => head name] for QRs already in the DB (passed into validateAndBuild). */
    private array $existingHeads = [];

    private int $memberCount = 0;

    // Cached DB lookups (reference data) so re-validation on each edit stays cheap.
    private ?array $sectorByCode  = null;
    private ?array $serviceByCode = null;
    private ?array $incomeByLabel = null;

    // Normalized official-barangay lookup, built once from FamilyProfilingFormV2.
    private ?array $barangayLookup = null;

    // -- public API ------------------------------------------------------------

    /**
     * Parses + validates a workbook into a review-ready bundle. On a hard parse
     * failure (unreadable / wrong sheet / missing columns) `ok` is false and `errors`
     * carries the single file-level reason.
     *
     * @return array{ok: bool, rows: list<array{sheetRow: int, data: array<string,string>}>, errors: list<array>, families: list<array>, counts: array{families:int, members:int, blocking:int, warnings:int}}
     */
    public function stage(string $filePath): array
    {
        $parsed = $this->parseFile($filePath);

        if (! $parsed['ok']) {
            return [
                'ok'         => false,
                'rows'       => [],
                'errors'     => $parsed['errors'],
                'fileErrors' => $parsed['errors'],
                'families'   => [],
                'counts'     => $this->summarize([], $parsed['errors']),
            ];
        }

        $existingHeads = $this->existingHeadsForRows($parsed['rows']);
        $built  = $this->validateAndBuild($parsed['rows'], $existingHeads);
        $errors = array_merge($parsed['errors'], $built['errors']);

        return [
            'ok'         => true,
            'rows'       => $parsed['rows'],
            'errors'     => $errors,
            'fileErrors' => $parsed['errors'],
            // [field => Excel column letter] so the review can name the exact cell.
            'columns'    => $parsed['columns'] ?? [],
            'families'   => $built['families'],
            'appends'    => $built['appends'],
            'counts'     => $this->summarize($built['families'], $errors, $built['appends']),
        ];
    }

    /**
     * Reads a workbook into a normalized row set. Each row is
     * `['sheetRow'=>int, 'data'=>[normalizedHeader => trimmed string]]`. QR cells are
     * kept verbatim (placeholders like "N/A" are NOT blanked, so they surface as a
     * format error rather than a silent "missing").
     *
     * @return array{ok: bool, rows: list<array{sheetRow: int, data: array<string,string>}>, errors: list<array>}
     */
    public function parseFile(string $filePath): array
    {
        try {
            $spreadsheet = IOFactory::load($filePath);
        } catch (Throwable $exception) {
            return $this->parseFailure('The file could not be read as an Excel workbook. Make sure it is a .xlsx file saved from the template.');
        }

        $sheet = $spreadsheet->getSheetByName(FamilyExcelTemplate::DATA_SHEET);

        if ($sheet === null) {
            return $this->parseFailure('The "' . FamilyExcelTemplate::DATA_SHEET . '" sheet was not found. Please use the downloaded template.');
        }

        $headerRow = $this->headerRowIndex($sheet);
        $columnMap = $this->mapHeaders($sheet, $headerRow);

        $required = ['familyno', 'relationship', 'firstname', 'lastname'];
        $missing  = array_diff($required, array_keys($columnMap));

        if ($missing !== []) {
            return $this->parseFailure('The template is missing required column(s): ' . implode(', ', $missing) . '. Please use the downloaded template.');
        }

        $errors       = [];
        $qrLetter     = $columnMap['familyno'];
        $firstDataRow = $headerRow + 1;

        // QR-11: a merged QR cell in the DATA rows leaves every row but the top one blank.
        // The template's banner/header merges (e.g. "A1:B1" over the title row) are NOT a
        // problem, so only flag merges that reach into the data region.
        foreach ($sheet->getMergeCells() as $range) {
            if ($this->rangeTouchesColumn($range, $qrLetter) && $this->rangeMaxRow($range) >= $firstDataRow) {
                $errors[] = $this->makeError(null, '', 'QR-11', 'familyno',
                    'The QR Number column has merged cells (' . $range . '). Unmerge it, repeat the QR number on every row of the family, then re-upload.');
            }
        }

        $rows = $this->readRows($sheet, $columnMap, $headerRow);

        if ($rows === []) {
            $errors[] = $this->makeError(null, '', 'EMPTY', null, 'No family rows were found on the "' . FamilyExcelTemplate::DATA_SHEET . '" sheet.');
        }

        // The column map is carried through so the review can print the EXACT Excel cell
        // to fix (e.g. "H42") — the operator fixes the file, not a copy of it.
        return ['ok' => true, 'rows' => $rows, 'errors' => $errors, 'columns' => $columnMap];
    }

    /**
     * Validates a row set and builds persist-ready families. Reports every row's
     * problems (never skips a row's field checks because its family failed a
     * family-level check). Resets and repopulates the instance's families/errors.
     *
     * @param list<array{sheetRow: int|string, data: array<string,string>}> $rows
     * @return array{families: list<array>, errors: list<array>, counts: array{families:int, members:int, blocking:int, warnings:int}}
     */
    public function validateAndBuild(array $rows, array $existingHeads = []): array
    {
        $this->families      = [];
        $this->errors        = [];
        $this->appends       = [];
        $this->existingHeads = $existingHeads;
        $this->memberCount   = 0;

        $sectorByCode  = $this->sectorCodeMap();
        $serviceByCode = $this->serviceCodeMap();
        $incomeByLabel = $this->incomeLabelMap();

        // STAGE 3-4: validate + normalise each QR, then group. A row whose QR cannot be
        // validated is reported and left ungrouped (it has no usable family key).
        $groups = [];

        foreach ($rows as $entry) {
            $sheetRow = (int) $entry['sheetRow'];
            $data     = $entry['data'];

            if ($this->rowIsEmpty($data)) {
                continue;
            }

            $qr = $this->validateQr((string) ($data['familyno'] ?? ''));

            if (! $qr['ok']) {
                $this->addError($sheetRow, (string) ($data['familyno'] ?? ''), $qr['code'], 'familyno', $qr['msg']);
                continue;
            }

            $groups[$qr['qr']][] = ['row' => $sheetRow, 'data' => $data];
        }

        foreach ($groups as $familyNo => $familyRows) {
            $this->processFamily((string) $familyNo, $familyRows, $sectorByCode, $serviceByCode, $incomeByLabel);
        }

        // Cross-row pass: flag rows that look like the same person (name+birthday+address).
        $this->checkDuplicatePersons($groups);

        return [
            'families' => $this->families,
            'errors'   => $this->errors,
            'appends'  => $this->appends,
            'counts'   => $this->summarize($this->families, $this->errors, $this->appends),
        ];
    }

    /**
     * Normalises + validates a single raw QR string. Returns
     * `['ok'=>true,'qr'=>int]` or `['ok'=>false,'code'=>string,'msg'=>string]`.
     *
     * Order matters: the strict regex runs BEFORE the int cast, so "5880.0" (a dot) is
     * rejected instead of silently becoming 5880 and filing a person into a stranger's
     * family. (A purely numeric whole-number cell reads back as that int and is accepted
     * — 5880.0 and 5880 are the same value in xlsx and cannot be told apart; the regex
     * only rescues the text-formatted ".0" case, which the template's text column gives.)
     */
    public function validateQr(string $raw): array
    {
        // Excel error literal (#REF!, #N/A) — poisons helper formulas if trusted.
        if (str_starts_with($raw, '#')) {
            return ['ok' => false, 'code' => 'QR-08', 'msg' => 'The QR Number cell holds an Excel error value (like #REF!). Retype the number.'];
        }

        // A formula leaked through (getValue returns "=A4", not a number).
        if (str_starts_with($raw, '=')) {
            return ['ok' => false, 'code' => 'QR-12', 'msg' => 'The QR Number cell holds a formula. Type the number itself, not a formula.'];
        }

        // STAGE 2 — normalise: strip NBSP + zero-width, trim. Do NOT strip commas.
        $s = str_replace(["\u{00A0}", "\u{200B}"], '', $raw);
        $s = trim($s);

        if ($s === '') {
            return ['ok' => false, 'code' => 'QR-01', 'msg' => 'QR Number is required (use the same number for everyone in one family).'];
        }

        // STAGE 3 — strict format. Rejects letters, placeholders, negatives, decimals,
        // integral-float text ("5880.0" has a dot), commas, scientific notation.
        if (! preg_match('/^[0-9]{1,10}$/', $s)) {
            return ['ok' => false, 'code' => 'QR-FORMAT', 'msg' => 'QR Number "' . $s . '" must be a whole number — no letters, symbols, decimals, or commas.'];
        }

        // QR-14 — leading zeros are accepted but logged (may signal a card convention).
        if (strlen($s) > 1 && $s[0] === '0') {
            log_message('warning', 'Import: QR Number had leading zeros: ' . $s);
        }

        $qr = (int) $s;

        // STAGE 4 — range.
        if ($qr < 1) {
            return ['ok' => false, 'code' => 'QR-05', 'msg' => 'QR Number must be greater than zero.'];
        }

        if ($qr > self::QR_MAX) {
            return ['ok' => false, 'code' => 'QR-07', 'msg' => 'QR Number "' . $s . '" is too large (maximum ' . self::QR_MAX . ').'];
        }

        return ['ok' => true, 'qr' => $qr];
    }

    /**
     * Looks up which of a row set's QR numbers already exist in the DB, and the name of
     * each existing family's head. Feeds validateAndBuild so it can tell a duplicate
     * family (skip) and members-being-added-to-an-existing-family (append) apart from a
     * genuinely head-less group. Bulk queries; safe when the tables are absent.
     *
     * @param list<array{sheetRow: int|string, data: array<string,string>}> $rows
     * @return array<int, string> [qr => head name]
     */
    public function existingHeadsForRows(array $rows): array
    {
        $qrs = [];

        foreach ($rows as $entry) {
            $qr = $this->validateQr((string) ($entry['data']['familyno'] ?? ''));

            if ($qr['ok']) {
                $qrs[] = $qr['qr'];
            }
        }

        $qrs = array_values(array_unique($qrs));

        if ($qrs === []) {
            return [];
        }

        $qrModel  = new QrControlModel();
        $existing = $qrModel->existingControlNos($qrs);

        if ($existing === []) {
            return [];
        }

        $headByQr = [];

        foreach (array_chunk($existing, 1000) as $chunk) {
            foreach ($qrModel->whereIn('control_no', $chunk)->findAll() as $row) {
                $headByQr[(int) $row['control_no']] = (int) $row['headID'];
            }
        }

        $names = (new MemberModel())->namesForHeads(array_values($headByQr));

        $map = [];

        foreach ($headByQr as $qr => $headId) {
            $map[$qr] = $names[$headId] ?? ('family ' . $qr);
        }

        return $map;
    }

    /**
     * Builds the "add this member to an existing family" entries for a head-less group
     * whose QR is already on file — the classic "worker forgot a member last batch" case.
     * These are ADDED automatically on import and listed in the review; to skip someone,
     * the operator deletes that row from the spreadsheet and re-uploads.
     *
     * @param list<array{row: int, data: array<string, string>}> $rows
     */
    private function collectAppends(string $familyNo, string $headName, array $rows, array $sectorByCode, array $serviceByCode, array $incomeByLabel): void
    {
        foreach ($rows as $entry) {
            $payload    = $this->buildPersonPayload($entry, $familyNo, false, $sectorByCode, $incomeByLabel);
            $serviceIds = $this->mapServices($entry, $familyNo, $serviceByCode);

            $memberName = trim(((string) ($payload['firstname'] ?? '')) . ' ' . ((string) ($payload['lastname'] ?? '')));

            $this->appends[] = [
                'sheetRow'   => (int) $entry['row'],
                'qr'         => (int) $familyNo,
                'headName'   => $headName,
                'payload'    => $payload,
                'serviceIds' => $serviceIds,
            ];

            $this->addError((int) $entry['row'], $familyNo, 'ADD-MEMBER', null,
                ($memberName !== '' ? $memberName : 'This person') . ' will be ADDED to existing family ' . $familyNo
                . ($headName !== '' ? ' (' . $headName . ')' : '')
                . '. To skip them, delete this row from the file and upload again.', 'warning');
        }
    }

    /**
     * Builds the review counts from a family set, its errors, and the append list.
     * `members` is the extra (non-head) members; `people` is every individual; `existing`
     * is duplicate families; the `appends*` keys track members bound for existing families.
     *
     * @param list<array> $families
     * @param list<array> $errors
     * @param list<array> $appends
     */
    public function summarize(array $families, array $errors, array $appends = []): array
    {
        $members = 0;

        foreach ($families as $family) {
            $members += count($family['memberPayloads'] ?? []);
        }

        $familyCount = count($families);

        return [
            'families' => $familyCount,
            'members'  => $members,
            'people'   => $familyCount + $members,
            'existing' => count(array_filter($errors, static fn (array $e): bool => ($e['code'] ?? '') === 'DUP-EXISTS')),
            // Members that will be added to an already-existing family on import.
            'appends'  => count($appends),
            'blocking' => $this->tally($errors, 'blocking'),
            'warnings' => $this->tally($errors, 'warning'),
        ];
    }

    /** @return list<array> the last run's errors (richer shape; see class docblock). */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /** @return list<array> the last run's persist-ready families. */
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

    /**
     * Legacy all-or-nothing entry point (parse + validate a file; true only when every
     * row is valid). Retained for callers that import nothing on any error.
     */
    public function process(string $filePath): bool
    {
        $staged = $this->stage($filePath);

        $this->families    = $staged['families'];
        $this->errors      = $staged['errors'];
        $this->memberCount = $staged['counts']['members'];

        return $staged['errors'] === [];
    }

    // -- parsing ---------------------------------------------------------------

    /**
     * Finds the header row by locating the "FamilyNo"/"QR Number" cell within the first
     * rows, so a decorative banner row above the headers does not break parsing.
     */
    private function headerRowIndex(Worksheet $sheet): int
    {
        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $maxScan = min(20, max(1, $sheet->getHighestDataRow()));

        for ($row = 1; $row <= $maxScan; $row++) {
            for ($i = 1; $i <= $highestColumnIndex; $i++) {
                $header = $this->normalizeHeader($sheet->getCell(Coordinate::stringFromColumnIndex($i) . $row)->getValue());

                if ($header === 'familyno' || $header === 'qrnumber') {
                    return $row;
                }
            }
        }

        return 1;
    }

    /**
     * Reads the header row into [normalizedHeader => columnLetter], aliasing "QR Number"
     * to 'familyno' and dropping the template's helper columns.
     *
     * @return array<string, string>
     */
    private function mapHeaders(Worksheet $sheet, int $headerRow): array
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

        // The family-group key column may be headed "FamilyNo" or "QR Number".
        if (! isset($map['familyno']) && isset($map['qrnumber'])) {
            $map['familyno'] = $map['qrnumber'];
        }

        return $map;
    }

    /**
     * Reads every non-empty data row into the interchange shape used by
     * validateAndBuild.
     *
     * @param array<string, string> $columnMap
     * @return list<array{sheetRow: int, data: array<string, string>}>
     */
    private function readRows(Worksheet $sheet, array $columnMap, int $headerRow): array
    {
        $highestRow = $sheet->getHighestDataRow();
        $rows = [];

        for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
            $values = $this->readRow($sheet, $columnMap, $row);

            if ($this->rowIsEmpty($values)) {
                continue;
            }

            $rows[] = ['sheetRow' => $row, 'data' => $values];
        }

        return $rows;
    }

    /**
     * Reads one sheet row into [normalizedHeader => trimmed string value]. Birthday is
     * date-aware (a real Excel date becomes "MM-DD-YYYY"). The QR cell is kept verbatim
     * (no no-data blanking) so placeholders surface as a format error, not "missing".
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

            // QR: keep the raw text so "N/A"/"5880.0"/"=A4" reach the QR validator intact.
            if ($key === 'familyno') {
                $values[$key] = trim((string) $value);
                continue;
            }

            if ($key === 'birthday' && $value !== null && $value !== '' && ExcelDate::isDateTime($cell)) {
                $values[$key] = ExcelDate::excelToDateTimeObject($value)->format('m-d-Y');
                continue;
            }

            // Placeholder words ("none", "n/a", …) are treated as an empty cell.
            $values[$key] = MemberFieldNormalizer::blankIfNoData($value);
        }

        return $values;
    }

    // -- per-family validation + payload build ---------------------------------

    /**
     * Validates one family group. Emits family-level coherence errors (head count,
     * fingerprint, contiguity) AND validates every row's own fields, so all problems
     * surface at once. Only a coherent (exactly-one-head) family is appended as
     * persist-ready; field errors on it still block the import via the reviewer's gate.
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

        $existsInDb = isset($this->existingHeads[(int) $familyNo]);
        $existingHeadName = (string) ($this->existingHeads[(int) $familyNo] ?? '');

        // Family-level coherence (does not early-return — fields are still validated).
        $this->checkFingerprint($familyNo, $rows);
        $this->checkContiguity($familyNo, $rows);

        // A headless group whose QR already belongs to a family = members being ADDED to
        // that existing family (the worker's forgotten-member-next-batch case). Instead of
        // HEAD-NONE, surface each as an append the operator confirms or removes.
        if (count($heads) === 0 && $existsInDb) {
            $this->collectAppends($familyNo, $existingHeadName, $rows, $sectorByCode, $serviceByCode, $incomeByLabel);

            return;
        }

        if (count($heads) !== 1) {
            if (count($heads) === 0) {
                [$anchorRow, $message] = $this->headlessDiagnosis($familyNo, $rows);
                $this->addError($anchorRow, $familyNo, 'HEAD-NONE', 'relationship', $message);
            } else {
                $this->addError($heads[1]['row'], $familyNo, 'HEAD-MULTI', 'relationship',
                    'Family ' . $familyNo . ' has more than one Head row. Only one person can be the Head.');
            }

            // Aggregate: still validate every row's fields so those errors surface now.
            foreach ($rows as $entry) {
                $isHead = strcasecmp(trim((string) ($entry['data']['relationship'] ?? '')), 'Head') === 0;
                $this->buildPersonPayload($entry, $familyNo, $isHead, $sectorByCode, $incomeByLabel);
                $this->mapServices($entry, $familyNo, $serviceByCode);
            }

            return;
        }

        // One head whose QR is already on file = a duplicate family (skipped on write).
        if ($existsInDb) {
            $this->addError($heads[0]['row'], $familyNo, 'DUP-EXISTS', null,
                'Family ' . $familyNo . ' is already in the system (' . $existingHeadName . '). It will be skipped if you import.', 'warning');
        }

        $headPayload    = $this->buildPersonPayload($heads[0], $familyNo, true, $sectorByCode, $incomeByLabel);
        $headServiceIds = $this->mapServices($heads[0], $familyNo, $serviceByCode);

        $memberPayloads = [];

        foreach ($members as $memberEntry) {
            $memberPayload = $this->buildPersonPayload($memberEntry, $familyNo, false, $sectorByCode, $incomeByLabel);
            // Members share the head's address — auto-fill so workers never retype it.
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
     * Works out WHO the missing Head probably is, for a family with no Head row.
     *
     * In the template only the Head fills Address/Barangay — members leave them blank and
     * inherit the head's. So in a head-less family, the row that carries an address is
     * almost certainly the intended Head (the worker filled it like a head but never set
     * Relationship = Head). Point the error straight at that person.
     *
     * @param list<array{row: int, data: array<string, string>}> $rows
     * @return array{0: int, 1: string} [anchor sheet row, message]
     */
    private function headlessDiagnosis(string $familyNo, array $rows): array
    {
        $withAddress = [];

        foreach ($rows as $entry) {
            $address  = trim((string) ($entry['data']['address'] ?? ''));
            $barangay = trim((string) ($entry['data']['barangay'] ?? ''));

            if ($address !== '' || $barangay !== '') {
                $withAddress[] = $entry;
            }
        }

        // Exactly one person carries the address → that is the Head.
        if (count($withAddress) === 1) {
            $candidate = $withAddress[0];
            $name = trim(((string) ($candidate['data']['firstname'] ?? '')) . ' ' . ((string) ($candidate['data']['lastname'] ?? '')));

            return [
                (int) $candidate['row'],
                'Family ' . $familyNo . ' has no Head. Row ' . $candidate['row']
                    . ($name !== '' ? ' (' . $name . ')' : '')
                    . ' is the only person with an address, so they are most likely the Head — set their relationship to Head.',
            ];
        }

        // Nobody carries an address → the head is missing entirely, address and all.
        if ($withAddress === []) {
            return [
                (int) $rows[0]['row'],
                'Family ' . $familyNo . ' has no Head and no address on any row. Set one person as Head and give them an Address and Barangay.',
            ];
        }

        // Several rows carry an address. If it is the SAME address it is one household
        // (the worker just repeated it) — the operator only has to pick who the Head is.
        // Different addresses mean two households sharing one QR.
        $distinct = [];

        foreach ($withAddress as $entry) {
            $key = $this->normalizeText((string) ($entry['data']['address'] ?? ''))
                . '|' . $this->normalizeText((string) ($entry['data']['barangay'] ?? ''));
            $distinct[$key] = true;
        }

        if (count($distinct) === 1) {
            return [
                (int) $withAddress[0]['row'],
                'Family ' . $familyNo . ' has no Head. ' . count($withAddress) . ' people carry the same address, '
                    . 'so this is one household — set exactly one of them as Head.',
            ];
        }

        return [
            (int) $withAddress[0]['row'],
            'Family ' . $familyNo . ' has no Head, and ' . count($distinct) . ' different addresses appear. '
                . 'This looks like two households sharing one QR — give each household its own QR, then set one Head in each.',
        ];
    }

    /**
     * QR-29 / fingerprint: one QR must be one household. Flags a group whose rows carry
     * more than one non-blank barangay or address (the signature of a copy-pasted block
     * or an off-by-one row shift). Surname is deliberately NOT part of the fingerprint —
     * a real household legitimately holds mixed surnames.
     *
     * @param list<array{row: int, data: array<string, string>}> $rows
     */
    private function checkFingerprint(string $familyNo, array $rows): void
    {
        $barangays = [];
        $addresses = [];

        foreach ($rows as $entry) {
            $barangay = $this->normalizeText((string) ($entry['data']['barangay'] ?? ''));
            $address  = $this->normalizeText((string) ($entry['data']['address'] ?? ''));

            if ($barangay !== '') {
                $barangays[$barangay] = true;
            }

            if ($address !== '') {
                $addresses[$address] = true;
            }
        }

        if (count($barangays) > 1 || count($addresses) > 1) {
            $this->addError($rows[0]['row'], $familyNo, 'FP-ADDR', 'address',
                'Family ' . $familyNo . ' has rows with different addresses or barangays. One QR Number must be one household — check for a copied block or a shifted row.');
        }
    }

    /**
     * QR-30: a family's rows should sit next to each other. Non-contiguous rows are a
     * warning (a sort/paste accident) — informational, does not block the import.
     *
     * @param list<array{row: int, data: array<string, string>}> $rows
     */
    private function checkContiguity(string $familyNo, array $rows): void
    {
        if (count($rows) < 2) {
            return;
        }

        $nums = array_map(static fn (array $entry): int => (int) $entry['row'], $rows);

        if (max($nums) - min($nums) + 1 !== count($nums)) {
            $this->addError(min($nums), $familyNo, 'QR-CONTIG', null,
                'Family ' . $familyNo . ' rows are not next to each other. This can happen after sorting or pasting — check the grouping.', 'warning');
        }
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

        $this->requireField($row, $familyNo, 'firstname', 'First name', $firstName);
        $this->requireField($row, $familyNo, 'lastname', 'Last name', $lastName);

        $birthday = $this->validateBirthday($row, $familyNo, (string) ($data['birthday'] ?? ''), $isHead);
        $sex      = $this->validateSex($row, $familyNo, (string) ($data['sex'] ?? ''), $isHead);

        $civilStatus = $this->fullValueFromCode((string) ($data['civilstatus'] ?? ''), FamilyExcelTemplate::CIVIL_STATUS_CODES);
        $education   = $this->fullValueFromCode((string) ($data['education'] ?? ''), FamilyExcelTemplate::EDUCATION_CODES);

        if ($isHead) {
            $this->requireField($row, $familyNo, 'civilstatus', 'Civil status', $civilStatus);
            $this->requireField($row, $familyNo, 'education', 'Education', $education);
            $this->requireField($row, $familyNo, 'job', 'Job', (string) ($data['job'] ?? ''));
            $this->requireField($row, $familyNo, 'address', 'Address', (string) ($data['address'] ?? ''));
            $this->requireField($row, $familyNo, 'barangay', 'Barangay', (string) ($data['barangay'] ?? ''));
            // Barangay has no "Other" option — it must be one of the official barangays
            // (tolerant match). A mismatch is a warning: it still imports as typed.
            $this->validateBarangay($row, $familyNo, (string) ($data['barangay'] ?? ''));
        } else {
            $this->requireField($row, $familyNo, 'relationship', 'Relationship', (string) ($data['relationship'] ?? ''));
        }

        $income    = $this->resolveIncome($row, $familyNo, (string) ($data['monthlyincome'] ?? ''), $isHead, $incomeByLabel);
        $sectorIds = $this->mapSectors($entry, $familyNo, $sectorByCode);

        // Contact number (optional): warn if present and not 09 + 11 digits.
        $this->validateContact($row, $familyNo, (string) ($data['contactnumber'] ?? ''));
        // Suffix (optional): normalise "Jr."->"Jr" / map "the 3rd"->"III"; an unmappable
        // suffix is left blank (so the DB enum insert can't fail) with a warning.
        $suffix = $this->validateSuffix($row, $familyNo, (string) ($data['suffix'] ?? ''));

        $firstClean  = MemberFieldNormalizer::cleanName($firstName);
        $middleClean = MemberFieldNormalizer::cleanName((string) ($data['middlename'] ?? ''));
        $lastClean   = MemberFieldNormalizer::cleanName($lastName);
        $civilValue  = MemberFieldNormalizer::nullableText($civilStatus);
        $contact     = MemberFieldNormalizer::nullableText((string) ($data['contactnumber'] ?? ''));
        $religion    = MemberFieldNormalizer::nullableText((string) ($data['religion'] ?? ''));

        // Guard the varchar column limits so an over-long value can't fail or silently
        // truncate at the write step. (Address / Job / Education / Relationship are TEXT.)
        $this->checkLength($row, $familyNo, 'firstname', 'First name', $firstClean, 100);
        $this->checkLength($row, $familyNo, 'lastname', 'Last name', $lastClean, 100);
        $this->checkLength($row, $familyNo, 'middlename', 'Middle name', $middleClean, 50);
        $this->checkLength($row, $familyNo, 'civilstatus', 'Civil status', $civilValue, 100);
        $this->checkLength($row, $familyNo, 'contactnumber', 'Contact number', $contact, 20);
        $this->checkLength($row, $familyNo, 'religion', 'Religion', $religion, 100);

        return [
            'firstname'     => $firstClean,
            'middlename'    => $middleClean,
            'lastname'      => $lastClean,
            'suffix'        => $suffix,
            'birthday'      => $birthday,
            'civilstatus'   => $civilValue,
            'sex'           => $sex,
            'education'     => MemberFieldNormalizer::nullableText($education),
            'job'           => MemberFieldNormalizer::nullableText((string) ($data['job'] ?? '')),
            'Salary'        => MemberFieldNormalizer::moneyOrNull($income),
            'contactnumber' => $contact,
            'religion'      => $religion,
            'address'       => MemberFieldNormalizer::combineAddressBarangay(
                (string) ($data['address'] ?? ''),
                (string) ($data['barangay'] ?? '')
            ),
            'relationship'  => $isHead ? 'Head' : (MemberFieldNormalizer::nullableText((string) ($data['relationship'] ?? '')) ?? 'Member'),
            'sectorID'      => $sectorIds,
        ];
    }

    /** Blocks an over-long value that would fail or truncate at the DB write. */
    private function checkLength(int $row, string $familyNo, string $field, string $label, ?string $value, int $max): void
    {
        if ($value !== null && mb_strlen($value) > $max) {
            $this->addError($row, $familyNo, 'LENGTH', $field,
                $label . ' is too long (' . mb_strlen($value) . ' characters; the maximum is ' . $max . ').');
        }
    }

    /** Records an error when a required field is blank. */
    private function requireField(int $row, string $familyNo, string $field, string $label, string $value): void
    {
        if (trim($value) === '') {
            $this->addError($row, $familyNo, 'REQUIRED', $field, $label . ' is required.');
        }
    }

    /**
     * Validates a birthday cell (MM-DD-YYYY, legacy YYYY-MM-DD accepted). Required for
     * heads. Returns the stored Y-m-d value or null.
     */
    private function validateBirthday(int $row, string $familyNo, string $value, bool $required): ?string
    {
        $value = trim($value);

        if ($value === '') {
            if ($required) {
                $this->addError($row, $familyNo, 'BDAY', 'birthday', 'Birthday is required (format MM-DD-YYYY).');
            }

            return null;
        }

        foreach (['m-d-Y', 'Y-m-d'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!' . $format, $value);

            if ($date !== false && $date->format($format) === $value) {
                $this->checkBirthdayRange($row, $familyNo, $date, $value);

                return $date->format('Y-m-d');
            }
        }

        $this->addError($row, $familyNo, 'BDAY', 'birthday', 'Birthday "' . $value . '" is not a valid date (use MM-DD-YYYY).');

        return null;
    }

    /**
     * Warns when a validly-formatted birthday is implausible — an age over 150 years, or a
     * date in the future (a mistyped year). 150 is well past the oldest human on record
     * (~122), so it can't flag a real person; it only catches gross typos. Warning only:
     * the date is still stored, but flagged. The 150-year floor tracks today automatically.
     */
    private function checkBirthdayRange(int $row, string $familyNo, \DateTimeImmutable $date, string $raw): void
    {
        $today  = new \DateTimeImmutable('today');
        $oldest = $today->modify('-150 years');

        if ($date > $today || $date < $oldest) {
            $this->addError($row, $familyNo, 'BDAY-RANGE', 'birthday',
                'Birthday "' . $raw . '" looks wrong (in the future, or over 150 years ago) — please check the year.', 'warning');
        }
    }

    /** Validates a sex cell against Male/Female. Required for heads. */
    private function validateSex(int $row, string $familyNo, string $value, bool $required): ?string
    {
        $value = trim($value);

        if ($value === '') {
            if ($required) {
                $this->addError($row, $familyNo, 'SEX', 'sex', 'Sex is required (Male or Female).');
            }

            return null;
        }

        if (strcasecmp($value, 'Male') === 0) {
            return 'Male';
        }

        if (strcasecmp($value, 'Female') === 0) {
            return 'Female';
        }

        $this->addError($row, $familyNo, 'SEX', 'sex', 'Sex "' . $value . '" must be Male or Female.');

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
                $this->addError($row, $familyNo, 'INCOME', 'monthlyincome', 'Monthly income is required.');
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

        $this->addError($row, $familyNo, 'INCOME', 'monthlyincome', 'Monthly income "' . $value . '" is not a valid bracket or number.');

        return null;
    }

    /**
     * Maps a row's comma-separated sector codes to IDs. An unrecognized code is filed
     * under the "Other Sectors" catch-all rather than aborting (mirrors the form).
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

            $this->addError($entry['row'], $familyNo, 'SERVICE', 'services', 'Unknown service code "' . $token . '" (see the Reference sheet).');
        }

        return array_values(array_unique($ids));
    }

    /**
     * Warns when a contact number is present but isn't 09 + 11 digits. Optional field,
     * so a blank cell is fine; punctuation (spaces/dashes) is ignored before the check.
     */
    private function validateContact(int $row, string $familyNo, string $value): void
    {
        $value = trim($value);

        if ($value === '') {
            return;
        }

        $digits = preg_replace('/[^0-9]/', '', $value);

        if (! preg_match('/^09[0-9]{9}$/', (string) $digits)) {
            $this->addError($row, $familyNo, 'CONTACT', 'contactnumber',
                'Contact number "' . $value . '" should start with 09 and be 11 digits.', 'warning');
        }
    }

    /**
     * Maps a name suffix to a valid dropdown value (Jr, Sr, I–V) so the DB enum is always
     * satisfied. Blank stays blank. A trivial cleanup (case / trailing dot) is applied
     * silently; a real change ("Junior" -> "Jr", "the 3rd" -> "III") is coerced with a
     * warning. Anything that maps to nothing is left blank (also enum-safe) with a warning.
     */
    private function validateSuffix(int $row, string $familyNo, string $raw): ?string
    {
        $value = trim($raw);

        if ($value === '') {
            return null;
        }

        // Lookup key: lowercase, drop dots, collapse spaces, drop a leading "the ".
        $key = trim((string) preg_replace('/\s+/', ' ', str_replace('.', '', mb_strtolower($value))));
        $key = (string) preg_replace('/^the\s+/', '', $key);

        $canonical = self::SUFFIX_ALIASES[$key] ?? null;

        if ($canonical !== null) {
            // Only warn when it was a real change, not just case or a trailing dot.
            if (strcasecmp(rtrim($value, '.'), $canonical) !== 0) {
                $this->addError($row, $familyNo, 'SUFFIX', 'suffix',
                    'Suffix "' . $value . '" was changed to "' . $canonical . '" (the matching dropdown option).', 'warning');
            }

            return $canonical;
        }

        $this->addError($row, $familyNo, 'SUFFIX', 'suffix',
            'Suffix "' . $value . '" is not a valid option (Jr, Sr, I–V) — it will be left blank.', 'warning');

        return null;
    }

    /**
     * Warns when a head's barangay isn't one of the official Biñan barangays. The match is
     * tolerant (case, ñ, dots and the "(...)" alias are ignored) so "Biñan"/"Sto. Tomas"
     * still pass; only a genuine non-barangay is flagged. Warning — imports as typed.
     */
    private function validateBarangay(int $row, string $familyNo, string $value): void
    {
        $value = trim($value);

        if ($value === '') {
            return;
        }

        if (! isset($this->barangayLookup()[$this->normalizeBarangay($value)])) {
            $this->addError($row, $familyNo, 'BRGY', 'barangay',
                'Barangay "' . $value . '" is not an official Biñan barangay — please check the spelling.', 'warning');
        }
    }

    /**
     * QR-31 refinement: warns when two rows look like the SAME person — identical first +
     * middle + last + suffix + birthday AND the same household address (members inherit the
     * head's). Never skipped or blocked, so a genuine coincidence still imports. Only runs
     * per family with exactly one head (address is unambiguous there).
     *
     * @param array<int|string, list<array{row: int, data: array<string,string>}>> $groups
     */
    private function checkDuplicatePersons(array $groups): void
    {
        $byKey = [];

        foreach ($groups as $qr => $rows) {
            $head = null;
            $headCount = 0;

            foreach ($rows as $entry) {
                if (strcasecmp(trim((string) ($entry['data']['relationship'] ?? '')), 'Head') === 0) {
                    $head = $entry;
                    $headCount++;
                }
            }

            if ($headCount !== 1) {
                continue;
            }

            $addressKey = $this->normalizeText((string) ($head['data']['address'] ?? ''))
                . '|' . $this->normalizeText((string) ($head['data']['barangay'] ?? ''));

            foreach ($rows as $entry) {
                $data  = $entry['data'];
                $first = $this->normalizeText((string) ($data['firstname'] ?? ''));
                $last  = $this->normalizeText((string) ($data['lastname'] ?? ''));

                // Blank names are already flagged REQUIRED; don't treat them as dup matches.
                if ($first === '' || $last === '') {
                    continue;
                }

                $key = implode('|', [
                    $first,
                    $this->normalizeText((string) ($data['middlename'] ?? '')),
                    $last,
                    $this->normalizeText(str_replace('.', '', (string) ($data['suffix'] ?? ''))),
                    trim((string) ($data['birthday'] ?? '')),
                    $addressKey,
                ]);

                $byKey[$key][] = ['row' => (int) $entry['row'], 'qr' => (string) $qr];
            }
        }

        foreach ($byKey as $hits) {
            if (count($hits) < 2) {
                continue;
            }

            $allRows = array_map(static fn (array $h): int => $h['row'], $hits);

            foreach ($hits as $hit) {
                $others = array_values(array_filter($allRows, static fn (int $r): bool => $r !== $hit['row']));

                $this->addError($hit['row'], $hit['qr'], 'DUP-PERSON', null,
                    'Same person (name, birthday, and address) also on row(s) ' . implode(', ', $others) . '. Check this is not a duplicate.', 'warning');
            }
        }
    }

    // -- lookups + helpers -----------------------------------------------------

    /**
     * [UPPER shortcode => sectorID] of active sectors, with OTHERS/OTHER aliased to the
     * catch-all sector. Cached for re-validation.
     */
    private function sectorCodeMap(): array
    {
        if ($this->sectorByCode !== null) {
            return $this->sectorByCode;
        }

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

        return $this->sectorByCode = $map;
    }

    /** [UPPER shortcode => serviceID] of active services. Cached for re-validation. */
    private function serviceCodeMap(): array
    {
        if ($this->serviceByCode !== null) {
            return $this->serviceByCode;
        }

        $map = [];

        foreach ((new ServiceModel())->getActive() as $service) {
            $code = strtoupper(trim((string) ($service['shortcode'] ?? '')));
            $id   = (int) ($service['serviceID'] ?? 0);

            if ($code !== '' && $id > 0) {
                $map[$code] = $id;
            }
        }

        return $this->serviceByCode = $map;
    }

    /** [lowercase bracket label => stored numeric value]. Cached for re-validation. */
    private function incomeLabelMap(): array
    {
        if ($this->incomeByLabel !== null) {
            return $this->incomeByLabel;
        }

        $map = [];

        foreach ((new FamilyFormOptionsModel())->getOptions()['income_ranges'] ?? [] as $range) {
            $value = (string) ($range['value'] ?? '');
            $label = strtolower(trim((string) ($range['label'] ?? '')));

            if ($value !== '' && $label !== '') {
                $map[$label] = $value;
            }
        }

        return $this->incomeByLabel = $map;
    }

    /**
     * Translates a civil-status / education cell to its full stored value. Accepts a
     * bare code, a "CODE - Name" pick, or the full name (returned unchanged).
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

    /** Lowercased, whitespace-collapsed value for case/spacing-insensitive comparison. */
    private function normalizeText(string $value): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $value)));
    }

    /** [normalized barangay => true] for the official Biñan list. Built once. */
    private function barangayLookup(): array
    {
        if ($this->barangayLookup !== null) {
            return $this->barangayLookup;
        }

        $set = [];

        foreach (FamilyProfilingFormV2::barangays() as $barangay) {
            $set[$this->normalizeBarangay($barangay)] = true;
        }

        return $this->barangayLookup = $set;
    }

    /**
     * Folds a barangay to a comparable form: lowercase, ñ→n, the "(alias)" dropped,
     * "Sto./Sta." expanded to "Santo/Santa", punctuation removed. So "Biñan",
     * "Sto. Tomas" and "Santo Tomas (Calabuso)" all reduce to the same key.
     */
    private function normalizeBarangay(string $value): string
    {
        $s = mb_strtolower(trim($value));
        $s = strtr($s, ['ñ' => 'n']);
        $s = (string) preg_replace('/\([^)]*\)/', ' ', $s);
        $s = strtr($s, ['sto.' => 'santo', 'sto ' => 'santo ', 'sta.' => 'santa', 'sta ' => 'santa ']);
        $s = (string) preg_replace('/[^a-z0-9 ]/', ' ', $s);

        return trim((string) preg_replace('/\s+/', ' ', $s));
    }

    /** @return list<string> Non-empty, trimmed tokens from a comma-separated cell. */
    private function splitList(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        $tokens = array_map('trim', explode(',', $value));

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

    /** True when a merge range (e.g. "A3:A6" or "A3") intersects the given column letter. */
    private function rangeTouchesColumn(string $range, string $letter): bool
    {
        [$start, $end] = array_pad(explode(':', $range, 2), 2, $range);

        $startCol = Coordinate::columnIndexFromString(preg_replace('/[0-9]+/', '', $start) ?: 'A');
        $endCol   = Coordinate::columnIndexFromString(preg_replace('/[0-9]+/', '', $end) ?: 'A');
        $target   = Coordinate::columnIndexFromString($letter);

        return $target >= min($startCol, $endCol) && $target <= max($startCol, $endCol);
    }

    /** Highest row number in a merge range (e.g. "A1:B1" -> 1, "A3" -> 3). */
    private function rangeMaxRow(string $range): int
    {
        [$start, $end] = array_pad(explode(':', $range, 2), 2, $range);

        return max(
            (int) preg_replace('/[^0-9]/', '', $start),
            (int) preg_replace('/[^0-9]/', '', $end)
        );
    }

    /** Builds a hard parse-failure bundle carrying one file-level blocking error. */
    private function parseFailure(string $message): array
    {
        return [
            'ok'     => false,
            'rows'   => [],
            'errors' => [$this->makeError(null, '', 'FILE', null, $message)],
        ];
    }

    /** Builds an error record without pushing it onto the instance list. */
    private function makeError(?int $sheetRow, string $familyNo, string $code, ?string $field, string $message, string $severity = 'blocking'): array
    {
        return [
            'sheetRow' => $sheetRow,
            'familyNo' => $familyNo,
            'field'    => $field,
            'code'     => $code,
            'message'  => $message,
            'severity' => $severity,
        ];
    }

    private function addError(?int $sheetRow, string $familyNo, string $code, ?string $field, string $message, string $severity = 'blocking'): void
    {
        $this->errors[] = $this->makeError($sheetRow, $familyNo, $code, $field, $message, $severity);
    }

    /** Counts errors of a given severity. @param list<array> $errors */
    private function tally(array $errors, string $severity): int
    {
        return count(array_filter($errors, static fn (array $e): bool => ($e['severity'] ?? 'blocking') === $severity));
    }
}
