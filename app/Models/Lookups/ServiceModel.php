<?php

namespace App\Models\Lookups;

use App\Models\Concerns\LookupModelTrait;
use App\Models\Concerns\NormalizesIds;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Model;

/**
 * Manages the `services` lookup table: admin CRUD/archival (create, update, find,
 * getActive) plus the per-batch eligibility lookups used by the family form and
 * search. Shared CRUD and management-search behaviour lives in LookupModelTrait.
 * The single model for the `services` table across the admin lookup screens and
 * family flows.
 */
class ServiceModel extends Model
{
    use LookupModelTrait;
    use NormalizesIds;

    protected $table = 'services';
    protected $primaryKey = 'serviceID';
    protected $returnType = 'array';
    protected $allowedFields = ['serviceID', 'shortcode', 'categoryID', 'sectorID', 'name', 'description'];
    protected $useAutoIncrement = true;
    protected $useTimestamps = false;

    /** Columns the Services management search box matches. */
    protected function lookupSearchColumns(): array
    {
        return array_values(array_filter([$this->codeColumn(), 'name', 'description']));
    }

    /**
     * A service's grouping is a key (categoryID or sectorID) since V22, so a
     * keyword that names a category or a sector is resolved to ids here rather
     than matched as text on the row.
     */
    protected function applyLookupKeywordExtras(BaseBuilder $builder, string $keyword, bool $isFirst): void
    {
        $categoryIds = $this->groupIdsMatching('category', 'categoryID', $keyword);
        $sectorIds   = $this->groupIdsMatching('sector', 'sectorID', $keyword);

        foreach ([['categoryID', $categoryIds], ['sectorID', $sectorIds]] as [$column, $ids]) {
            if ($ids === []) {
                continue;
            }

            if ($isFirst) {
                $builder->whereIn($column, $ids);
                $isFirst = false;

                continue;
            }

            $builder->orWhereIn($column, $ids);
        }
    }

    /**
     * Ids in a grouping table whose name (or code/shortcode) contains $keyword.
     *
     * @return list<int>
     */
    private function groupIdsMatching(string $table, string $idColumn, string $keyword): array
    {
        if (! $this->db->tableExists($table)) {
            return [];
        }

        $codeColumn = $this->db->fieldExists('shortcode', $table) ? 'shortcode' : 'code';

        $rows = $this->db->table($table)
            ->select($idColumn)
            ->groupStart()
            ->like('name', $keyword)
            ->orLike($codeColumn, $keyword)
            ->groupEnd()
            ->get()
            ->getResultArray();

        return array_map(static fn (array $row): int => (int) $row[$idColumn], $rows);
    }

    /**
     * Grouping label per service row, so every view keeps reading `category`
     * while the table stores the key. A service is grouped by a standalone
     * category (FA/SWPS/EDA) or by a sector, which is its own category once
     * categories:dedupe-sectors has run.
     */
    private function groupNames(): array
    {
        $names = ['category' => [], 'sector' => []];

        foreach ($names as $table => $_) {
            if (! $this->db->tableExists($table)) {
                continue;
            }

            $idColumn = $table === 'category' ? 'categoryID' : 'sectorID';

            foreach ($this->db->table($table)->select($idColumn . ', name')->get()->getResultArray() as $row) {
                $names[$table][(int) $row[$idColumn]] = (string) $row['name'];
            }
        }

        return $names;
    }

    /**
     * Some installed databases still use `code` for the service/program code.
     * The application exposes it as `shortcode` so views/controllers stay stable.
     */
    protected function normalizeLookupRows(array $rows): array
    {
        $codeColumn = $this->codeColumn();
        $groupNames = $rows === [] ? ['category' => [], 'sector' => []] : $this->groupNames();

        return array_map(static function (array $row) use ($codeColumn, $groupNames): array {
            if ($codeColumn !== null && ! array_key_exists('shortcode', $row) && array_key_exists($codeColumn, $row)) {
                $row['shortcode'] = $row[$codeColumn];
            }

            // Views and the family form group services by this label; it is
            // resolved from whichever key the row carries.
            $categoryId = (int) ($row['categoryID'] ?? 0);
            $sectorId   = (int) ($row['sectorID'] ?? 0);

            $row['category'] = $groupNames['category'][$categoryId]
                ?? $groupNames['sector'][$sectorId]
                ?? '';

            return $row;
        }, $rows);
    }

    /**
     * Maps write payloads to the actual database columns in the current schema,
     * including the grouping: callers (the Services modal, the importer) still
     * submit a `category` label, and it is resolved here to the one key that
     * matches - categoryID for a standalone category, sectorID when the label
     * names a sector. A label matching neither is rejected by the CHECK
     * constraint rather than saved ungrouped.
     */
    public function dataForCurrentSchema(array $data): array
    {
        $codeColumn = $this->codeColumn();

        if (array_key_exists('category', $data)) {
            $group = $this->resolveGroupKey((string) $data['category']);
            unset($data['category']);
            $data = array_merge($data, $group);
        }

        if ($codeColumn !== null && $codeColumn !== 'shortcode' && array_key_exists('shortcode', $data)) {
            $data[$codeColumn] = $data['shortcode'];
            unset($data['shortcode']);
        }

        if ($codeColumn === null) {
            unset($data['shortcode']);
        }

        return array_filter(
            $data,
            fn (string $column): bool => $this->db->fieldExists($column, $this->table),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * The key a grouping label resolves to: ['categoryID' => id] or
     * ['sectorID' => id], and ['categoryID' => null, 'sectorID' => null] when it
     * matches neither. Matching folds case and whitespace, the same way
     * services:link-categories matched the labels this replaced.
     *
     * @return array{categoryID?: int|null, sectorID?: int|null}
     */
    public function resolveGroupKey(string $label): array
    {
        $key = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $label)), 'UTF-8');

        if ($key === '') {
            return ['categoryID' => null, 'sectorID' => null];
        }

        foreach ([['category', 'categoryID', 'code'], ['sector', 'sectorID', 'shortcode']] as [$table, $idColumn, $codeColumn]) {
            if (! $this->db->tableExists($table)) {
                continue;
            }

            $column = $this->db->fieldExists($codeColumn, $table) ? $codeColumn : 'code';

            foreach ($this->db->table($table)->select($idColumn . ', name, ' . $column)->get()->getResultArray() as $row) {
                $name = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', (string) $row['name'])), 'UTF-8');
                $code = mb_strtolower(trim((string) $row[$column]), 'UTF-8');

                if ($key === $name || $key === $code) {
                    return [$idColumn => (int) $row[$idColumn]];
                }
            }
        }

        return ['categoryID' => null, 'sectorID' => null];
    }

    private function codeColumn(): ?string
    {
        if ($this->db->fieldExists('shortcode', $this->table)) {
            return 'shortcode';
        }

        if ($this->db->fieldExists('code', $this->table)) {
            return 'code';
        }

        return null;
    }

    /**
     * True if another active service already uses this shortcode (case-insensitive),
     * optionally excluding one serviceID (the row being edited). Guards code uniqueness.
     */
    public function shortcodeExists(string $shortcode, ?int $exceptServiceId = null): bool
    {
        $shortcode = trim($shortcode);

        if ($shortcode === '' || ! $this->db->tableExists($this->table)) {
            return false;
        }

        $codeColumn = $this->codeColumn();

        if ($codeColumn === null) {
            return false;
        }

        $builder = $this->db->table($this->table)
            ->where('UPPER(' . $codeColumn . ')', strtoupper($shortcode));

        if ($this->db->fieldExists('dt_deleted', $this->table)) {
            $builder->where('dt_deleted IS NULL', null, false);
        }

        if ($exceptServiceId !== null) {
            $builder->where('serviceID !=', $exceptServiceId);
        }

        return $builder->countAllResults() > 0;
    }

    /**
     * All current, active service shortcodes, uppercased and trimmed. Used by
     * the modal's client-side duplicate check (see services-modal.js). Only
     * active rows, matching shortcodeExists()'s dt_deleted filter, so the
     * client list matches exactly what the server will actually reject.
     */
    public function existingShortcodes(): array
    {
        $codeColumn = $this->codeColumn();

        if ($codeColumn === null || ! $this->hasTable()) {
            return [];
        }

        $builder = $this->db->table($this->table)->select($codeColumn);

        if ($this->db->fieldExists('dt_deleted', $this->table)) {
            $builder->where('dt_deleted IS NULL', null, false);
        }

        $rows = $builder->get()->getResultArray();

        return array_values(array_unique(array_filter(array_map(
            static fn (array $row): string => strtoupper(trim((string) ($row[$codeColumn] ?? ''))),
            $rows
        ))));
    }

    /** Services management list order: grouped, then by ID within the group. */
    protected function applyLookupOrder(BaseBuilder $builder): void
    {
        $builder->orderBy('categoryID', 'ASC')
            ->orderBy('sectorID', 'ASC')
            ->orderBy('serviceID', 'ASC');
    }

    /**
     * Suggested next service shortcode for a code prefix, e.g. 'B' => 'B4' when
     * B1..B3 already exist, or 'EDA' => 'EDA10'. Scans every existing service
     * shortcode (INCLUDING archived, so numbers are never reused) that is exactly
     * this prefix followed by digits, and returns prefix.(max+1). A prefix with no
     * numbered services yet returns prefix.'1'. Drives the Add-Program modal's
     * category-driven code auto-fill (public/assets/js/dashboard/services-modal.js);
     * the prefix comes from the selected sector's shortcode or category's code, so it
     * stays correct as workers add new sectors/categories/services.
     */
    public function nextCodeForPrefix(string $prefix): string
    {
        $prefix = strtoupper(trim($prefix));

        if ($prefix === '' || ! $this->db->tableExists($this->table)) {
            return '';
        }

        $highest = 0;

        $codeColumn = $this->codeColumn();

        if ($codeColumn === null) {
            return '';
        }

        foreach ($this->db->table($this->table)->select($codeColumn)->get()->getResultArray() as $row) {
            $code = strtoupper(trim((string) ($row['shortcode'] ?? '')));
            if ($code === '') {
                $code = strtoupper(trim((string) ($row[$codeColumn] ?? '')));
            }

            if (preg_match('/^([A-Z]+)(\d+)$/', $code, $matches) !== 1 || $matches[1] !== $prefix) {
                continue;
            }

            $highest = max($highest, (int) $matches[2]);
        }

        return $prefix . ($highest + 1);
    }

    /**
     * Fetch active (non-archived) services from the read-only view (or the base
     * table filtered by dt_deleted). Frontend: the family form's service options.
     */
    public function getActive(): array
    {
        $builder = $this->activeBuilder();

        if ($builder === null) {
            return [];
        }

        $rows = $builder
            ->orderBy('categoryID', 'ASC')
            ->orderBy('sectorID', 'ASC')
            ->orderBy('serviceID', 'ASC')
            ->get()
            ->getResultArray();

        return $this->normalizeLookupRows($rows);
    }

    /**
     * Fetch specific services by ID, including archived ones. Used by the family edit
     * form to keep showing services a member already has even after they were archived.
     *
     * @param list<int> $ids
     */
    public function getByIdsIncludingArchived(array $ids): array
    {
        $ids = $this->positiveUniqueIds($ids);

        if ($ids === [] || ! $this->db->tableExists($this->table)) {
            return [];
        }

        $rows = $this->db->table($this->table)
            ->whereIn($this->primaryKey, $ids)
            ->orderBy('categoryID', 'ASC')
            ->orderBy('sectorID', 'ASC')
            ->orderBy('serviceID', 'ASC')
            ->get()
            ->getResultArray();

        return $this->normalizeLookupRows($rows);
    }

    /**
     * Returns a query builder scoped to active (non-archived) services: prefers the
     * `view_services_active` DB view, else filters dt_deleted on the base table.
     * Returns null if neither exists. Shared by the active-read methods above.
     */
    private function activeBuilder(): ?BaseBuilder
    {
        if ($this->db->tableExists('view_services_active')) {
            return $this->db->table('view_services_active');
        }

        if (! $this->db->tableExists($this->table)) {
            return null;
        }

        $builder = $this->db->table($this->table);

        if ($this->db->fieldExists('dt_deleted', $this->table)) {
            $builder->where('dt_deleted IS NULL', null, false);
        }

        return $builder;
    }

    /**
     * Returns a [serviceID => name] map for the given IDs. Used by SearchModel to
     * label a member's assigned services in search results.
     */
    public function getNameMapByIds(array $serviceIds): array
    {
        $serviceIds = $this->naturalUniqueIds($serviceIds) ?? [];

        if ($serviceIds === []) {
            return [];
        }

        $rows = $this->select('serviceID, name')
            ->whereIn('serviceID', $serviceIds)
            ->findAll();

        $map = [];

        foreach ($rows as $row) {
            $id = (int) ($row['serviceID'] ?? 0);

            if ($id < 0) {
                continue;
            }

            $map[$id] = (string) ($row['name'] ?? '');
        }

        return $map;
    }

    /**
     * True if an active (non-archived) service with this ID exists. Used by
     * FamilyController::store() to validate selected services before linking them.
     */
    public function existsById(int $serviceId): bool
    {
        if (! $this->db->tableExists($this->table)) {
            return false;
        }

        $builder = $this->where($this->primaryKey, $serviceId);

        if ($this->db->fieldExists('dt_deleted', $this->table)) {
            $builder->where('dt_deleted', null);
        }

        return $builder
            ->countAllResults() > 0;
    }

    /**
     * Soft-archive every active service grouped under $name (a category or a
     * sector), stamping each with $archivedAt - the parent's own dt_deleted.
     * Sharing that exact timestamp is what lets restoreByCategoryArchivedAt()
     * later un-archive only the services THIS cascade retired, leaving
     * independently-archived ones alone. Returns the number archived.
     */
    public function archiveByCategory(string $name, string $archivedAt): int
    {
        $archivedAt = trim($archivedAt);
        $group      = $this->groupKeyFilter($name);

        if ($group === null || $archivedAt === '' || ! $this->db->tableExists($this->table) || ! $this->db->fieldExists('dt_deleted', $this->table)) {
            return 0;
        }

        $this->db->table($this->table)
            ->where($group[0], $group[1])
            ->where('dt_deleted IS NULL', null, false)
            ->set('dt_deleted', $archivedAt)
            ->update();

        return $this->db->affectedRows();
    }

    /**
     * [column, id] for a grouping label, or null when it names neither a
     * category nor a sector (in which case nothing should be cascaded).
     *
     * @return array{0: string, 1: int}|null
     */
    private function groupKeyFilter(string $name): ?array
    {
        $group = $this->resolveGroupKey($name);

        foreach (['categoryID', 'sectorID'] as $column) {
            if (! empty($group[$column])) {
                return [$column, (int) $group[$column]];
            }
        }

        return null;
    }

    /**
     * Reverse of archiveByCategory(): restore only the services whose category equals
     * $name AND whose dt_deleted exactly matches $archivedAt (the parent's archive
     * timestamp). The timestamp match ensures a category/sector restore un-archives
     * only the programs its own archive cascaded onto - services archived separately
     * (different timestamp) stay archived. Returns the number of services restored.
     */
    public function restoreByCategoryArchivedAt(string $name, string $archivedAt): int
    {
        $archivedAt = trim($archivedAt);
        $group      = $this->groupKeyFilter($name);

        if ($group === null || $archivedAt === '' || ! $this->db->tableExists($this->table) || ! $this->db->fieldExists('dt_deleted', $this->table)) {
            return 0;
        }

        $this->db->table($this->table)
            ->where($group[0], $group[1])
            ->where('dt_deleted', $archivedAt)
            ->set('dt_deleted', null)
            ->update();

        return $this->db->affectedRows();
    }

    /**
     * Inserts a service and returns its new serviceID, or false on failure.
     *
     * serviceID is AUTO_INCREMENT since V22, so the id comes from the DB. It used
     * to be allocated by hand as MAX(serviceID) + 1 under a FOR UPDATE lock,
     * because the column was the one lookup primary key without AUTO_INCREMENT.
     */
    public function insertWithNextId(array $data): int|false
    {
        $this->db->transStart();
        $inserted = $this->insert($this->dataForCurrentSchema($data)) !== false;
        $newId    = (int) $this->getInsertID();
        $this->db->transComplete();

        return ($inserted && $newId > 0 && $this->db->transStatus() !== false) ? $newId : false;
    }

    /**
     * True if any `member_services` row links to this service ID. Guards
     * archive/delete so in-use services cannot be removed.
     */
    public function isInUse(int $serviceId): bool
    {
        if (! $this->db->tableExists('member_services')) {
            return false;
        }

        return $this->db->table('member_services')
            ->where('serviceID', $serviceId)
            ->countAllResults() > 0;
    }
}
