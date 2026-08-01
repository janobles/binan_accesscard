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
     * 'Developer'/'Admin'/'Encoder'/'Viewer'/'Scanner', or null if unrecognized.
     * This is the single translation point between the database account_level enum
     * and the rest of the app, and the enum values are the same words, so no
     * aliasing is needed.
     */
    public static function normalizeRole(string $role): ?string
    {
        return match (strtolower(trim($role))) {
            'developer'              => 'Developer',
            'admin', 'administrator' => 'Admin',
            'encoder'                => 'Encoder',
            'viewer'                 => 'Viewer',
            'scanner'                => 'Scanner',
            default                  => null,
        };
    }

    /**
     * The auth session keys set at login. Centralized so every clear path (the
     * idle-timeout filter and the controllers' logout/invalid-session handling)
     * removes exactly the same set - no drift between copies.
     */
    public const SESSION_KEYS = [
        'is_logged_in',
        'user_id',
        'member_id',
        'username',
        'role',
        'auth_token',
        'idle_last_activity',
    ];

    /**
     * Removes the auth session keys (SESSION_KEYS). Pass $regenerate = true to also
     * rotate the session ID - the controller-side clear regenerates; the idle filter
     * clears without regenerating.
     */
    public static function forgetLoginSession(bool $regenerate = false): void
    {
        session()->remove(self::SESSION_KEYS);

        if ($regenerate) {
            session()->regenerate(true);
        }
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
     * RedirectResponse otherwise - to login if not authenticated / session invalid,
     * or to their own dashboard with an error if their role isn't in $allowedRoles.
     * Called at the top of guarded controller actions and the page builder.
     */
    public static function requireRole(array $allowedRoles): ?RedirectResponse
    {
        if (! session()->get('is_logged_in')) {
            return redirect()->to(site_url('login'))->with('error', 'Please login first.');
        }

        $currentRole = self::normalizeRole((string) session()->get('role'));

        if (! self::sessionUserExists()) {
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
     * Returns a redirect to the landing page for a role. Every staff role lands on
     * the shared dashboard; only Scanner has a different home, the kiosk. An
     * invalid role destroys the session and sends back to login.
     */
    public static function redirectByRole(string $role): RedirectResponse
    {
        $normalizedRole = self::normalizeRole($role);

        if ($normalizedRole === 'Scanner') {
            return redirect()->to(site_url('scanner/scan'));
        }

        if ($normalizedRole !== null) {
            return redirect()->to(site_url('dashboard'));
        }

        session()->destroy();

        return redirect()->to(site_url('login'))
            ->with('error', 'Your account role is invalid. Please contact an administrator.');
    }
}
