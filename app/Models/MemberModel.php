<?php

namespace App\Models;

use App\Support\SectorIds;
use CodeIgniter\Model;

/**
 * Manages family heads and family member records.
 */
class MemberModel extends Model
{
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

    protected $validationRules = [
        'sectorID' => 'required|max_length[255]',
        'firstname' => 'required|max_length[100]',
        'lastname' => 'required|max_length[100]',
        'middlename' => 'permit_empty|max_length[50]',
        'birthday' => 'permit_empty|valid_date[Y-m-d]',
        'sex' => 'permit_empty|in_list[Male,Female]',
    ];

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

        return $this->select('member.*, ' . SectorIds::sectorNameSelect(), false)
            ->where('member.headID = member.memberID')
            ->orderBy('member.dt_created', 'DESC')
            ->limit($limit)
            ->findAll();
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
        if ($this->find($headId) === null) {
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
        return $this->select('member.*, ' . SectorIds::sectorNameSelect(), false)
            ->where('member.headID', $headId)
            ->orderBy('member.memberID', 'ASC')
            ->findAll();
    }

    public function findWithSector(int $memberId): ?array
    {
        return $this->select(
            'member.*, '
            . SectorIds::sectorNameSelect()
            . ', '
            . SectorIds::sectorShortcodeSelect(),
            false
        )
            ->where('member.memberID', $memberId)
            ->first();
    }

    public function searchFamilies(?string $keyword = null): array
    {
        $builder = $this->select('member.*, ' . SectorIds::sectorNameSelect(), false)
            ->where('member.headID = member.memberID')
            ->orderBy('member.lastname', 'ASC')
            ->orderBy('member.firstname', 'ASC');

        if ($keyword !== null && trim($keyword) !== '') {
            $builder->groupStart()
                ->like('member.firstname', $keyword)
                ->orLike('member.lastname', $keyword)
                ->orWhere(SectorIds::sectorNameLikeCondition($keyword, 'member.sectorID', $this->db), null, false)
                ->groupEnd();
        }

        return $builder->findAll();
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
}
