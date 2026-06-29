<?php

namespace App\Models\Concerns;

use App\Libraries\SectorIds;

/**
 * Shared query helpers for model-layer ID normalization and batched name lookup.
 *
 * Hosting classes must expose $this->db (CodeIgniter Model or BaseConnection).
 */
trait ModelQueryHelpers
{
    /** Normalizes an ID list to unique positive ints for batched IN() lookups. */
    private function positiveUniqueIds(array $ids): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $ids),
            static fn (int $id): bool => $id > 0
        )));
    }

    /**
     * Normalizes a filter control's value(s) to a unique list, dropping blanks,
     * the "__all" sentinel and zero. $integers casts each entry to an int string.
     */
    private function normalizeFilterList(mixed $value, bool $integers = true): array
    {
        $values = is_array($value) ? $value : [$value];
        $normalized = [];

        foreach ($values as $item) {
            $item = trim((string) $item);

            if ($item === '' || $item === '__all') {
                continue;
            }

            $normalized[] = $integers ? (string) (int) $item : $item;
        }

        return array_values(array_unique(array_filter(
            $normalized,
            static fn (string $item): bool => $item !== '' && $item !== '0'
        )));
    }

    /**
     * Resolves raw JSON sectorID values on rows into readable sector_name strings.
     */
    private function withSectorNames(array $rows): array
    {
        $sectorNames = $this->sectorNameMap();

        foreach ($rows as &$row) {
            $sectorValue = $row['sector_array_string'] ?? $row['sectorID'] ?? '[]';
            $row['sectorID'] = $sectorValue;
            $row['sector_name'] = SectorIds::toNames($sectorValue, $sectorNames);
        }

        return $rows;
    }

    /** Builds an [sectorID => name] map used to resolve sector names for display. */
    private function sectorNameMap(): array
    {
        if (! $this->db->tableExists('sector')) {
            return [];
        }

        $sectors = $this->db->table('sector')
            ->select('sectorID, name')
            ->get()
            ->getResultArray();

        $map = [];

        foreach ($sectors as $sector) {
            $map[(int) $sector['sectorID']] = (string) $sector['name'];
        }

        return $map;
    }

    /** Batch [userID => {username, role}] lookup. */
    private function userMap(array $userIds): array
    {
        $userIds = $this->positiveUniqueIds($userIds);

        if ($userIds === [] || ! $this->db->tableExists('users')) {
            return [];
        }

        $users = $this->db->table('users')
            ->select('userID, username, account_level AS role')
            ->whereIn('userID', $userIds)
            ->get()
            ->getResultArray();

        $map = [];

        foreach ($users as $user) {
            $map[(int) $user['userID']] = [
                'username' => (string) $user['username'],
                'role' => (string) ($user['role'] ?? ''),
            ];
        }

        return $map;
    }

    /** Batch [memberID => {firstname, lastname}] lookup. */
    private function memberNameMap(array $memberIds): array
    {
        $memberIds = $this->positiveUniqueIds($memberIds);

        if ($memberIds === [] || ! $this->db->tableExists('member')) {
            return [];
        }

        $members = $this->db->table('member')
            ->select('memberID, firstname, lastname')
            ->whereIn('memberID', $memberIds)
            ->get()
            ->getResultArray();

        $map = [];

        foreach ($members as $member) {
            $map[(int) $member['memberID']] = [
                'firstname' => (string) $member['firstname'],
                'lastname' => (string) $member['lastname'],
            ];
        }

        return $map;
    }

    /** Joins first/last name into a single display string. */
    private function formatMemberName(array $memberName): string
    {
        return trim(implode(' ', array_filter([
            (string) ($memberName['firstname'] ?? ''),
            (string) ($memberName['lastname'] ?? ''),
        ], static fn (string $value): bool => trim($value) !== '')));
    }
}
