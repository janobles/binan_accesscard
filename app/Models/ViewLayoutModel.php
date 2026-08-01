<?php

namespace App\Models;

/**
 * Provides the shell's mode banner label.
 */
class ViewLayoutModel
{
    /** Banner label shown in the dashboard shell, based on whether the user is a Developer. */
    public function adminModeLabel(bool $isDeveloper): string
    {
        return $isDeveloper ? 'Developer Mode' : 'Admin Console';
    }
}
