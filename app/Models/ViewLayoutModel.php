<?php

namespace App\Models;

/**
 * Provides page labels and navigation state for views.
 */
class ViewLayoutModel
{
    public function pageTitle(string $activePage): string
    {
        if ($activePage === 'family-manage') {
            return 'Manage Member';
        }

        return ucwords(str_replace('-', ' ', $activePage));
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
