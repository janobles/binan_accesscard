<?php

namespace App\Models\Concerns;

/**
 * Batched user-name/role lookup, shared by AuditTrailsModel and SearchModel.
 * Hosting classes must also use NormalizesIds — userMap() calls
 * positiveUniqueIds().
 */
trait ResolvesUserNames
{
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
}
