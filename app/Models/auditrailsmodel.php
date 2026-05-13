<?php

namespace App\Models;

use CodeIgniter\Model;

class AuditTrailsModel extends Model
{
    protected $table            = 'audit_trails';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    protected $allowedFields = [
        'user_id',
        'action',
        'description',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $useTimestamps = false;

    protected $validationRules = [
        'action' => 'required|max_length[100]',
    ];

    protected $validationMessages = [
        'action' => [
            'required'   => 'Audit action is required.',
            'max_length' => 'Audit action must not exceed 100 characters.',
        ],
    ];

    public function logAction(
        ?int $userId,
        string $action,
        ?string $description = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): bool {
        return $this->insert([
            'user_id'     => $userId,
            'action'      => $action,
            'description' => $description,
            'ip_address'  => $ipAddress,
            'user_agent'  => $userAgent,
            'created_at'  => date('Y-m-d H:i:s'),
        ]) !== false;
    }

    public function getRecent(int $limit = 50): array
    {
        return $this->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->findAll();
    }

    public function getByUser(int $userId, int $limit = 50): array
    {
        return $this->where('user_id', $userId)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->findAll();
    }
}
