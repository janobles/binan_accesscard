<?php

namespace App\Models\Lookups;

use App\Models\Concerns\ModelQueryHelpers;
use App\Models\Concerns\LookupModelTrait;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Model;

/**
 * Manages the citizen sectors used for categorizing members. Shared CRUD and
 * management-search behaviour lives in LookupModelTrait; this class adds the
 * sector-specific catalog, shortcode and cascade-archival logic.
 */
class SectorModel extends Model
{
    use LookupModelTrait;
    use ModelQueryHelpers;

    protected $table = 'sector';
    protected $primaryKey = 'sectorID';
    protected $returnType = 'array';
    protected $allowedFields = ['shortcode', 'categoryID', 'name', 'description'];
    protected $useTimestamps = false;

    /** Columns the Sector management search box matches. */
    protected function lookupSearchColumns(): array
    {
        return ['name', 'shortcode', 'description'];
    }

    /** Sector management list order: Name first, then shortcode/id for stability. */
    protected function applyLookupOrder(BaseBuilder $builder): void
    {
        $this->applyNameFirstOrder($builder, ['shortcode', $this->primaryKey]);
    }

    /**
     * Fetch active sectors from the read-only view used by lookups and forms.
     */
    public function getActive(): array
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
        $ids = $this->positiveUniqueIds($ids);

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
        return $this->columnValueExists('shortcode', $shortcode, $excludeId);
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
