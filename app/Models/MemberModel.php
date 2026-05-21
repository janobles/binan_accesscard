<?php

namespace App\Models;

use App\Support\SectorIds;
use CodeIgniter\Model;

/**
 * Manages family heads and family member records.
 */
class MemberModel extends Model
{
    public const VALIDATION_RULES = [
        'sectorID' => 'required|max_length[255]',
        'firstname' => 'required|max_length[100]',
        'lastname' => 'required|max_length[100]',
        'middlename' => 'permit_empty|max_length[50]',
        'birthday' => 'permit_empty|valid_date[Y-m-d]',
        'sex' => 'permit_empty|in_list[Male,Female]',
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
        'relationship',
        'headID',
        'sectorID',
    ];
    protected $useTimestamps = false;
    protected $validationRules = self::VALIDATION_RULES;

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
        if (! $this->db->tableExists('view_member_dashboard')) {
            return [];
        }

        $rows = $this->db->table('view_member_dashboard')
            ->where('memberID = headID')
            ->orderBy('memberID', 'DESC')
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

        return $this->where('headID = memberID')->countAllResults();
    }

    public function countMembers(): int
    {
        if (! $this->hasTable()) {
            return 0;
        }

        return $this->countAllResults();
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

        if (! $this->insert($data)) {
            return false;
        }

        return (int) $this->getInsertID();
    }

    public function getFamilyMembers(int $headId): array
    {
        if (! $this->db->tableExists('view_member_dashboard')) {
            return [];
        }

        $rows = $this->db->table('view_member_dashboard')
            ->where('headID', $headId)
            ->orderBy('memberID', 'ASC')
            ->get()
            ->getResultArray();

        return $this->withSectorNames($rows);
    }

    public function findWithSector(int $memberId): ?array
    {
        if (! $this->db->tableExists('view_member_dashboard')) {
            return null;
        }

        $member = $this->db->table('view_member_dashboard')
            ->where('memberID', $memberId)
            ->get()
            ->getRowArray();

        if ($member === null) {
            return null;
        }

        return $this->withSectorNames([$member])[0] ?? null;
    }

    public function searchFamilies(?string $keyword = null): array
    {
        if (! $this->db->tableExists('view_member_dashboard')) {
            return [];
        }

        $builder = $this->db->table('view_member_dashboard')
            ->where('memberID = headID')
            ->orderBy('lastname', 'ASC')
            ->orderBy('firstname', 'ASC');

        if ($keyword !== null && trim($keyword) !== '') {
            $keyword = trim($keyword);

            $builder->groupStart()
                ->like('firstname', $keyword)
                ->orLike('lastname', $keyword)
                ->orLike('relationship', $keyword);

            foreach ($this->sectorIdsForKeyword($keyword) as $sectorId) {
                $builder->orWhere(SectorIds::containsCondition($sectorId), null, false);
            }

            $builder
                ->groupEnd();
        }

        return $this->withSectorNames($builder->get()->getResultArray());
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

        return $this->update($headId, $data) !== false;
    }

    public function deleteFamilyMembersExceptHead(int $headId): bool
    {
        return $this->where('headID', $headId)
            ->where('memberID !=', $headId)
            ->delete() !== false;
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
}
