<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Manages staff users, login verification, and account creation.
 */
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

    // Used by Home::login() to authenticate staff accounts.
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
        $passwordInfo = password_get_info($storedPassword);
        $isLegacyPlaintext = $passwordInfo['algo'] === 0;
        $isValid = password_verify($password, $storedPassword);

        if (! $isValid && $isLegacyPlaintext) {
            $isValid = hash_equals($storedPassword, $password);
        }

        if (! $isValid) {
            return null;
        }

        if ($isLegacyPlaintext || password_needs_rehash($storedPassword, PASSWORD_ARGON2ID)) {
            $this->update((int) $user['userID'], [
                'password' => password_hash($password, PASSWORD_ARGON2ID),
            ]);
        }

        return $user;
    }

    public function getStaffAccounts(): array
    {
        if (! $this->db->tableExists($this->table)) {
            return [];
        }

        $select = 'userID, username, role, isactive, dt_created';

        return $this->select($select)
            ->whereIn('role', ['Admin', 'User'])
            ->orderBy('role', 'ASC')
            ->orderBy('username', 'ASC')
            ->findAll();
    }

    // Enforces the Enable/Disabled enum while allowing legacy numeric rows.
    private function isUserActive(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }

        $normalized = strtolower(trim((string) $value));

        if (in_array($normalized, ['enable', 'enabled'], true)) {
            return true;
        }

        return in_array($normalized, ['1', 'true', 'yes', 'y', 'on'], true);
    }

    // Creates staff accounts for AccountController::create().
    public function createAccount(string $username, string $password, string $role): int|false
    {
        $data = [
            'username' => $username,
            'password' => password_hash($password, PASSWORD_ARGON2ID),
            'role' => $role,
            'isactive' => $this->activeValue(),
        ];

        $inserted = $this->insert($data);

        if ($inserted === false) {
            return false;
        }

        return (int) $this->getInsertID();
    }

    // Keeps insert compatible with either enum or numeric legacy types.
    private function activeValue(): string|int
    {
        $fieldData = $this->db->getFieldData($this->table);

        foreach ($fieldData as $field) {
            if ($field->name !== 'isactive') {
                continue;
            }

            $type = strtolower((string) $field->type);

            $isStringType = strpos($type, 'char') !== false
                || strpos($type, 'text') !== false
                || strpos($type, 'enum') !== false;

            return $isStringType ? 'Enable' : 1;
        }

        return 'Enable';
    }

}
