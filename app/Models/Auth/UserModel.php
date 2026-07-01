<?php

namespace App\Models\Auth;

use App\Libraries\DeveloperProfile;
use CodeIgniter\Model;

/**
 * Manages staff users, login verification, and account creation.
 *
 * Role note: the `account_level` column is the DB enum('administrator','encoder',
 * 'viewer'). 'administrator' is the Admin role and 'encoder' is the staff/employee
 * role; the rest of the app refers to them as 'Admin'/'Employee' (translated by
 * App\Libraries\RoleAccess::normalizeRole). The literals in the queries below are
 * the raw DB enum values and must match the schema. Read queries alias the column
 * back as `account_level AS role` so existing `$row['role']` callers keep working.
 * The Developer no longer lives in this table — it authenticates from .env (see
 * verifyLogin) so it cannot be seen, disabled, or edited through the app.
 */
class UserModel extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'userID';
    protected $returnType = 'array';
    protected $allowedFields = [
        'username',
        'password',
        'account_level',
        'full_description',
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
        $developer = $this->verifyDeveloperLogin($username, $password);

        if ($developer !== null) {
            return $developer;
        }

        $user = $this->where('username', $username)->first();

        if ($user === null) {
            return null;
        }

        // Expose the DB enum column under the `role` key the rest of the auth flow
        // reads (RoleAccess::normalizeRole), matching the developer's synthetic row.
        $user['role'] = $user['account_level'] ?? '';

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
     * Returns all administrator and encoder (Admin/Employee) accounts for the admin
     * Account Management page, ordered by account level then username. The Developer
     * is not in this table. Frontend: feeds the accounts table via DashboardPageBuilder.
     */
    public function getStaffAccounts(): array
    {
        if (! $this->db->tableExists($this->table)) {
            return [];
        }

        return $this->select('userID, username, account_level AS role, isactive')
            ->whereIn('account_level', ['administrator', 'encoder', 'viewer'])
            ->orderBy('account_level', 'ASC')
            ->orderBy('username', 'ASC')
            ->findAll();
    }

    /**
     * Fetches a single staff account for the edit / My Account prefill, with the
     * enum column aliased back to `role` and the packed personal details. Returns
     * null when the id is missing or the table does not exist.
     */
    public function getAccountById(int $userId): ?array
    {
        if ($userId <= 0 || ! $this->db->tableExists($this->table)) {
            return null;
        }

        return $this->select('userID, username, account_level AS role, full_description, isactive')
            ->find($userId);
    }

    /**
     * Resolves a username to its userID for audit attribution (e.g. a failed login
     * against a known account), or null when no such account exists. Read-only and
     * password-agnostic — never use this for authentication.
     */
    public function userIdByUsername(string $username): ?int
    {
        $username = trim($username);

        if ($username === '' || ! $this->db->tableExists($this->table)) {
            return null;
        }

        $row = $this->select('userID')->where('username', $username)->first();

        return $row === null ? null : (int) $row['userID'];
    }

    /**
     * Updates profile fields on an existing account. Only username/account_level/
     * full_description keys present in $fields are written. Returns false on
     * failure. Callers own all authorization/validation. No password handling here
     * (see updatePassword).
     */
    public function updateProfile(int $userId, array $fields): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $data = array_intersect_key($fields, array_flip(['username', 'account_level', 'full_description']));

        if ($data === []) {
            return false;
        }

        return $this->update($userId, $data) !== false;
    }

    /**
     * Sets a new Argon2id-hashed password on an account (self change-password and
     * admin/developer reset). Returns false on failure.
     */
    public function updatePassword(int $userId, string $newPassword): bool
    {
        if ($userId <= 0) {
            return false;
        }

        return $this->update($userId, [
            'password' => password_hash($newPassword, PASSWORD_ARGON2ID),
        ]) !== false;
    }

    /**
     * Confirms a plaintext password matches the stored hash for a user — used for
     * the "current password" check in self change-password. Mirrors the legacy
     * plaintext fallback in verifyLogin so old rows still verify.
     */
    public function verifyUserPassword(int $userId, string $password): bool
    {
        $user = $this->find($userId);

        if ($user === null) {
            return false;
        }

        $stored = (string) ($user['password'] ?? '');

        if (password_verify($password, $stored)) {
            return true;
        }

        return password_get_info($stored)['algo'] === 0 && hash_equals($stored, $password);
    }

    /**
     * Builds a readable random password (no ambiguous characters like 0/O/1/l) for
     * the admin/developer "reset password" action. The caller shows the plaintext
     * to the staffer once and hashes it via updatePassword.
     */
    public function generateRandomPassword(int $length = 8): string
    {
        $length = max(8, $length);
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        $maxIndex = strlen($alphabet) - 1;
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= $alphabet[random_int(0, $maxIndex)];
        }

        return $password;
    }

    /**
     * Enables or disables an Admin/Employee/Viewer account (only those roles are
     * eligible). Called by AccountController's status actions. Returns false if
     * the user is missing or not an updatable role.
     */
    public function updateAccountStatus(int $userId, bool $enabled): bool
    {
        if (! $this->db->tableExists($this->table)) {
            return false;
        }

        $account = $this->select('userID, account_level AS role')->find($userId);

        if ($account === null || ! in_array((string) ($account['role'] ?? ''), ['administrator', 'encoder', 'viewer'], true)) {
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
     * Inserts a new staff account (Argon2id-hashed password, active by default) for
     * AccountController::create(). `$accountLevel` is the DB enum value
     * ('administrator'/'encoder'/'viewer'); `$fullDescription` is the prebuilt
     * labeled personal-details string. Returns the new userID, or false on failure.
     */
    public function createAccount(string $username, string $password, string $accountLevel, string $fullDescription = ''): int|false
    {
        $data = [
            'username' => $username,
            'password' => password_hash($password, PASSWORD_ARGON2ID),
            'account_level' => $accountLevel,
            'full_description' => $fullDescription,
            'isactive' => $this->activeValue(),
        ];

        $inserted = $this->insert($data);

        if ($inserted === false) {
            return false;
        }

        return (int) $this->getInsertID();
    }

    /**
     * Authenticates the hardcoded Developer account from .env (developer.username +
     * developer.passwordHash, an Argon2id hash) before any DB lookup. Returns a
     * synthetic user row (userID 0, role 'developer', active) on success, or null
     * when the env credentials are unset or do not match. Keeping the Developer out
     * of the `users` table means it cannot be seen, disabled, or edited via the app.
     */
    private function verifyDeveloperLogin(string $username, string $password): ?array
    {
        // Live credentials come from writable/developer/credentials.json when present,
        // else the .env developer.* seed (see DeveloperProfile::credentials()).
        $creds = DeveloperProfile::credentials();
        $devUsername = $creds['username'];
        $devHash = $creds['passwordHash'];

        if ($devUsername === '' || $devHash === '') {
            return null;
        }

        if (! hash_equals($devUsername, $username) || ! password_verify($password, $devHash)) {
            return null;
        }

        return [
            'userID' => 0,
            'memberID' => 0,
            'username' => $username,
            'role' => 'developer',
            'isactive' => 'Enable',
        ];
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
