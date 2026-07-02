<?php

namespace App\Models\Families;

use App\Libraries\SectorIds;
use App\Models\Concerns\MemberQueryFilters;
use App\Models\Concerns\NormalizesIds;
use App\Models\Concerns\RecordStatus;
use App\Models\Concerns\ResolvesSectorNames;
use CodeIgniter\Model;

/**
 * Manages family heads and family member records.
 */
class MemberModel extends Model
{
    use MemberQueryFilters;
    use NormalizesIds;
    use ResolvesSectorNames;

    public const VALIDATION_RULES = [
        'sectorID' => 'permit_empty|valid_sector_array',
        'firstname' => 'required|max_length[100]',
        'lastname' => 'required|max_length[100]',
        'middlename' => 'permit_empty|max_length[50]',
        'suffix' => 'permit_empty|max_length[20]',
        'birthday' => 'permit_empty|valid_date[Y-m-d]',
        'civilstatus' => 'permit_empty|max_length[100]',
        'sex' => 'permit_empty|in_list[Male,Female]',
        'education' => 'permit_empty|max_length[150]',
        'job' => 'permit_empty|max_length[150]',
        'contactnumber' => 'permit_empty|max_length[30]',
        'religion' => 'permit_empty|max_length[100]',
        'address' => 'permit_empty|max_length[255]',
        'barangay' => 'permit_empty|max_length[100]',
    ];

    protected $table = 'member';
    protected $primaryKey = 'memberID';
    protected $returnType = 'array';
    protected $allowedFields = [
        'memberID',
        'lastname',
        'firstname',
        'middlename',
        'suffix',
        'birthday',
        'civilstatus',
        'sex',
        'education',
        'job',
        'Salary',
        'contactnumber',
        'religion',
        'address',
        'barangay',
        'relationship',
        'headID',
        'sectorID',
    ];
    protected $useTimestamps = false;
    protected $validationRules = self::VALIDATION_RULES;
    protected $beforeInsert = ['normalizeSectorIdStorage'];
    protected $beforeUpdate = ['normalizeSectorIdStorage'];

    /** True if the `member` table exists; callers guard queries with this. */
    public function hasTable(): bool
    {
        return $this->db->tableExists($this->table);
    }

    /**
     * Confirms every table the family-save flow needs exists. FamilyController::store()
     * calls this up front and aborts with a clear message if the schema is incomplete.
     */
    public function hasRequiredFamilyTables(): bool
    {
        foreach (['member', 'sector', 'services', 'member_services', 'audit_trails'] as $table) {
            if (! $this->db->tableExists($table)) {
                return false;
            }
        }

        return true;
    }

    // Transaction wrappers used by FamilyController::store() so the head, members,
    // service links, and audit row all commit together or roll back as one unit.

    /** Opens a managed DB transaction. */
    public function beginTransaction(): void
    {
        $this->db->transStart();
    }

    /** Rolls back the current transaction after a save failure. */
    public function rollbackTransaction(): void
    {
        $this->db->transRollback();
    }

    /** Commits the current transaction (or rolls back if any query failed). */
    public function completeTransaction(): void
    {
        $this->db->transComplete();
    }

    /** Whether the just-completed transaction succeeded. */
    public function transactionStatus(): bool
    {
        return $this->db->transStatus();
    }

    /**
     * ENCODE hook. Registered as beforeInsert/beforeUpdate, this runs right
     * before a row is written and converts the sectorID array into its JSON
    * storage string ('[1,2,3]'). See App\Libraries\SectorIds::toStorage().
     */
    protected function normalizeSectorIdStorage(array $data): array
    {
        if (array_key_exists('sectorID', $data['data'] ?? [])) {
            $data['data']['sectorID'] = SectorIds::toStorage($data['data']['sectorID']);
        }

        return $data;
    }

    /**
     * Inserts a head-of-family row, where headID points to its own memberID.
     * Called by FamilyController::store(); returns the new memberID or false.
     */
    public function createHead(array $data): int|false
    {
        $data['memberID'] = $this->nextAutoIncrementId();
        $data['headID'] = $data['memberID'];
        $data['relationship'] = $data['relationship'] ?? 'Head';
        $data = $this->memberColumnPayload($data);

        if (! $this->insert($data)) {
            return false;
        }

        return (int) $data['memberID'];
    }

    /**
     * True when an ACTIVE head-of-family with the same name + birthday is already
     * on file. The Excel importer calls this to SKIP families that already exist
     * rather than inserting a duplicate. Comparison is case-insensitive on the
     * (already Title-cased) first/last name and exact on the stored Y-m-d birthday.
     *
     * Only live heads block a re-import: an archived (retired) family is treated as
     * gone, so re-importing it re-creates the record — matching the archive
     * grandfather semantics used elsewhere.
     */
    public function activeHeadExists(string $firstname, string $lastname, ?string $birthday): bool
    {
        if (! $this->hasTable()) {
            return false;
        }

        $builder = $this->db->table($this->table)
            ->where('member.memberID = member.headID', null, false)
            ->where('LOWER(member.firstname) = ' . $this->db->escape(mb_strtolower(trim($firstname))), null, false)
            ->where('LOWER(member.lastname) = ' . $this->db->escape(mb_strtolower(trim($lastname))), null, false);

        if ($birthday !== null && trim($birthday) !== '') {
            $builder->where('member.birthday', $birthday);
        } else {
            $builder->where('member.birthday IS NULL', null, false);
        }

        if ($this->db->fieldExists('dt_deleted', $this->table)) {
            $builder->where('member.dt_deleted IS NULL', null, false);
        }

        return $builder->countAllResults() > 0;
    }

    /**
     * Inserts a relative under an existing head (validating the head exists).
     * Called per member by FamilyController::store(); returns the new memberID
     * or false.
     */
    public function addFamilyMember(int $headId, array $data): int|false
    {
        $head = $this->find($headId);

        if ($head === null || (int) ($head['headID'] ?? 0) !== $headId) {
            return false;
        }

        $data['headID'] = $headId;
        $data['relationship'] = $data['relationship'] ?? 'Member';
        $data = $this->memberColumnPayload($data);

        if (! $this->insert($data)) {
            return false;
        }

        return (int) $this->getInsertID();
    }

    /**
     * Returns all members of one family (by head ID) with sector names resolved.
     * $status filters active/archived/all. Frontend: the family view/edit
     * screens via DashboardPageBuilder.
     */
    public function getFamilyMembers(int $headId, string $status = RecordStatus::ACTIVE): array
    {
        if (! $this->hasTable()) {
            return [];
        }

        $rows = $this->memberDashboardBuilder($status)
            ->where('member.headID', $headId)
            ->orderBy('member.memberID', 'ASC')
            ->get()
            ->getResultArray();

        return $this->withSectorNames($rows);
    }

    // FIRST (quick) search bar of the Manage Records tab. Lists family HEADS only.
    // $filters carries the Manage Records filter controls (sectorID + date); see
    // App\Libraries\DashboardPageBuilder::buildMemberListData() which supplies them.
    //
    // $orderKey/$orderDirection are an OPTIONAL, append-only addition used by the
    // server-side DataTables endpoint (FamilyController::dataTable) for column
    // sorting. When $orderKey is null the original ordering (newest first, by
    // memberID DESC) is preserved, so existing callers are unaffected.
    public function searchFamilies(?string $keyword = null, int $limit = 50, int $offset = 0, string $status = RecordStatus::ALL, array $filters = [], ?string $orderKey = null, string $orderDirection = 'asc'): array
    {
        if (! $this->hasTable()) {
            return [];
        }

        $limit = max(1, $limit);
        $offset = max(0, $offset);

        $builder = $this->familySearchBuilder($keyword, $status, $filters);
        $this->applyMemberOrder($builder, $orderKey, $orderDirection);
        $builder->limit($limit, $offset);

        return $this->withSectorNames($builder->get()->getResultArray());
    }

    /**
     * Applies a DataTables column sort to a member query, or the default
     * newest-first ordering when $orderKey is null/unrecognized. Column keys map
     * to the visible Manage Records columns: name (lastname, firstname), address,
     * birthday. Used only by the server-side DataTables path.
     */
    private function applyMemberOrder($builder, ?string $orderKey, string $orderDirection): void
    {
        $direction = strtolower(trim($orderDirection)) === 'desc' ? 'DESC' : 'ASC';

        switch ($orderKey) {
            case 'name':
                $builder->orderBy('member.lastname', $direction)
                    ->orderBy('member.firstname', $direction);
                return;
            case 'address':
                if ($this->memberFieldExists('address')) {
                    $builder->orderBy('member.address', $direction);
                    return;
                }
                break;
            case 'birthday':
                $builder->orderBy('member.birthday', $direction);
                return;
        }

        $builder->orderBy('member.memberID', 'DESC');
    }

    /**
     * Total count for the same query as searchFamilies(), used to drive the Manage
     * Records pagination controls on the frontend.
     */
    public function countSearchFamilies(?string $keyword = null, string $status = RecordStatus::ALL, array $filters = []): int
    {
        if (! $this->hasTable()) {
            return 0;
        }

        return $this->familySearchBuilder($keyword, $status, $filters)->countAllResults();
    }

    // Builds the head-only records query. $filters (optional) applies the Manage Records
    // filter controls: 'sectorID' (exact match inside the JSON array), 'barangay',
    // and 'date'
    // (single-day match on member.dt_created). Empty $filters = original behavior unchanged.
    private function familySearchBuilder(?string $keyword = null, string $status = RecordStatus::ALL, array $filters = [])
    {
        if ($status === '1') {
            $status = RecordStatus::ARCHIVED;
        } elseif ($status === '') {
            $status = RecordStatus::ACTIVE;
        }

        $status = in_array($status, [RecordStatus::ACTIVE, RecordStatus::ARCHIVED, RecordStatus::ALL], true) ? $status : RecordStatus::ALL;
        $builder = $this->memberDashboardBuilder($status)
            ->where('member.memberID = member.headID', null, false);

        if ($keyword !== null && trim($keyword) !== '') {
            $this->applyMemberKeyword($builder, trim($keyword), 'member.', ['religion', 'address', 'barangay'], 'member.sectorID');
        }

        $this->applyRecordFilters($builder, $filters);

        return $builder;
    }

    // Applies the Manage Records filter controls to a member query builder.
    // Connects to: family-list.php filter form -> DashboardPageBuilder -> here.
    private function applyRecordFilters($builder, array $filters): void
    {
        $this->applySectorIdFilter($builder, $filters['sectorID'] ?? [], 'member.sectorID');
        $this->applyBarangayFilter($builder, $filters['barangay'] ?? [], 'member.address', 'member.barangay');
        $this->applyDateRange($builder, 'member.dt_created', $filters);
    }

    /**
     * Returns the member IDs belonging to a family, used when re-syncing a
     * family's service assignments during an edit.
     */
    public function getFamilyMemberIds(int $headId): array
    {
        if (! $this->hasTable()) {
            return [];
        }

        $rows = $this->select('memberID')
            ->where('headID', $headId)
            ->findAll();

        return array_values(array_map(static fn (array $row): int => (int) ($row['memberID'] ?? 0), $rows));
    }

    /** Updates the head-of-family row during a family edit submission. */
    public function updateHead(int $headId, array $data): bool
    {
        $data['headID'] = $headId;
        $data['relationship'] = 'Head';
        $data = $this->memberColumnPayload($data);

        return $this->update($headId, $data) !== false;
    }

    /**
     * Hard-deletes all relatives of a family but keeps the head. Used when an edit
     * replaces the member list before re-inserting the submitted members.
     */
    public function deleteFamilyMembersExceptHead(int $headId): bool
    {
        return $this->where('headID', $headId)
            ->where('memberID !=', $headId)
            ->delete() !== false;
    }

    /** Soft-archives an entire family (Manage Records "archive" action). */
    public function archiveFamily(int $headId): bool
    {
        return $this->markFamilyDeleted($headId);
    }

    /** Restores a soft-archived family by clearing dt_deleted on all its rows. */
    public function restoreFamily(int $headId): bool
    {
        if (! $this->hasTable() || ! $this->db->fieldExists('dt_deleted', $this->table)) {
            return false;
        }

        return (bool) $this->db->table($this->table)
            ->where('headID', $headId)
            ->where('dt_deleted IS NOT NULL', null, false)
            ->update(['dt_deleted' => null]);
    }

    /** Shared soft-delete: stamps dt_deleted on a family's active rows. */
    private function markFamilyDeleted(int $headId): bool
    {
        if (! $this->hasTable() || ! $this->db->fieldExists('dt_deleted', $this->table)) {
            return false;
        }

        return (bool) $this->db->table($this->table)
            ->where('headID', $headId)
            ->where('dt_deleted IS NULL', null, false)
            ->update(['dt_deleted' => date('Y-m-d H:i:s')]);
    }

    /**
     * Reads the table's next AUTO_INCREMENT so a head can set its own memberID and
     * headID to the same value in one insert (head points at itself).
     */
    private function nextAutoIncrementId(): int
    {
        $row = $this->db->query("\n            SELECT AUTO_INCREMENT\n            FROM information_schema.TABLES\n            WHERE TABLE_SCHEMA = DATABASE()\n              AND TABLE_NAME = 'member'\n        ")->getRowArray();

        return (int) ($row['AUTO_INCREMENT'] ?? 1);
    }

    /**
     * Central query builder for member listings: selects the display columns,
     * left-joins the head's name, and applies the active/archived/all status
     * filter. Shared by the search, family, and detail queries.
     */
    private function memberDashboardBuilder(string $status = RecordStatus::ACTIVE)
    {
        $select = [
            'member.memberID',
            'member.lastname',
            'member.firstname',
            'member.middlename',
            'member.suffix',
            'member.birthday',
            'member.civilstatus',
            'member.sex',
            'member.education',
            'member.job',
            'member.Salary',
            'member.contactnumber',
            'member.relationship',
            'member.dt_created',
            'member.dt_updated',
            'member.dt_deleted',
            'member.headID',
            'member.sectorID',
            'head.firstname AS head_firstname',
            'head.lastname AS head_lastname',
        ];

        foreach (['religion', 'address', 'barangay'] as $field) {
            if ($this->memberFieldExists($field)) {
                $select[] = 'member.' . $field;
            }
        }

        $builder = $this->db->table('member')
            ->select($select)
            ->join('member head', 'head.memberID = member.headID', 'left');

        if ($this->db->fieldExists('dt_deleted', 'member')) {
            if ($status === RecordStatus::ARCHIVED) {
                $builder->where('member.dt_deleted IS NOT NULL', null, false);
            } elseif ($status !== RecordStatus::ALL) {
                $builder->where('member.dt_deleted IS NULL', null, false);
            }
        }

        return $builder;
    }

    /**
     * Drops any keys that aren't real `member` columns before an insert/update, so
     * the model tolerates schema differences between SQL-dump versions.
     */
    private function memberColumnPayload(array $data): array
    {
        if (! $this->hasTable()) {
            return $data;
        }

        return array_filter(
            $data,
            fn (mixed $value, string $field): bool => $this->memberFieldExists($field),
            ARRAY_FILTER_USE_BOTH
        );
    }

    /** True if a given column exists on the `member` table (schema-tolerance helper). */
    private function memberFieldExists(string $field): bool
    {
        return $this->db->fieldExists($field, $this->table);
    }
}
