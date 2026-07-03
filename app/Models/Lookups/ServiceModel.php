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
    protected $allowedFields = ['serviceID', 'shortcode', 'category', 'name', 'description'];
    protected $useAutoIncrement = false;
    protected $useTimestamps = false;

    /** Columns the Services management search box matches. */
    protected function lookupSearchColumns(): array
    {
        return array_values(array_filter([$this->codeColumn(), 'category', 'name', 'description']));
    }

    /**
     * Some installed databases still use `code` for the service/program code.
     * The application exposes it as `shortcode` so views/controllers stay stable.
     */
    protected function normalizeLookupRows(array $rows): array
    {
        $codeColumn = $this->codeColumn();

        return array_map(static function (array $row) use ($codeColumn): array {
            if ($codeColumn !== null && ! array_key_exists('shortcode', $row) && array_key_exists($codeColumn, $row)) {
                $row['shortcode'] = $row[$codeColumn];
            }

            return $row;
        }, $rows);
    }

    /** Maps write payloads to the actual database columns in the current schema. */
    public function dataForCurrentSchema(array $data): array
    {
        $codeColumn = $this->codeColumn();

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

    /** Services management list order: by category then ID. */
    protected function applyLookupOrder(BaseBuilder $builder): void
    {
        $builder->orderBy('category', 'ASC')
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
     * Returns the next service ID (max + 1). serviceID is not auto-increment here,
     * so create flows assign it explicitly.
     */
    public function nextServiceId(): int
    {
        if (! $this->hasTable()) {
            return 1;
        }

        $row = $this->selectMax($this->primaryKey, 'max_id')->first();

        return ((int) ($row['max_id'] ?? 0)) + 1;
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
            ->orderBy('category', 'ASC')
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
            ->orderBy('category', 'ASC')
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
     * Soft-archive every active service whose category label equals $name (exact
     * match on services.category, how services store their category), stamping each
     * with $archivedAt — the parent category/sector's own dt_deleted. Sharing that
     * exact timestamp is what lets restoreByCategoryArchivedAt() later un-archive only
     * the services THIS cascade retired, leaving independently-archived ones alone.
     * Returns the number of services archived.
     */
    public function archiveByCategory(string $name, string $archivedAt): int
    {
        $name       = trim($name);
        $archivedAt = trim($archivedAt);

        if ($name === '' || $archivedAt === '' || ! $this->db->tableExists($this->table) || ! $this->db->fieldExists('dt_deleted', $this->table)) {
            return 0;
        }

        $this->db->table($this->table)
            ->where('category', $name)
            ->where('dt_deleted IS NULL', null, false)
            ->set('dt_deleted', $archivedAt)
            ->update();

        return $this->db->affectedRows();
    }

    /**
     * Reverse of archiveByCategory(): restore only the services whose category equals
     * $name AND whose dt_deleted exactly matches $archivedAt (the parent's archive
     * timestamp). The timestamp match ensures a category/sector restore un-archives
     * only the programs its own archive cascaded onto — services archived separately
     * (different timestamp) stay archived. Returns the number of services restored.
     */
    public function restoreByCategoryArchivedAt(string $name, string $archivedAt): int
    {
        $name       = trim($name);
        $archivedAt = trim($archivedAt);

        if ($name === '' || $archivedAt === '' || ! $this->db->tableExists($this->table) || ! $this->db->fieldExists('dt_deleted', $this->table)) {
            return 0;
        }

        $this->db->table($this->table)
            ->where('category', $name)
            ->where('dt_deleted', $archivedAt)
            ->set('dt_deleted', null)
            ->update();

        return $this->db->affectedRows();
    }

}
