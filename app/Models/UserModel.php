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
        'memberID',
    ];
    protected $useTimestamps = false;

    public function verifyLogin(string $username, string $password): ?array
    {
        $user = $this->where('username', $username)->first();

        if ($user === null) {
            return null;
        }

        if (! $this->isUserActive($user['isactive'] ?? 1)) {
            return null;
        }

        $storedPassword = (string) ($user['password'] ?? '');
        $isValid = password_verify($password, $storedPassword) || hash_equals($storedPassword, $password);

        if (! $isValid) {
            return null;
        }

        if (! password_get_info($storedPassword)['algo']) {
            $this->update((int) $user['userID'], [
                'password' => password_hash($password, PASSWORD_DEFAULT),
            ]);
        }

        return $user;
    }

    private function isUserActive(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'yes', 'y', 'on', 'enable', 'enabled', 'active'], true);
    }

    public function createAccount(string $username, string $password, string $role): int|false
    {
        $inserted = $this->insert([
            'username' => $username,
            'password' => password_hash($password, PASSWORD_ARGON2ID),
            'role' => $role,
            'isactive' => 1,
            'memberID' => null,
        ]);

        if ($inserted === false) {
            return false;
        }

        return (int) $this->getInsertID();
    }
}