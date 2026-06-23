<?php

if (! function_exists('asset_url')) {
    /**
     * Build a cache-busted public asset URL.
     */
    function asset_url(string $relativePath): string
    {
        $relativePath = ltrim($relativePath, '/');
        $absolutePath = FCPATH . $relativePath;
        $url = base_url($relativePath);

        if (is_file($absolutePath)) {
            $mtime = filemtime($absolutePath);

            if ($mtime !== false) {
                $url .= '?v=' . $mtime;
            }
        }

        return $url;
    }
}

if (! function_exists('asset_groups')) {
    /**
     * Named asset groups used by the dashboard layouts.
     *
     * Keep dependency order here so views only request the group they need.
     *
     * @return array<string, list<string>>
     */
    function asset_groups(): array
    {
        return [
            'dashboard-core-css' => [
                'assets/bootstrap/css/bootstrap.min.css',
                'assets/bootstrap-icons/font/bootstrap-icons.min.css',
                'assets/datatables/css/dataTables.bootstrap5.min.css',
            ],
            'admin-dashboard-css' => [
                'css/sb-admin-adapter.css',
                'css/managerecord.css',
                'css/lookupmanagement.css',
                'css/audittrails.css',
                'css/accounts.css',
                'css/familymodal.css',
                'css/session-timeout.css',
            ],
            'employee-dashboard-css' => [
                'css/sb-admin-adapter.css',
                'css/managerecord.css',
                'css/audittrails.css',
                'css/familymodal.css',
                'css/accounts.css',
                'css/session-timeout.css',
            ],
            'viewer-dashboard-css' => [
                'css/sb-admin-adapter.css',
                'css/managerecord.css',
                'css/lookupmanagement.css',
                'css/accounts.css',
                'css/familymodal.css',
                'css/session-timeout.css',
            ],
            'dashboard-vendor-js' => [
                'assets/jquery/jquery-3.7.1.min.js',
                'assets/bootstrap/js/bootstrap.bundle.min.js',
                'assets/datatables/js/dataTables.min.js',
                'assets/datatables/js/dataTables.bootstrap5.min.js',
            ],
            'admin-dashboard-js' => [
                'assets/js/dashboard/view-interactions.js',
                'assets/js/dashboard/family-list.js',
                'assets/js/dashboard/family-datatable.js',
                'assets/js/dashboard/management-forms.js',
                'assets/js/dashboard/lookup-search.js',
                'assets/js/dashboard/audit-filters.js',
                'assets/js/dashboard/dashboard-modal-loader.js',
                'assets/js/dashboard/manage-family-modal.js',
                'assets/js/dashboard/account-form-modal.js',
                'assets/js/dashboard/accounts-modal.js',
                'assets/js/dashboard/sectors-modal.js',
                'assets/js/dashboard/services-modal.js',
                'assets/js/dashboard/categories-modal.js',
                'assets/js/dashboard/audit-trails-modal.js',
                'assets/js/dashboard/audit-detail-modal.js',
            ],
            'employee-dashboard-js' => [
                'assets/js/dashboard/view-interactions.js',
                'assets/js/dashboard/family-list.js',
                'assets/js/dashboard/family-datatable.js',
                'assets/js/dashboard/audit-filters.js',
                'assets/js/dashboard/lookup-search.js',
                'assets/js/dashboard/dashboard-modal-loader.js',
                'assets/js/dashboard/manage-family-modal.js',
                'assets/js/dashboard/account-form-modal.js',
            ],
            'viewer-dashboard-js' => [
                'assets/js/dashboard/view-interactions.js',
                'assets/js/dashboard/family-list.js',
                'assets/js/dashboard/family-datatable.js',
                'assets/js/dashboard/lookup-search.js',
                'assets/js/dashboard/dashboard-modal-loader.js',
                'assets/js/dashboard/manage-family-modal.js',
                'assets/js/dashboard/account-form-modal.js',
            ],
        ];
    }
}

if (! function_exists('asset_group')) {
    /**
     * @return list<string>
     */
    function asset_group(string $groupName): array
    {
        $groups = asset_groups();

        return $groups[$groupName] ?? [];
    }
}

if (! function_exists('asset_attributes')) {
    /**
     * @param array<string, scalar|null> $attributes
     */
    function asset_attributes(array $attributes): string
    {
        $html = '';

        foreach ($attributes as $name => $value) {
            if ($value === null) {
                continue;
            }

            $html .= ' ' . esc((string) $name, 'attr') . '="' . esc((string) $value, 'attr') . '"';
        }

        return $html;
    }
}

if (! function_exists('asset_link_tag')) {
    /**
     * @param array<string, scalar|null> $attributes
     */
    function asset_link_tag(string $relativePath, array $attributes = []): string
    {
        $attributes = ['rel' => 'stylesheet', 'href' => asset_url($relativePath)] + $attributes;

        return '<link' . asset_attributes($attributes) . '>';
    }
}

if (! function_exists('asset_script_tag')) {
    /**
     * @param array<string, scalar|null> $attributes
     */
    function asset_script_tag(string $relativePath, array $attributes = []): string
    {
        $attributes = ['src' => asset_url($relativePath)] + $attributes;

        return '<script' . asset_attributes($attributes) . '></script>';
    }
}

if (! function_exists('asset_tags')) {
    /**
     * Render every asset in a named group.
     */
    function asset_tags(string $groupName): string
    {
        $tags = [];

        foreach (asset_group($groupName) as $relativePath) {
            if (str_ends_with($relativePath, '.css')) {
                $tags[] = asset_link_tag($relativePath);
                continue;
            }

            if (str_ends_with($relativePath, '.js')) {
                $tags[] = asset_script_tag($relativePath);
            }
        }

        return implode(PHP_EOL, $tags);
    }
}
