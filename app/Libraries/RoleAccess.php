<?php

namespace App\Libraries;

use CodeIgniter\HTTP\RedirectResponse;

class RoleAccess
{
    public static function normalizeRole(string $role): ?string
    {
        $normalizedRole = strtolower(trim($role));

        return match ($normalizedRole) {
            'developer'              => 'Developer',
            'admin', 'administrator' => 'Admin',
            'user', 'employee'       => 'User',
            default                  => null,
        };
    }

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

    public static function requireRole(array $allowedRoles): ?RedirectResponse
    {
        if (! session()->get('is_logged_in')) {
            return redirect()->to(site_url('/'))->with('error', 'Please login first.');
        }

        if (! self::sessionUserExists()) {
            session()->destroy();

            return redirect()->to(site_url('/'))
                ->with('error', 'Your session is no longer valid after the database update. Please login again.');
        }

        $currentRole = self::normalizeRole((string) session()->get('role'));
        $normalizedAllowedRoles = array_values(array_filter(array_map(
            fn (string $role): ?string => self::normalizeRole($role),
            $allowedRoles
        )));

        if ($currentRole === null) {
            session()->destroy();

            return redirect()->to(site_url('/'))
                ->with('error', 'Your account role is invalid. Please login again or contact an administrator.');
        }

        if (! in_array($currentRole, $normalizedAllowedRoles, true)) {
            return self::redirectByRole($currentRole)
                ->with('error', 'You do not have access to that page.');
        }

        return null;
    }

    public static function redirectByRole(string $role): RedirectResponse
    {
        $normalizedRole = self::normalizeRole($role);

        if ($normalizedRole === 'User') {
            return redirect()->to(site_url('employee/workspace'));
        }

        if ($normalizedRole === 'Admin' || $normalizedRole === 'Developer') {
            return redirect()->to(site_url('admin'));
        }

        session()->destroy();

        return redirect()->to(site_url('/'))
            ->with('error', 'Your account role is invalid. Please contact an administrator.');
    }
}
