<?php

namespace App\Models;

class ViewLayoutModel
{
    public function pageTitle(string $activePage): string
    {
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
