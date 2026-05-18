<?php

namespace App\Models;

use CodeIgniter\Model;

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
        'sectorID' => 'required|is_natural_no_zero',
        'firstname' => 'required|max_length[100]',
        'lastname' => 'required|max_length[100]',
        'middlename' => 'permit_empty|max_length[50]',
        'birthday' => 'permit_empty|valid_date[Y-m-d]',
        'sex' => 'permit_empty|in_list[Male,Female]',
    ];

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
        return $this->select('member.*, sector.name AS sector_name')
            ->join('sector', 'sector.sectorID = member.sectorID')
            ->where('member.headID', $headId)
            ->orderBy('member.memberID', 'ASC')
            ->findAll();
    }

    public function findWithSector(int $memberId): ?array
    {
        return $this->select('member.*, sector.name AS sector_name, sector.shortcode AS sector_shortcode')
            ->join('sector', 'sector.sectorID = member.sectorID')
            ->where('member.memberID', $memberId)
            ->first();
    }

    public function searchFamilies(?string $keyword = null): array
    {
        $builder = $this->select('member.*, sector.name AS sector_name')
            ->join('sector', 'sector.sectorID = member.sectorID')
            ->where('member.headID = member.memberID')
            ->orderBy('member.lastname', 'ASC')
            ->orderBy('member.firstname', 'ASC');

        if ($keyword !== null && trim($keyword) !== '') {
            $builder->groupStart()
                ->like('member.firstname', $keyword)
                ->orLike('member.lastname', $keyword)
                ->orLike('sector.name', $keyword)
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
