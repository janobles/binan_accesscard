<?php

namespace App\Models\Lookups;

use App\Models\Concerns\LookupModelTrait;
use App\Models\Concerns\NormalizesIds;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Model;

/**
 * Manages the citizen sectors used for classifying members. After the Phase A
 * restructure a sector is a flat classification (SC, PWD, SP, B, LGBT, OFW, IP,
 * IDP, PDL, OTHER) — it is no longer linked to a category (programs moved into the
 * `services` table). Shared CRUD and management-search behaviour lives in
 * LookupModelTrait; this class adds the sector-specific catalog and shortcode logic.
 */
class SectorModel extends Model
{
    use LookupModelTrait;
    use NormalizesIds;

    protected $table = 'sector';
    protected $primaryKey = 'sectorID';
    protected $returnType = 'array';
    protected $allowedFields = ['shortcode', 'name', 'description'];
    protected $useTimestamps = false;

    /** Columns the Sector management search box matches. */
    protected function lookupSearchColumns(): array
    {
        return ['shortcode', 'name', 'description'];
    }

    /** Sector management list order: by ID. */
    protected function applyLookupOrder(BaseBuilder $builder): void
    {
        $builder->orderBy('sectorID', 'ASC');
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

    /**
     * Check for an existing name (case-insensitive), excluding one ID when
     * editing. Shortcodes are already guarded by shortcodeExists(); this closes
     * the gap where two sectors could share a name under different codes, which
     * would break cascade matching that relies on exact names.
     */
    public function nameExists(string $name, ?int $excludeId = null): bool
    {
        return $this->columnValueExists('LOWER(name)', strtolower(trim($name)), $excludeId);
    }

    /**
     * True if any active sector matches this code (shortcode) OR name (case-insensitive).
     * Used to keep sectors and service categories disjoint: a Manage-Categories entry may
     * not duplicate a sector (a sector already acts as its own service category).
     */
    public function activeCodeOrNameExists(string $code, string $name): bool
    {
        if (! $this->hasTable()) {
            return false;
        }

        $code = strtoupper(trim($code));
        $name = strtolower(trim($name));

        if ($code === '' && $name === '') {
            return false;
        }

        $builder = $this->db->table($this->table);

        if ($this->db->fieldExists('dt_deleted', $this->table)) {
            $builder->where('dt_deleted IS NULL', null, false);
        }

        $builder->groupStart();

        if ($code !== '') {
            $builder->where('UPPER(shortcode)', $code);
        }

        if ($name !== '') {
            $builder->orWhere('LOWER(name)', $name);
        }

        $builder->groupEnd();

        return $builder->countAllResults() > 0;
    }

    /**
     * True if any active service uses this sector's name as its category
     * (services.category stores the group name). Guards hard-deleting a sector that
     * still backs a service category.
     */
    public function usedAsServiceCategory(string $name): bool
    {
        $name = trim($name);

        if ($name === '' || ! $this->db->tableExists('services')) {
            return false;
        }

        $builder = $this->db->table('services')->where('category', $name);

        if ($this->db->fieldExists('dt_deleted', 'services')) {
            $builder->where('dt_deleted IS NULL', null, false);
        }

        return $builder->countAllResults() > 0;
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
     * Groups sectors into a single "Sectors" display group. Sectors are flat
     * classifications after the restructure, so there is only ever one group; the
     * grouped shape is kept because the family-form pickers (family-modal.php,
     * Lookups/picker.php) and the family-form JS iterate a [groupKey => sectors] map.
     */
    public function getSectorCatalog(array $sectorOptions = []): array
    {
        if ($sectorOptions === []) {
            $sectorOptions = $this->getSectorOptions();
        }

        $entries = [];

        foreach ($sectorOptions as $sector) {
            $shortcode = strtoupper(trim((string) ($sector['shortcode'] ?? '')));

            if ($shortcode === '') {
                continue;
            }

            $entry = [
                'category_label' => 'Sectors',
                'sectorID' => (string) ($sector['sectorID'] ?? ''),
                'shortcode' => $shortcode,
                'name' => (string) ($sector['name'] ?? ''),
                'description' => (string) ($sector['description'] ?? ''),
            ];

            if (! empty($sector['is_archived'])) {
                $entry['is_archived'] = true;
            }

            $entries[] = $entry;
        }

        return $entries === [] ? [] : ['Sectors' => $entries];
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
     * Resolves the shortcode to store: uses the submitted one, else the existing
     * row's code (on edit). Keeps codes stable when an edit form leaves the field blank.
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

        return '';
    }
}
