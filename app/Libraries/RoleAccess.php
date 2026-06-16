<?php

namespace App\Libraries;

use CodeIgniter\HTTP\RedirectResponse;

/**
 * Central role/authorization helper used by controllers, the page builder, and
 * filters to gate access and route users to the right dashboard.
 */
class RoleAccess
{
    /**
     * Canonicalizes a raw account-level string to the app's role labels
     * 'Developer'/'Admin'/'Employee'/'Viewer', or null if unrecognized. This is the
     * single translation point between the database enum (account_level:
     * 'administrator'/'encoder'/'viewer') and the rest of the app. The legacy enum
     * values ('Admin'/'User') and the app labels are still accepted so stale
     * sessions and pre-migration rows keep resolving: 'administrator'/'Admin' map to
     * the Admin label, 'encoder'/'User' map to Employee. The developer is no longer
     * a DB row (it lives in .env) but its 'developer' value still maps here.
     */
    public static function normalizeRole(string $role): ?string
    {
        $normalizedRole = strtolower(trim($role));

        return match ($normalizedRole) {
            'developer'                    => 'Developer',
            'admin', 'administrator'       => 'Admin',
            'user', 'encoder', 'employee'  => 'Employee',
            'viewer'                       => 'Viewer',
            default                        => null,
        };
    }

    /** True if the session's user_id still maps to a real `users` row (post-DB-change safety). */
    public static function sessionUserExists(): bool
    {
        $userId = (int) session()->get('user_id');

        if ($userId <= 0) {
            return false;
        }

        $db = db_connect();

        if (! $db->tableExists('users')) {
            return false;
        }

        return $db->table('users')
            ->where('userID', $userId)
            ->countAllResults() > 0;
    }

    /**
     * The main access gate: returns null when the current user may proceed, or a
     * RedirectResponse otherwise — to login if not authenticated / session invalid,
     * or to their own dashboard with an error if their role isn't in $allowedRoles.
     * Called at the top of guarded controller actions and the page builder.
     */
    public static function requireRole(array $allowedRoles): ?RedirectResponse
    {
        if (! session()->get('is_logged_in')) {
            return redirect()->to(site_url('login'))->with('error', 'Please login first.');
        }

        $currentRole = self::normalizeRole((string) session()->get('role'));

        // The developer logs in from .env (no users row, user_id 0), so the row
        // existence check does not apply to it.
        if ($currentRole !== 'Developer' && ! self::sessionUserExists()) {
            session()->destroy();

            return redirect()->to(site_url('login'))
                ->with('error', 'Your session is no longer valid after the database update. Please login again.');
        }

        $normalizedAllowedRoles = array_values(array_filter(array_map(
            fn (string $role): ?string => self::normalizeRole($role),
            $allowedRoles
        )));

        if ($currentRole === null) {
            session()->destroy();

            return redirect()->to(site_url('login'))
                ->with('error', 'Your account role is invalid. Please login again or contact an administrator.');
        }

        if (! in_array($currentRole, $normalizedAllowedRoles, true)) {
            return self::redirectByRole($currentRole)
                ->with('error', 'You do not have access to that page.');
        }

        return null;
    }

    /**
     * Returns a redirect to the dashboard matching the role: employees to
     * `employee/workspace`, admins/developers to `admin/dashboard`; an invalid
     * role destroys the session and sends back to login. Used after login and by
     * requireRole() to bounce users to where they belong.
     */
    public static function redirectByRole(string $role): RedirectResponse
    {
        $normalizedRole = self::normalizeRole($role);

        if ($normalizedRole === 'Employee') {
            return redirect()->to(site_url('employee/workspace'));
        }

        if ($normalizedRole === 'Admin' || $normalizedRole === 'Developer') {
            return redirect()->to(site_url('admin/dashboard'));
        }

        // Viewer has a read-only dashboard (Viewer\DashboardController).
        if ($normalizedRole === 'Viewer') {
            return redirect()->to(site_url('viewer/dashboard'));
        }

        session()->destroy();

        return redirect()->to(site_url('login'))
            ->with('error', 'Your account role is invalid. Please contact an administrator.');
    }
}
