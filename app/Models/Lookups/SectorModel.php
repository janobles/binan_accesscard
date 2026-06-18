<?php

namespace App\Models\Lookups;

use CodeIgniter\Model;

/**
 * Manages the citizen sectors used for categorizing members.
 */
class SectorModel extends Model
{
    protected $table = 'sector';
    protected $primaryKey = 'sectorID';
    protected $returnType = 'array';
    protected $allowedFields = ['shortcode', 'categoryID', 'name', 'description'];
    protected $useTimestamps = false;

    /**
     * Fetch active sectors from the read-only view used by lookups and forms.
     */
    public function getAll(): array
    {
        if ($this->db->tableExists('view_sector_active')) {
            return $this->db->table('view_sector_active')
                ->orderBy('sectorID', 'ASC')
                ->get()
                ->getResultArray();
        }

        if (! $this->hasTable()) {
            return [];
        }

        $builder = $this->db->table($this->table);

        if ($this->db->fieldExists('dt_deleted', $this->table)) {
            $builder->where('dt_deleted IS NULL', null, false);
        }

        return $builder->orderBy('sectorID', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Fetch all sectors, including archived records, for admin management.
     */
    public function getAllIncluding(): array
    {
        if (! $this->hasTable()) {
            return [];
        }

        $builder = $this->db->table($this->table);

        if ($this->db->fieldExists('dt_deleted', $this->table)) {
            $builder->orderBy('dt_deleted', 'ASC');
        }

        return $builder->orderBy('sectorID', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Status-aware, keyword-filtered builder for the Sector management list.
     * Mirrors MemberModel::familySearchBuilder. $status: active|archived|all.
     * Searches the same columns the page advertises: shortcode, name, description.
     */
    private function lookupBuilder(?string $keyword, string $status)
    {
        $builder = $this->db->table($this->table);

        if ($this->db->fieldExists('dt_deleted', $this->table)) {
            if ($status === 'archived') {
                $builder->where('dt_deleted IS NOT NULL', null, false);
            } elseif ($status !== 'all') {
                $builder->where('dt_deleted IS NULL', null, false);
            }
        }

        $keyword = trim((string) $keyword);

        if ($keyword !== '') {
            $builder->groupStart()
                ->like('shortcode', $keyword)
                ->orLike('name', $keyword)
                ->orLike('description', $keyword)
                ->groupEnd();
        }

        return $builder;
    }

    /**
     * One page of sectors for the management list: status-filtered, keyword-matched,
     * active rows first (dt_deleted ASC) then by ID. $status: active|archived|all.
     */
    public function searchLookup(?string $keyword, string $status = 'active', int $limit = 50, int $offset = 0): array
    {
        if (! $this->hasTable()) {
            return [];
        }

        $builder = $this->lookupBuilder($keyword, $status);

        if ($this->db->fieldExists('dt_deleted', $this->table)) {
            $builder->orderBy('dt_deleted', 'ASC');
        }

        return $builder->orderBy('sectorID', 'ASC')
            ->limit(max(1, $limit), max(0, $offset))
            ->get()
            ->getResultArray();
    }

    /** Total sectors matching the keyword/status filter (for pagination). */
    public function countLookup(?string $keyword, string $status = 'active'): int
    {
        if (! $this->hasTable()) {
            return 0;
        }

        return $this->lookupBuilder($keyword, $status)->countAllResults();
    }

    /** Unfiltered active/archived totals for the status dropdown badges. */
    public function statusCounts(): array
    {
        return [
            'active'   => $this->countLookup(null, 'active'),
            'archived' => $this->countLookup(null, 'archived'),
        ];
    }

    /**
     * Find a single sector row by ID for edit forms.
     */
    public function find($id = null)
    {
        if (! $this->hasTable()) {
            return null;
        }

        return $this->db->table($this->table)
            ->where($this->primaryKey, $id)
            ->get()
            ->getRowArray();
    }

    /**
     * Create a new sector record and return its insert ID.
     */
    public function create(array $data): int
    {
        $this->db->table($this->table)->insert($data);

        return (int) $this->db->insertID();
    }

    /**
     * Update a sector record by ID.
     */
    public function update($id = null, $row = null): bool
    {
        return $this->db->table($this->table)
            ->where($this->primaryKey, (int) $id)
            ->update((array) $row);
    }

    /**
     * Soft-archive a sector by setting dt_deleted.
     */
    public function archive(int $id): bool
    {
        return $this->db->table($this->table)
            ->where($this->primaryKey, $id)
            ->set('dt_deleted', 'NOW()', false)
            ->update();
    }

    /**
     * Restore a soft-archived sector by clearing dt_deleted.
     */
    public function restore(int $id): bool
    {
        return $this->db->table($this->table)
            ->where($this->primaryKey, $id)
            ->set('dt_deleted', null)
            ->update();
    }

    /**
     * Cascade-archive the active sectors of a category, stamping each with the same
     * dt_deleted as the category so CategoryController::restore can match and restore
     * exactly this batch later. Returns the number of sectors archived.
     */
    public function archiveByCategory(int $categoryId, string $stampedAt): int
    {
        if (! $this->hasTable() || ! $this->db->fieldExists('dt_deleted', $this->table)) {
            return 0;
        }

        $this->db->table($this->table)
            ->where('categoryID', $categoryId)
            ->where('dt_deleted IS NULL', null, false)
            ->set('dt_deleted', $stampedAt)
            ->update();

        return $this->db->affectedRows();
    }

    /**
     * Restore the sectors that were cascade-archived together with their category,
     * i.e. those whose dt_deleted matches the category's archive timestamp. Sectors
     * archived independently keep a different timestamp and stay archived.
     */
    public function restoreByCategoryArchivedAt(int $categoryId, string $stampedAt): int
    {
        if ($stampedAt === '' || ! $this->hasTable() || ! $this->db->fieldExists('dt_deleted', $this->table)) {
            return 0;
        }

        $this->db->table($this->table)
            ->where('categoryID', $categoryId)
            ->where('dt_deleted', $stampedAt)
            ->set('dt_deleted', null)
            ->update();

        return $this->db->affectedRows();
    }

    /**
     * Fetch specific sectors by ID, including archived ones. Used by the family edit
     * form to keep showing sectors a member already has even after they were archived.
     *
     * @param list<int> $ids
     */
    public function getByIdsIncludingArchived(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));

        if ($ids === [] || ! $this->hasTable()) {
            return [];
        }

        return $this->db->table($this->table)
            ->whereIn($this->primaryKey, $ids)
            ->orderBy('sectorID', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Check for an existing shortcode, excluding one ID when editing.
     */
    public function shortcodeExists(string $shortcode, ?int $excludeId = null): bool
    {
        if (! $this->hasTable()) {
            return false;
        }

        $builder = $this->db->table($this->table)
            ->where('shortcode', $shortcode);

        if ($excludeId !== null) {
            $builder->where($this->primaryKey . ' !=', $excludeId);
        }

        return $builder->countAllResults() > 0;
    }

    /** True if the `sector` table exists; callers guard queries with this. */
    public function hasTable(): bool
    {
        return $this->db->tableExists($this->table);
    }

    /** Counts sectors for dashboard stats. */
    public function countSectors(): int
    {
        if (! $this->hasTable()) {
            return 0;
        }

        return $this->countAllResults();
    }

    /** All sectors ordered by shortcode; used to build form dropdowns/catalog. */
    public function getSectorOptions(): array
    {
        if (! $this->hasTable()) {
            return [];
        }

        return $this->orderBy('shortcode', 'ASC')->findAll();
    }

    /**
     * All current sector codes, uppercased and trimmed. Used by the modal's
     * client-side duplicate check (see public/assets/js/dashboard/sectors-modal.js).
     */
    public function existingShortcodes(): array
    {
        if (! $this->hasTable()) {
            return [];
        }

        $rows = $this->select('shortcode')->findAll();

        return array_values(array_unique(array_filter(array_map(
            static fn (array $row): string => strtoupper(trim((string) ($row['shortcode'] ?? ''))),
            $rows
        ))));
    }

    /**
     * Code => display-name map for sector categories, read from the `category`
     * table (active rows only). Drives the grouped sector headings in the family
     * form (via ViewFormatter::memberSectorGroups, which keys by shortcode prefix)
     * and any code-keyed label lookup.
     */
    public function categoryLabelMap(): array
    {
        $labels = [];

        foreach ((new CategoryModel())->getActive() as $category) {
            $code = strtoupper(trim((string) ($category['code'] ?? '')));

            if ($code !== '') {
                $labels[$code] = (string) ($category['name'] ?? $code);
            }
        }

        return $labels;
    }

    /**
     * Inserts or updates a sector, resolving a blank shortcode first. Used by
     * Lookups\SectorController::saveSector(). $sectorId null = insert.
     */
    public function saveSectorRecord(array $data, ?int $sectorId = null): bool
    {
        if (! $this->hasTable()) {
            return false;
        }

        $data['shortcode'] = $this->effectiveShortcode($data, $sectorId);

        if ($sectorId !== null) {
            return $this->update($sectorId, $data) !== false;
        }

        return $this->insert($data) !== false;
    }

    /**
     * Groups sectors into display categories using the linked `category` row
     * (sector.categoryID). The group key is the category code and the heading is
     * the category name. Sectors whose category is missing/archived fall back to
     * their shortcode prefix, or an "UNCATEGORIZED" bucket — they are never
     * dropped. Groups are ordered with active categories first (official before
     * custom, per CategoryModel::getActive), then any leftover buckets
     * alphabetically. Frontend: drives the grouped sector picker in the family form.
     */
    public function getSectorCatalog(array $sectorOptions = []): array
    {
        if ($sectorOptions === []) {
            $sectorOptions = $this->getSectorOptions();
        }

        $categoryModel = new CategoryModel();

        // categoryID => row, including archived so their sectors still get a label.
        $byId = [];
        foreach ($categoryModel->getAllIncluding() as $category) {
            $byId[(int) ($category['categoryID'] ?? 0)] = $category;
        }

        // The order active categories should appear in (official first).
        $orderedCodes = [];
        foreach ($categoryModel->getActive() as $category) {
            $code = strtoupper(trim((string) ($category['code'] ?? '')));

            if ($code !== '') {
                $orderedCodes[] = $code;
            }
        }

        $catalog = [];

        foreach ($sectorOptions as $sector) {
            $shortcode = $this->effectiveShortcode($sector);

            if ($shortcode === '') {
                continue;
            }

            $categoryId = (int) ($sector['categoryID'] ?? 0);
            $category = $byId[$categoryId] ?? null;

            if ($category !== null) {
                $group = strtoupper(trim((string) ($category['code'] ?? '')));
                $label = (string) ($category['name'] ?? $group);
            } else {
                // No/unknown category: bucket under the shortcode prefix.
                $group = $this->sectorPrefix($shortcode);
                $label = $group;
            }

            if ($group === '') {
                $group = 'UNCATEGORIZED';
                $label = 'Uncategorized';
            }

            $catalog[$group][] = [
                'category_label' => $label === '' ? $group : $label,
                'sectorID' => (string) ($sector['sectorID'] ?? ''),
                'shortcode' => $shortcode,
                'name' => (string) ($sector['name'] ?? ''),
                'description' => (string) ($sector['description'] ?? ''),
            ];
        }

        return $this->orderCatalog($catalog, $orderedCodes);
    }

    /**
     * Group key for a shortcode: its leading letters, with OSCA/OSWA folded into
     * SC. Falls back to the whole code if it has no leading letters. Used only as
     * a fallback for sectors with no linked category.
     */
    private function sectorPrefix(string $shortcode): string
    {
        $prefix = preg_match('/^([A-Z]+)/', $shortcode, $matches) === 1 ? $matches[1] : $shortcode;

        return ($prefix === 'OSCA' || $prefix === 'OSWA') ? 'SC' : $prefix;
    }

    /**
     * Reorders catalog groups so active categories lead (in the order from
     * CategoryModel::getActive — official before custom), with any leftover
     * buckets (uncategorized / archived-category prefixes) appended alphabetically.
     */
    private function orderCatalog(array $catalog, array $orderedCodes): array
    {
        $ordered = [];

        foreach ($orderedCodes as $code) {
            if (isset($catalog[$code])) {
                $ordered[$code] = $catalog[$code];
                unset($catalog[$code]);
            }
        }

        ksort($catalog);

        return $ordered + $catalog;
    }

    /**
     * Resolves the shortcode to store: uses the submitted one, else the existing
     * row's code (on edit), else infers SC1 for "Registered OSCA". Keeps codes
     * stable when a form leaves the field blank.
     */
    private function effectiveShortcode(array $sector, ?int $sectorId = null): string
    {
        $shortcode = strtoupper(trim((string) ($sector['shortcode'] ?? '')));

        if ($shortcode !== '') {
            return $shortcode;
        }

        if ($sectorId !== null) {
            $existing = $this->find($sectorId);
            $existingShortcode = strtoupper(trim((string) ($existing['shortcode'] ?? '')));

            if ($existingShortcode !== '') {
                return $existingShortcode;
            }
        }

        $name = strtoupper(trim((string) ($sector['name'] ?? '')));

        if (str_contains($name, 'REGISTERED OSCA')) {
            return 'SC1';
        }

        return '';
    }
}