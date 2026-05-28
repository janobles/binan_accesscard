<?php

namespace App\Models;

use App\Libraries\SectorIds;
use CodeIgniter\Model;

/**
 * Manages family heads and family member records.
 */
class MemberModel extends Model
{
    public const VALIDATION_RULES = [
        'sectorID' => 'required|valid_sector_array',
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

    public function hasTable(): bool
    {
        return $this->db->tableExists($this->table);
    }

    public function hasRequiredFamilyTables(): bool
    {
        foreach (['member', 'sector', 'services', 'member_services', 'audit_trails'] as $table) {
            if (! $this->db->tableExists($table)) {
                return false;
            }
        }

        return true;
    }

    public function beginTransaction(): void
    {
        $this->db->transStart();
    }

    public function rollbackTransaction(): void
    {
        $this->db->transRollback();
    }

    public function completeTransaction(): void
    {
        $this->db->transComplete();
    }

    public function transactionStatus(): bool
    {
        return $this->db->transStatus();
    }

    public function getRecentFamilies(int $limit = 10): array
    {
        if (! $this->hasTable()) {
            return [];
        }

        $rows = $this->memberDashboardBuilder()
            ->where('member.memberID = member.headID', null, false)
            ->orderBy('member.memberID', 'DESC')
            ->limit($limit)
            ->get()
            ->getResultArray();

        return $this->withSectorNames($rows);
    }

    public function countHeads(): int
    {
        if (! $this->hasTable()) {
            return 0;
        }

        return $this->where('headID = memberID')
            ->where('dt_deleted IS NULL', null, false)
            ->countAllResults();
    }

    public function countMembers(): int
    {
        if (! $this->hasTable()) {
            return 0;
        }

        return $this->where('dt_deleted IS NULL', null, false)
            ->countAllResults();
    }

    /**
     * ENCODE hook. Registered as beforeInsert/beforeUpdate, this runs right
     * before a row is written and converts the sectorID array into its JSON
     * storage string ('[1,2,3]'). See App\Support\SectorIds::toStorage().
     */
    protected function normalizeSectorIdStorage(array $data): array
    {
        if (array_key_exists('sectorID', $data['data'] ?? [])) {
            $data['data']['sectorID'] = SectorIds::toStorage($data['data']['sectorID']);
        }

        return $data;
    }

    public static function personValidationRules(bool $requireHeadDetails = false): array
    {
        $rules = self::VALIDATION_RULES;
        unset($rules['sectorID']);

        if ($requireHeadDetails) {
            $rules['middlename'] = 'required|max_length[50]';
            $rules['birthday'] = 'required|valid_date[Y-m-d]';
            $rules['sex'] = 'required|in_list[Male,Female]';
        }

        return $rules;
    }

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

    public function getFamilyMembers(int $headId, string $visibility = 'active'): array
    {
        if (! $this->hasTable()) {
            return [];
        }

        $rows = $this->memberDashboardBuilder($visibility)
            ->where('member.headID', $headId)
            ->orderBy('member.memberID', 'ASC')
            ->get()
            ->getResultArray();

        return $this->withSectorNames($rows);
    }

    public function findWithSector(int $memberId): ?array
    {
        if (! $this->hasTable()) {
            return null;
        }

        $member = $this->memberDashboardBuilder()
            ->where('member.memberID', $memberId)
            ->get()
            ->getRowArray();

        if ($member === null) {
            return null;
        }

        return $this->withSectorNames([$member])[0] ?? null;
    }

    public function searchFamilies(?string $keyword = null, int $limit = 50, int $offset = 0, bool $archived = false): array
    {
        if (! $this->hasTable()) {
            return [];
        }

        $limit = max(1, $limit);
        $offset = max(0, $offset);

        $builder = $this->familySearchBuilder($keyword, $archived)
            ->orderBy('member.lastname', 'ASC')
            ->orderBy('member.firstname', 'ASC')
            ->limit($limit, $offset);

        return $this->withSectorNames($builder->get()->getResultArray());
    }

    public function countSearchFamilies(?string $keyword = null, bool $archived = false): int
    {
        if (! $this->hasTable()) {
            return 0;
        }

        return $this->familySearchBuilder($keyword, $archived)->countAllResults();
    }

    private function familySearchBuilder(?string $keyword = null, bool $archived = false)
    {
        $builder = $this->memberDashboardBuilder($archived ? 'archived' : 'active')
            ->where('member.memberID = member.headID', null, false);

        if ($keyword !== null && trim($keyword) !== '') {
            $keyword = trim($keyword);

            $builder->groupStart()
                ->like('member.firstname', $keyword)
                ->orLike('member.middlename', $keyword)
                ->orLike('member.lastname', $keyword)
                ->orLike('member.contactnumber', $keyword)
                ->orLike('member.relationship', $keyword);

            foreach (['religion', 'address', 'barangay'] as $field) {
                if ($this->memberFieldExists($field)) {
                    $builder->orLike('member.' . $field, $keyword);
                }
            }

            foreach ($this->sectorIdsForKeyword($keyword) as $sectorId) {
                $builder->orWhere(SectorIds::containsCondition($sectorId, 'member.sectorID'), null, false);
            }

            $builder
                ->groupEnd();
        }

        return $builder;
    }

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

    public function updateHead(int $headId, array $data): bool
    {
        $data['headID'] = $headId;
        $data['relationship'] = 'Head';
        $data = $this->memberColumnPayload($data);

        return $this->update($headId, $data) !== false;
    }

    public function deleteFamilyMembersExceptHead(int $headId): bool
    {
        return $this->where('headID', $headId)
            ->where('memberID !=', $headId)
            ->delete() !== false;
    }

    public function archiveFamily(int $headId): bool
    {
        return $this->markFamilyDeleted($headId);
    }

    public function deleteFamilyRecord(int $headId): bool
    {
        return $this->markFamilyDeleted($headId);
    }

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

    private function nextAutoIncrementId(): int
    {
        $row = $this->db->query("
            SELECT AUTO_INCREMENT
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'member'
        ")->getRowArray();

        return (int) ($row['AUTO_INCREMENT'] ?? 1);
    }

    private function memberDashboardBuilder(string $visibility = 'active')
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
            if ($visibility === 'archived') {
                $builder->where('member.dt_deleted IS NOT NULL', null, false);
            } elseif ($visibility !== 'all') {
                $builder->where('member.dt_deleted IS NULL', null, false);
            }
        }

        return $builder;
    }

    /**
     * DECODE/display path. For each member row, takes the raw JSON sectorID
     * string and adds a 'sector_name' field with the IDs resolved to names.
     * See App\Support\SectorIds::toNames().
     */
    private function withSectorNames(array $rows): array
    {
        $sectorNames = $this->sectorNameMap();

        foreach ($rows as &$row) {
            $sectorValue = $row['sector_array_string'] ?? $row['sectorID'] ?? '[]';
            $row['sectorID'] = $sectorValue;
            $row['sector_name'] = SectorIds::toNames($sectorValue, $sectorNames);
        }

        return $rows;
    }

    private function sectorNameMap(): array
    {
        if (! $this->db->tableExists('sector')) {
            return [];
        }

        $sectors = $this->db->table('sector')
            ->select('sectorID, name')
            ->get()
            ->getResultArray();

        $map = [];

        foreach ($sectors as $sector) {
            $map[(int) $sector['sectorID']] = (string) $sector['name'];
        }

        return $map;
    }

    private function sectorIdsForKeyword(string $keyword): array
    {
        if (! $this->db->tableExists('sector')) {
            return [];
        }

        return array_map(
            static fn (array $sector): int => (int) $sector['sectorID'],
            $this->db->table('sector')
                ->select('sectorID')
                ->like('name', $keyword)
                ->orLike('description', $keyword)
                ->get()
                ->getResultArray()
        );
    }

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

    private function memberFieldExists(string $field): bool
    {
        return $this->db->fieldExists($field, $this->table);
    }
}
