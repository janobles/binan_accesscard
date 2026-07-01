<?php

namespace App\Models\Lookups;

use App\Models\Concerns\LookupModelTrait;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Model;

/**
 * Manages the sector categories table (`category`). A category groups sectors
 * by a short alpha code (SC, PWD, LGBT, …) and a display name. The standard CSWD
 * categories are seeded from the form, but every category — seeded or custom —
 * is fully managed from the "Manage Categories" admin page (rename, archive,
 * delete). Sectors link here via `sector.categoryID`. Shared CRUD and
 * management-search behaviour lives in LookupModelTrait.
 */
class CategoryModel extends Model
{
    use LookupModelTrait;

    protected $table = 'category';
    protected $primaryKey = 'categoryID';
    protected $returnType = 'array';
    protected $allowedFields = ['code', 'name'];
    protected $useTimestamps = false;

    /** Single-instance categories whose first code is the bare prefix (no "1"). */
    private const SINGLE_INSTANCE = ['LGBT', 'OFW', 'IP', 'IDP', 'PDL'];

    /** Columns the Manage Categories search box matches. */
    protected function lookupSearchColumns(): array
    {
        return ['name', 'code'];
    }

    /** Manage Categories list order: Name first, then code/id for stability. */
    protected function applyLookupOrder(BaseBuilder $builder): void
    {
        $this->applyNameFirstOrder($builder, ['code', $this->primaryKey]);
    }

    /**
     * All categories including archived, ordered by Name first for management use.
     */
    public function getAllIncluding(): array
    {
        return $this->getAllSortedByName(['code', $this->primaryKey]);
    }

    /**
     * Active (non-archived) categories for dropdowns and the family-form
     * grouping, ordered alphabetically by name.
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

        return $builder->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();
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

    /** Check for an existing code (uppercased), excluding one ID when editing. */
    public function codeExists(string $code, ?int $excludeId = null): bool
    {
        return $this->columnValueExists('code', strtoupper(trim($code)), $excludeId);
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
