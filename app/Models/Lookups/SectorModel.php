<?php

namespace App\Models\Lookups;

use App\Support\FamilyProfilingFormV2;
use CodeIgniter\Model;

/**
 * Manages the citizen sectors used for categorizing members.
 */
class SectorModel extends Model
{
    protected $table = 'sector';
    protected $primaryKey = 'sectorID';
    protected $returnType = 'array';
    protected $allowedFields = ['shortcode', 'name', 'description'];
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

    public function hasTable(): bool
    {
        return $this->db->tableExists($this->table);
    }

    public function countSectors(): int
    {
        if (! $this->hasTable()) {
            return 0;
        }

        return $this->countAllResults();
    }

    public function getSectorOptions(): array
    {
        if (! $this->hasTable()) {
            return [];
        }

        return $this->orderBy('shortcode', 'ASC')->findAll();
    }

    public function getShortcodeOptions(): array
    {
        $fallback = array_values(array_filter(
            array_keys(FamilyProfilingFormV2::SECTOR_CATEGORIES),
            static fn (string $shortcode): bool => $shortcode !== 'OTHER'
        ));

        if (! $this->hasTable()) {
            return $fallback;
        }

        $rows = $this->select('shortcode')
            ->where('shortcode !=', '')
            ->orderBy('shortcode', 'ASC')
            ->findAll();
        $shortcodes = array_values(array_unique(array_filter(array_map(
            static fn (array $row): string => strtoupper(trim((string) ($row['shortcode'] ?? ''))),
            $rows
        ))));

        if ($shortcodes !== []) {
            return array_values(array_unique(array_merge($shortcodes, $fallback)));
        }

        $field = $this->db
            ->query("SHOW COLUMNS FROM `{$this->table}` LIKE 'shortcode'")
            ->getRowArray();
        $type = (string) ($field['Type'] ?? '');

        if (preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $type, $matches) !== false && $matches[1] !== []) {
            $enumValues = array_map(static fn (string $value): string => stripcslashes($value), $matches[1]);

            return array_values(array_unique(array_merge($fallback, $enumValues)));
        }

        return $fallback;
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
     * Suggested next code per category prefix, e.g. ['SC' => 'SC4', 'PWD' => 'PWD2'].
     *
     * Scans every existing code (including archived rows, so numbers are never
     * reused), takes the highest trailing number per alpha prefix, and adds one.
     * Prefixes come from FamilyProfilingFormV2::SECTOR_CATEGORIES (minus OTHER);
     * a prefix with no codes yet starts at 1. The sector modal uses this map to
     * auto-fill the Code field when a category is picked.
     */
    public function nextShortcodeMap(): array
    {
        $highestByPrefix = [];

        foreach ($this->existingShortcodes() as $code) {
            if (preg_match('/^([A-Z]+)(\d*)$/', $code, $matches) !== 1) {
                continue;
            }

            $prefix = $matches[1];
            $number = $matches[2] === '' ? 0 : (int) $matches[2];

            if (! isset($highestByPrefix[$prefix]) || $number > $highestByPrefix[$prefix]) {
                $highestByPrefix[$prefix] = $number;
            }
        }

        $map = [];

        foreach (array_keys(FamilyProfilingFormV2::SECTOR_CATEGORIES) as $prefix) {
            if ($prefix === 'OTHER') {
                continue;
            }

            $map[$prefix] = $prefix . (($highestByPrefix[$prefix] ?? 0) + 1);
        }

        return $map;
    }

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

    public function getSectorCatalog(array $sectorOptions = []): array
    {
        if ($sectorOptions === []) {
            $sectorOptions = $this->getSectorOptions();
        }

        $catalog = array_fill_keys(array_keys(FamilyProfilingFormV2::SECTOR_CATEGORIES), []);
        $catalog['OSCA'] = [];

        foreach ($sectorOptions as $sector) {
            $shortcode = $this->effectiveShortcode($sector);

            if ($shortcode === '') {
                continue;
            }

            if (str_starts_with($shortcode, 'PWD')) {
                $group = 'PWD';
            } elseif (str_starts_with($shortcode, 'SP')) {
                $group = 'SP';
            } elseif (str_starts_with($shortcode, 'SC') || str_starts_with($shortcode, 'OSCA') || str_starts_with($shortcode, 'OSWA')) {
                $group = 'SC';
            } elseif (str_starts_with($shortcode, 'B')) {
                $group = 'B';
            } else {
                $group = array_key_exists($shortcode, FamilyProfilingFormV2::SECTOR_CATEGORIES)
                    ? $shortcode
                    : 'OTHER';
            }

            $catalog[$group][] = [
                'category_label' => FamilyProfilingFormV2::SECTOR_CATEGORIES[$group] ?? 'Other Sectors',
                'sectorID' => (string) ($sector['sectorID'] ?? ''),
                'shortcode' => $shortcode,
                'name' => (string) ($sector['name'] ?? ''),
                'description' => (string) ($sector['description'] ?? ''),
            ];
        }

        return $catalog;
    }

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