<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'username',
        'password_hash',
        'full_name',
        'role',
        'is_active',
    ];
    protected $useTimestamps = true;

    protected $validationRules = [
        'username' => 'required|max_length[80]',
        'full_name' => 'required|max_length[150]',
        'role' => 'required|in_list[Admin,Employee]',
    ];

    public function findActiveByUsername(string $username): ?array
    {
        return $this->where('username', $username)
            ->where('is_active', 1)
            ->first();
    }
}
