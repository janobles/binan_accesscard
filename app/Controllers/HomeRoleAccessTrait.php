<?php

namespace App\Controllers;

use App\Libraries\RoleAccess;
use CodeIgniter\HTTP\RedirectResponse;
use Config\IdleTimeout;

trait HomeRoleAccessTrait
{
    private function hasValidLoginSession(): bool
    {
        if (! RoleAccess::sessionUserExists()) {
            return false;
        }

        if ($this->normalizeRole((string) session()->get('role')) === null) {
            return false;
        }

        $lastActivity = (int) (session()->get('idle_last_activity') ?? time());

        return (time() - $lastActivity) < (new IdleTimeout())->seconds;
    }

    private function clearLoginSession(): void
    {
        session()->remove([
            'is_logged_in',
            'user_id',
            'member_id',
            'username',
            'role',
            'idle_last_activity',
        ]);

        session()->regenerate(true);
    }

    private function normalizeRole(string $role): ?string
    {
        return RoleAccess::normalizeRole($role);
    }

    private function redirectByRole(string $role): RedirectResponse
    {
        return RoleAccess::redirectByRole($role);
    }
}
