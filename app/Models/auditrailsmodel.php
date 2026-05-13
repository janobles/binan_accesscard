<?php

namespace App\Models;

use CodeIgniter\Model;

class audittrailsmodel extends Model
{
    protected $table            = 'audit_trails';
    protected $primaryKey       = 'auditID';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    protected $allowedFields = [
        'user_action',
        'description',
        'ip_address',
        'userID',
        'memberID',
    ];

    protected $useTimestamps = false;

    protected $validationRules = [
        'user_action' => 'required',
    ];

    protected $validationMessages = [
        'user_action' => [
            'required' => 'Audit action is required.',
        ],
    ];

    public function logAction(
        int $userId,
        ?int $memberId,
        string $action,
        ?string $description = null,
        ?string $ipAddress = null
    ): bool {
        return $this->insert([
            'userID'      => $userId,
            'memberID'    => $memberId,
            'user_action' => $action,
            'description' => $description,
            'ip_address'  => $ipAddress,
        ]) !== false;
    }

    public function getRecent(int $limit = 50): array
    {
        return $this->select('audit_trails.*, users.username, member.firstname, member.lastname')
            ->join('users', 'users.userID = audit_trails.userID')
            ->join('member', 'member.memberID = audit_trails.memberID', 'left')
            ->orderBy('audit_trails.dt_created', 'DESC')
            ->limit($limit)
            ->findAll();
    }

    public function getByUser(int $userId, int $limit = 50): array
    {
        return $this->where('userID', $userId)
            ->orderBy('dt_created', 'DESC')
            ->limit($limit)
            ->findAll();
    }
}