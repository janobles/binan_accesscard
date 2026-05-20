<?php

namespace App\Models;

use CodeIgniter\Model;

class MemberServiceModel extends Model
{
    protected $table = 'member_services';
    protected $primaryKey = 'ID';
    protected $returnType = 'array';
    protected $allowedFields = [
        'serviceID',
        'memberID',
    ];
    protected $useTimestamps = false;

    protected $validationRules = [
        'memberID' => 'required|is_natural_no_zero',
        'serviceID' => 'required|is_natural',
    ];

    public function hasTable(): bool
    {
        return $this->db->tableExists($this->table);
    }

    public function countAssignments(): int
    {
        if (! $this->hasTable()) {
            return 0;
        }

        return $this->countAllResults();
    }

    public function assignService(int $memberId, int $serviceId): int|false
    {
        if (! $this->insert([
            'memberID' => $memberId,
            'serviceID' => $serviceId,
        ])) {
            return false;
        }

        return (int) $this->getInsertID();
    }

    public function recentAssignments(int $limit = 25): array
    {
        return $this->select('member_services.*, services.name AS service_name, member.firstname, member.lastname')
            ->join('services', 'services.serviceID = member_services.serviceID')
            ->join('member', 'member.memberID = member_services.memberID')
            ->orderBy('member_services.dt_created', 'DESC')
            ->limit($limit)
            ->findAll();
    }
}

