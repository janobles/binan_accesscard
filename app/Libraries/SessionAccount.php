<?php

namespace App\Libraries;

use App\Models\Auth\UserModel;

/**
 * Resolves the currently logged-in account for view data (topbar account menu,
 * etc.). Shared by DashboardPageBuilder and the Scanner controllers so every
 * shell renders the same account identity from one place.
 *
 * Every account, including Developer, comes from the users table.
 */
class SessionAccount
{
    /**
     * The session user merged with its account row.
     *
     * @return array<string,mixed>
     */
    public static function user(): array
    {
        $sessionUser = session()->get();
        $userId = (int) ($sessionUser['user_id'] ?? 0);

        if ($userId <= 0) {
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

}
