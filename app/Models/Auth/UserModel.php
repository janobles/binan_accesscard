<?php

namespace App\Models\Auth;

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

    /**
     * Authenticates a staff login for AuthController::login(). Looks up the user,
     * verifies the password (supporting legacy plaintext rows and transparently
     * re-hashing to Argon2id), and returns the user row. Disabled accounts are
     * returned with a `login_error` flag; null means invalid credentials.
     */
    public function verifyLogin(string $username, string $password): ?array
    {
        $user = $this->where('username', $username)->first();

        if ($user === null) {
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

        if (! $this->isUserActive($user['isactive'] ?? 1)) {
            $user['login_error'] = 'disabled';

            return $user;
        }

        if ($isLegacyPlaintext || password_needs_rehash($storedPassword, PASSWORD_ARGON2ID)) {
            $this->update((int) $user['userID'], [
                'password' => password_hash($password, PASSWORD_ARGON2ID),
            ]);
        }

        return $user;
    }

    /**
     * Returns all Admin and Employee (User) accounts for the admin Account
     * Management page, ordered by role then username. Excludes Developer accounts.
     * Frontend: feeds the accounts table via DashboardPageBuilder.
     */
    public function getStaffAccounts(): array
    {
        if (! $this->db->tableExists($this->table)) {
            return [];
        }

        return $this->select('userID, username, role, isactive, dt_created')
            ->whereIn('role', ['Admin', 'User'])
            ->orderBy('role', 'ASC')
            ->orderBy('username', 'ASC')
            ->findAll();
    }

    /**
     * Enables or disables an Admin/Employee account (only those roles are
     * eligible). Called by AccountController's status actions. Returns false if
     * the user is missing or not an updatable role.
     */
    public function updateAccountStatus(int $userId, bool $enabled): bool
    {
        if (! $this->db->tableExists($this->table)) {
            return false;
        }

        $account = $this->select('userID, role')->find($userId);

        if ($account === null || ! in_array((string) ($account['role'] ?? ''), ['Admin', 'User'], true)) {
            return false;
        }

        return $this->update($userId, ['isactive' => $this->statusValue($enabled)]) !== false;
    }

    /**
     * Interprets the `isactive` column as a boolean, tolerating the Enable/Disabled
     * enum, legacy numeric (1/0), and common truthy strings. Used during login.
     */
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

    /**
     * Inserts a new staff account (Argon2id-hashed password, active by default)
     * for AccountController::create(). Returns the new userID, or false on failure.
     */
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

    /**
     * The "active" value for a new account, format-matched to the column type.
     */
    private function activeValue(): string|int
    {
        return $this->statusValue(true);
    }

    /**
     * Returns the correct `isactive` value for the column's actual type: the
     * 'Enable'/'Disabled' enum for string columns, or 1/0 for numeric ones. Keeps
     * writes compatible across schema variants. No frontend connection.
     */
    private function statusValue(bool $enabled): string|int
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

            if ($isStringType) {
                return $enabled ? 'Enable' : 'Disabled';
            }

            return $enabled ? 1 : 0;
        }

        return $enabled ? 'Enable' : 'Disabled';
    }
}
