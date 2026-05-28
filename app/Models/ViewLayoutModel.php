<?php

namespace App\Models;

/**
 * Provides page labels and navigation state for views.
 */
class ViewLayoutModel
{
    public function pageTitle(string $activePage): string
    {
        return match ($activePage) {
            'family-entry' => 'Add Record',
            'family-manage' => 'Manage Records',
            'accounts' => 'Account Management',
            'audit-trails' => 'Audit Trails',
            'sectors' => 'Sector Management',
            'services' => 'Services and Programs Management',
            default => ucwords(str_replace('-', ' ', $activePage)),
        };
    }

    public function employeePageTitle(string $activePage): string
    {
        if ($activePage === 'dashboard') {
            return 'Workspace';
        }

        return $this->pageTitle($activePage);
    }

    public function navActive(string $activePage, string $targetPage): string
    {
        return $activePage === $targetPage ? 'active' : '';
    }

    public function adminModeLabel(bool $isDeveloper): string
    {
        return $isDeveloper ? 'Developer Mode' : 'Admin Console';
    }
}
