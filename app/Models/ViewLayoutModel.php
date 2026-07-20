<?php

namespace App\Models;

/**
 * Provides page labels and navigation state for views.
 */
class ViewLayoutModel
{
    /**
     * Maps an active-page key to the heading shown in the admin shell. Frontend:
     * used by DashboardPageBuilder to set the page title.
     */
    public function pageTitle(string $activePage): string
    {
        return match ($activePage) {
            'family-manage' => 'Manage Records',
            'accounts' => 'Account Management',
            'audit-trails' => 'Audit Trails',
            'reference-data' => 'Reference Data',
            'distribution' => 'Aid Distribution',
            default => ucwords(str_replace('-', ' ', $activePage)),
        };
    }

    /** Employee-shell variant of pageTitle. */
    public function employeePageTitle(string $activePage): string
    {
        if ($activePage === 'dashboard') {
            return 'Dashboard';
        }

        return $this->pageTitle($activePage);
    }

    /** Returns 'active' when a nav item matches the current page, for CSS highlighting. */
    public function navActive(string $activePage, string $targetPage): string
    {
        return $activePage === $targetPage ? 'active' : '';
    }

    /** Banner label shown in the admin shell, based on whether the user is a Developer. */
    public function adminModeLabel(bool $isDeveloper): string
    {
        return $isDeveloper ? 'Developer Mode' : 'Admin Console';
    }
}
