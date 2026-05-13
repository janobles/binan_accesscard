<?php

namespace App\Models;

use CodeIgniter\Model;

class MemberServiceModel extends Model
{
    protected $table = 'member_services';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'member_id',
        'service_id',
        'assigned_by',
        'status',
        'remarks',
        'assigned_at',
    ];
    protected $useTimestamps = true;

    protected $validationRules = [
        'member_id' => 'required|is_natural_no_zero',
        'service_id' => 'required|is_natural_no_zero',
        'status' => 'required|in_list[Pending,Released,Cancelled]',
    ];

    public function recentAssignments(int $limit = 25): array
    {
        return $this->select('member_services.*, services.name AS service_name, members.first_name, members.last_name, users.full_name AS assigned_by_name')
            ->join('services', 'services.id = member_services.service_id')
            ->join('members', 'members.id = member_services.member_id')
            ->join('users', 'users.id = member_services.assigned_by', 'left')
            ->orderBy('member_services.assigned_at', 'DESC')
            ->limit($limit)
            ->findAll();
    }
}
