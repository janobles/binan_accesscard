<?php

namespace App\Controllers;

use App\Libraries\RoleAccess;
use CodeIgniter\HTTP\RedirectResponse;
use Config\IdleTimeout;

/**
 * Shared session/role helpers used by Home controller.
 */
trait HomeRoleAccessTrait
{
    /**
     * Confirms the current session has a real user, a valid role, and is within
     * the IdleTimeout window. Used by Workspace\Home before serving pages.
     */
    private function hasValidLoginSession(): bool
    {
        if (! RoleAccess::sessionUserExists()) {
            return false;
        }

        if (RoleAccess::normalizeRole((string) session()->get('role')) === null) {
            return false;
        }

        $lastActivity = (int) (session()->get('idle_last_activity') ?? time());

        return (time() - $lastActivity) < (new IdleTimeout())->seconds;
    }

    /**
     * Wipes auth session keys and regenerates the session ID. Called when a
     * Home page detects an expired/invalid session.
     */
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

    /**
     * Maps a raw stored role string to a canonical role (or null if unknown).
     * Thin delegate to RoleAccess so controllers can call it as $this->...().
     */
    private function normalizeRole(string $role): ?string
    {
        return RoleAccess::normalizeRole($role);
    }

    /**
     * Returns a redirect to the dashboard that matches the given role. Used after
     * login and on the landing page to send users to the right workspace.
     */
    private function redirectByRole(string $role): RedirectResponse
    {
        return RoleAccess::redirectByRole($role);
    }
}
