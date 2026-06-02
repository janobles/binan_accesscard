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
     * Canonicalizes a raw role string to the app's role labels
     * 'Developer'/'Admin'/'Employee', or null if unrecognized. This is the single
     * translation point between the database enum (which stores the employee role
     * as the legacy 'User') and the rest of the app, which uses 'Employee'. The DB
     * value 'User' is accepted here and surfaced everywhere else as 'Employee'.
     */
    public static function normalizeRole(string $role): ?string
    {
        $normalizedRole = strtolower(trim($role));

        return match ($normalizedRole) {
            'developer'              => 'Developer',
            'admin', 'administrator' => 'Admin',
            'user', 'employee'       => 'Employee',
            default                  => null,
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

        if (! self::sessionUserExists()) {
            session()->destroy();

            return redirect()->to(site_url('login'))
                ->with('error', 'Your session is no longer valid after the database update. Please login again.');
        }

        $currentRole = self::normalizeRole((string) session()->get('role'));
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

        session()->destroy();

        return redirect()->to(site_url('login'))
            ->with('error', 'Your account role is invalid. Please contact an administrator.');
    }
}
