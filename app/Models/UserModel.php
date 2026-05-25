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
        'memberID',
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

        if ($this->hasUserField('memberID')) {
            $select .= ', memberID';
        }

        $accounts = $this->select($select)
            ->whereIn('role', ['Admin', 'User'])
            ->orderBy('role', 'ASC')
            ->orderBy('username', 'ASC')
            ->findAll();

        return $this->withLinkedMemberNames($accounts);
    }

    public function getLinkableMembers(): array
    {
        if (! $this->db->tableExists('member')) {
            return [];
        }

        return $this->db->table('member')
            ->select('memberID, firstname, middlename, lastname, suffix')
            ->where('dt_deleted IS NULL', null, false)
            ->orderBy('lastname', 'ASC')
            ->orderBy('firstname', 'ASC')
            ->get()
            ->getResultArray();
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
    public function createAccount(string $username, string $password, string $role, ?int $memberId = null): int|false
    {
        $data = [
            'username' => $username,
            'password' => password_hash($password, PASSWORD_ARGON2ID),
            'role' => $role,
            'isactive' => $this->activeValue(),
        ];

        if ($this->hasUserField('memberID')) {
            $data['memberID'] = $this->memberIdValue($memberId);
        }

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

    private function memberIdValue(?int $memberId): ?int
    {
        if ($memberId !== null && $memberId > 0 && $this->memberExists($memberId)) {
            return $memberId;
        }

        return null;
    }

    private function withLinkedMemberNames(array $accounts): array
    {
        if (! $this->hasUserField('memberID')) {
            foreach ($accounts as &$account) {
                $account['member_name'] = '';
            }

            return $accounts;
        }

        $memberNames = $this->memberNameMap(array_column($accounts, 'memberID'));

        foreach ($accounts as &$account) {
            $memberId = (int) ($account['memberID'] ?? 0);
            $account['member_name'] = $memberNames[$memberId] ?? '';
        }

        return $accounts;
    }

    private function hasUserField(string $fieldName): bool
    {
        return $this->db->tableExists($this->table)
            && $this->db->fieldExists($fieldName, $this->table);
    }

    private function memberExists(int $memberId): bool
    {
        if (! $this->db->tableExists('member')) {
            return false;
        }

        return $this->db->table('member')
            ->where('memberID', $memberId)
            ->countAllResults() > 0;
    }

    private function memberNameMap(array $memberIds): array
    {
        $memberIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $memberIds),
            static fn (int $id): bool => $id > 0
        )));

        if ($memberIds === [] || ! $this->db->tableExists('member')) {
            return [];
        }

        $members = $this->db->table('member')
            ->select('memberID, firstname, middlename, lastname, suffix')
            ->whereIn('memberID', $memberIds)
            ->get()
            ->getResultArray();

        $map = [];

        foreach ($members as $member) {
            $map[(int) $member['memberID']] = $this->formatMemberName($member);
        }

        return $map;
    }

    private function formatMemberName(array $member): string
    {
        return trim(implode(' ', array_filter([
            (string) ($member['firstname'] ?? ''),
            (string) ($member['middlename'] ?? ''),
            (string) ($member['lastname'] ?? ''),
            (string) ($member['suffix'] ?? ''),
        ], static fn (string $value): bool => trim($value) !== '')));
    }
}
