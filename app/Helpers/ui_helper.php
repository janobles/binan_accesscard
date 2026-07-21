<?php

/**
 * UI helper: semantic button roles.
 *
 * btn() is the single source of truth for toolbar button colors. The role
 * table is documented in docs/knowledge/binan-conventions/ui-design-system.md;
 * extend the map there first, then here.
 */

if (! function_exists('btn')) {
    /**
     * Returns the Bootstrap class string for a semantic button role.
     *
     * @throws InvalidArgumentException on a role not in the map, so a typo
     *                                  fails loudly instead of rendering an unstyled button.
     */
    function btn(string $role): string
    {
        $map = [
            'search'   => 'btn btn-primary',
            'generate' => 'btn btn-primary',
            'clear'    => 'btn btn-danger',
            'add'      => 'btn btn-success',
            'import'   => 'btn btn-warning',
            'filter'   => 'btn btn-outline-secondary',
        ];

        if (! isset($map[$role])) {
            throw new InvalidArgumentException('Unknown button role: ' . $role);
        }

        return $map[$role];
    }
}
