<?php

namespace App\Libraries;

use App\Models\Auth\UserModel;

/**
 * Resolves the currently logged-in account for view data (topbar account menu,
 * etc.). Shared by DashboardPageBuilder and the Scanner controllers so every
 * shell renders the same account identity from one place.
 *
 * Staff accounts come from the users table; the Developer is file-backed
 * (synthetic userID 0, no users row) so its details come from DeveloperProfile.
 */
class SessionAccount
{
    /**
     * The session user merged with its account row. For the file-backed Developer
     * (userID 0), fills full_description from DeveloperProfile so the account menu
     * shows the real name instead of falling back to the username.
     *
     * @return array<string,mixed>
     */
    public static function user(): array
    {
        $sessionUser = session()->get();
        $userId = (int) ($sessionUser['user_id'] ?? 0);

        if ($userId <= 0) {
            if (RoleAccess::normalizeRole((string) ($sessionUser['role'] ?? '')) === 'Developer') {
                $sessionUser['full_description'] = self::packFullDescription(DeveloperProfile::load());
            }

            return $sessionUser;
        }

        $account = (new UserModel())->getAccountById($userId);

        return $account === null ? $sessionUser : array_merge($sessionUser, $account);
    }

    /**
     * Human-readable account level for the current session (Admin, Encoder,
     * Viewer, Developer, Scanner), or 'Account' when the role is unknown.
     */
    public static function levelLabel(): string
    {
        return RoleAccess::auditRoleLabel((string) (session()->get('role') ?? '')) ?? 'Account';
    }

    /**
     * Packs DeveloperProfile personal details into the same `LN:..; FN:..` string
     * shape full_description uses for staff, so ViewFormatter::parseFullDescription
     * (and the topbar partial) reads the Developer's real name the same way.
     *
     * @param array<string,string> $details
     */
    private static function packFullDescription(array $details): string
    {
        $segments = [
            'LN'   => trim((string) ($details['last_name'] ?? '')),
            'FN'   => trim((string) ($details['first_name'] ?? '')),
            'MN'   => trim((string) ($details['middle_name'] ?? '')),
            'SF'   => trim((string) ($details['suffix'] ?? '')),
            'ADDR' => trim((string) ($details['address'] ?? '')),
            'CN'   => trim((string) ($details['contact_no'] ?? '')),
            'BD'   => trim((string) ($details['birthday'] ?? '')),
        ];

        $parts = [];

        foreach ($segments as $label => $value) {
            if ($value !== '') {
                $parts[] = $label . ':' . $value;
            }
        }

        return implode('; ', $parts);
    }
}
