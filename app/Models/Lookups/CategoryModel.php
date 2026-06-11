<?php

namespace App\Models\Lookups;

use CodeIgniter\Model;

/**
 * Manages the sector categories table (`category`). A category groups sectors
 * by a short alpha code (SC, PWD, LGBT, …) and a display name. Official
 * categories (is_official = 1) are seeded from the CSWD form and may be renamed
 * but never archived or deleted. Custom categories are fully managed from the
 * "Manage Categories" admin page. Sectors link here via `sector.categoryID`.
 */
class CategoryModel extends Model
{
    protected $table = 'category';
    protected $primaryKey = 'categoryID';
    protected $returnType = 'array';
    protected $allowedFields = ['code', 'name', 'description', 'is_official'];
    protected $useTimestamps = false;

    /** Single-instance categories whose first code is the bare prefix (no "1"). */
    private const SINGLE_INSTANCE = ['LGBT', 'OFW', 'IP', 'IDP', 'PDL'];

    /** True if the `category` table exists; callers guard queries with this. */
    public function hasTable(): bool
    {
        return $this->db->tableExists($this->table);
    }

    /**
     * All categories including archived, for the management table. Official
     * categories lead, then the rest by code.
     */
    public function getAllIncluding(): array
    {
        if (! $this->hasTable()) {
            return [];
        }

        return $this->db->table($this->table)
            ->orderBy('is_official', 'DESC')
            ->orderBy('code', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Active (non-archived) categories for dropdowns and the family-form
     * grouping order: official first, then the rest alphabetically by name.
     */
    public function getActive(): array
    {
        if (! $this->hasTable()) {
            return [];
        }

        $builder = $this->db->table($this->table);

        if ($this->db->fieldExists('dt_deleted', $this->table)) {
            $builder->where('dt_deleted IS NULL', null, false);
        }

        return $builder->orderBy('is_official', 'DESC')
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /** Find a single category row by ID (includes archived) for edit/guards. */
    public function find($id = null)
    {
        if (! $this->hasTable()) {
            return null;
        }

        return $this->db->table($this->table)
            ->where($this->primaryKey, (int) $id)
            ->get()
            ->getRowArray();
    }

    /** Find a single category row by its code (uppercased), or null. */
    public function findByCode(string $code): ?array
    {
        if (! $this->hasTable()) {
            return null;
        }

        return $this->db->table($this->table)
            ->where('code', strtoupper(trim($code)))
            ->get()
            ->getRowArray();
    }

    /** Insert a new category and return its insert ID. */
    public function create(array $data): int
    {
        $this->db->table($this->table)->insert($data);

        return (int) $this->db->insertID();
    }

    /** Update a category by ID. */
    public function update($id = null, $row = null): bool
    {
        return $this->db->table($this->table)
            ->where($this->primaryKey, (int) $id)
            ->update((array) $row);
    }

    /** Soft-archive a category by setting dt_deleted. */
    public function archive(int $id): bool
    {
        return $this->db->table($this->table)
            ->where($this->primaryKey, $id)
            ->set('dt_deleted', 'NOW()', false)
            ->update();
    }

    /** Restore a soft-archived category by clearing dt_deleted. */
    public function restore(int $id): bool
    {
        return $this->db->table($this->table)
            ->where($this->primaryKey, $id)
            ->set('dt_deleted', null)
            ->update();
    }

    /** Check for an existing code (uppercased), excluding one ID when editing. */
    public function codeExists(string $code, ?int $excludeId = null): bool
    {
        if (! $this->hasTable()) {
            return false;
        }

        $builder = $this->db->table($this->table)
            ->where('code', strtoupper(trim($code)));

        if ($excludeId !== null) {
            $builder->where($this->primaryKey . ' !=', $excludeId);
        }

        return $builder->countAllResults() > 0;
    }

    /** True if the category is official (seeded, protected from delete/archive). */
    public function isOfficial(int $id): bool
    {
        $row = $this->find($id);

        return $row !== null && (int) ($row['is_official'] ?? 0) === 1;
    }

    /** Count of non-archived sectors linked to this category. Guards archive/delete. */
    public function countSectors(int $id): int
    {
        if (! $this->db->tableExists('sector')) {
            return 0;
        }

        $builder = $this->db->table('sector')->where('categoryID', $id);

        if ($this->db->fieldExists('dt_deleted', 'sector')) {
            $builder->where('dt_deleted IS NULL', null, false);
        }

        return $builder->countAllResults();
    }

    /**
     * Suggested next sector shortcode for a category code, e.g. 'SC' => 'SC10'.
     * Scans every existing sector shortcode (incl. archived, so numbers are
     * never reused) sharing this prefix and returns prefix.(max+1). Single-
     * instance codes (LGBT, OFW, …) return the bare code until one exists.
     * Replaces the per-prefix logic previously in SectorModel::nextShortcodeMap().
     */
    public function nextSectorCodeFor(string $code): string
    {
        $code = strtoupper(trim($code));

        if ($code === '') {
            return '';
        }

        $highest = null;

        if ($this->db->tableExists('sector')) {
            $rows = $this->db->table('sector')->select('shortcode')->get()->getResultArray();

            foreach ($rows as $row) {
                $shortcode = strtoupper(trim((string) ($row['shortcode'] ?? '')));

                if (preg_match('/^([A-Z]+)(\d*)$/', $shortcode, $matches) !== 1) {
                    continue;
                }

                $prefix = ($matches[1] === 'OSCA' || $matches[1] === 'OSWA') ? 'SC' : $matches[1];

                if ($prefix !== $code) {
                    continue;
                }

                $number = $matches[2] === '' ? 0 : (int) $matches[2];

                if ($highest === null || $number > $highest) {
                    $highest = $number;
                }
            }
        }

        if ($highest === null && in_array($code, self::SINGLE_INSTANCE, true)) {
            return $code;
        }

        return $code . (($highest ?? 0) + 1);
    }
}
