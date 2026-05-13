<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'userID';
    protected $returnType = 'array';
    protected $allowedFields = [
        'username',
        'password',
        'role',
        'isactive',
    ];
    protected $useTimestamps = false;

    protected $validationRules = [
        'username' => 'required|max_length[255]',
        'role' => 'required|in_list[Admin,Employee,Developer]',
    ];

    public function findActiveByUsername(string $username): ?array
    {
        return $this->where('username', $username)
            ->where('isactive', 'Enable')
            ->first();
    }

    public function verifyLogin(string $username, string $password): ?array
    {
        $user = $this->findActiveByUsername($username);

        if ($user === null || ! password_verify($password, $user['password'])) {
            return null;
        }

        return $user;
    }
}
