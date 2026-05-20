<?php

namespace App\Controllers\Concerns;

use CodeIgniter\HTTP\RedirectResponse;

trait HomeRoleAccessTrait
{
    private function requireRole(array $allowedRoles): ?RedirectResponse
    {
        if (! session()->get('is_logged_in')) {
            return redirect()->to(site_url('/'))->with('error', 'Please login first.');
        }

        $currentRole = $this->normalizeRole((string) session()->get('role'));
        $normalizedAllowedRoles = array_values(array_filter(array_map(
            fn (string $role): ?string => $this->normalizeRole($role),
            $allowedRoles
        )));

        if ($currentRole === null) {
            session()->destroy();

            return redirect()->to(site_url('/'))
                ->with('error', 'Your account role is invalid. Please login again or contact an administrator.');
        }

        if (! in_array($currentRole, $normalizedAllowedRoles, true)) {
            return $this->redirectByRole($currentRole)
                ->with('error', 'You do not have access to that page.');
        }

        return null;
    }

    private function redirectByRole(string $role): RedirectResponse
    {
        $normalizedRole = $this->normalizeRole($role);

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

    private function normalizeRole(string $role): ?string
    {
        $normalizedRole = strtolower(trim($role));

        return match ($normalizedRole) {
            'developer' => 'Developer',
            'admin', 'administrator' => 'Admin',
            'user', 'employee' => 'User',
            default => null,
        };
    }
}
