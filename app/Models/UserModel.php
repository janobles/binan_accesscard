<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    private const PASSWORD_ALGORITHM = PASSWORD_ARGON2ID;

    protected $table = 'users';
    protected $primaryKey = 'userID';
    protected $returnType = 'array';
    protected $allowedFields = [
        'username',
        'password',
        'role',
        'isactive',
        'memberID',
    ];
    protected $useTimestamps = false;

    protected $validationRules = [
        'username' => 'required|max_length[255]',
        'role' => 'required|in_list[User,Admin,Developer]',
        'isactive' => 'permit_empty|in_list[Enable,Disabled]',
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

        if (password_needs_rehash($user['password'], self::PASSWORD_ALGORITHM)) {
            $this->update($user['userID'], [
                'password' => password_hash($password, self::PASSWORD_ALGORITHM),
            ]);
        }

        return $user;
    }

    public function createAccount(string $username, string $password, string $role, ?int $memberId = 1): int|false
    {
        if (! in_array($role, ['Admin', 'User'], true)) {
            return false;
        }

        if (! $this->insert([
            'username' => $username,
            'password' => password_hash($password, self::PASSWORD_ALGORITHM),
            'role' => $role,
            'isactive' => 'Enable',
            'memberID' => $memberId,
        ])) {
            return false;
        }

        return (int) $this->getInsertID();
    }

    public function archiveAccount(int $userId): bool
    {
        return $this->update($userId, ['isactive' => 'Disabled']);
    }
}
