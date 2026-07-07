<?php

if (! function_exists('asset_url')) {
    /**
     * Cache-busted URL for any public asset (css, js, jquery, json, images).
     *
     * Single source of truth for asset versioning across all views: instead of
     * each template hand-building `base_url(...) . '?v=' . filemtime(...)`, they
     * call asset_url('css/foo.css'). Falls back to time() when the file is
     * missing so a missing asset never breaks the page (it just won't cache).
     */
    function asset_url(string $relativePath): string
    {
        $absolute = FCPATH . ltrim($relativePath, '/');
        $version  = is_file($absolute) ? (string) filemtime($absolute) : (string) time();

        return base_url($relativePath) . '?v=' . $version;
    }
}

if (! function_exists('asset_styles')) {
    /**
     * Stylesheet manifest: the single source of truth for every CSS file the app
     * loads. Views iterate the returned relative paths and wrap each with
     * asset_url() for cache-busting. Per-role order is significant (CSS cascade).
     *
     * Contexts: head (shared <head> CSS), admin|employee|viewer (dashboard
     * shells), login. Unknown context returns [].
     */
    function asset_styles(string $context): array
    {
        $manifest = [
            'head' => [
                'assets/sb-admin/css/styles.css',
                'assets/bootstrap-icons/font/bootstrap-icons.min.css',
            ],
            'admin' => [
                'assets/datatables/css/dataTables.bootstrap5.min.css',
                'css/managerecord.css',
                'css/lookupmanagement.css',
                'css/audittrails.css',
                'css/accounts.css',
                'css/familymodal.css',
                'css/session-timeout.css',
            ],
            'employee' => [
                'assets/datatables/css/dataTables.bootstrap5.min.css',
                'css/managerecord.css',
                'css/audittrails.css',
                'css/familymodal.css',
                'css/accounts.css',
                'css/session-timeout.css',
            ],
            'viewer' => [
                'assets/datatables/css/dataTables.bootstrap5.min.css',
                'css/managerecord.css',
                'css/lookupmanagement.css',
                'css/accounts.css',
                'css/familymodal.css',
                'css/session-timeout.css',
            ],
            'login' => [
                'assets/bootstrap/css/bootstrap.min.css',
                'css/login.css',
            ],
            'scanner' => [
                'css/scanner-reports.css',
                'css/scanner-scan.css',
            ],
        ];

        return $manifest[$context] ?? [];
    }
}

if (! function_exists('asset_scripts')) {
    /**
     * Script manifest: the single source of truth for every JS/jQuery file the
     * app loads. Views iterate the returned relative paths and wrap each with
     * asset_url(). Load order is significant (dependencies first).
     *
     * `session-timeout.js` is intentionally absent — each layout renders it
     * inline because its data-* attributes differ per role.
     *
     * Contexts: core (jQuery + Bootstrap bundle, shared), admin|employee|viewer
     * (dashboard shells), login. Unknown context returns [].
     */
    function asset_scripts(string $context): array
    {
        $manifest = [
            'core' => [
                'assets/jquery/jquery-3.7.1.min.js',
                'assets/bootstrap/js/bootstrap.bundle.min.js',
                'assets/sb-admin/js/scripts.js',
            ],
            'admin' => [
                'assets/js/dashboard/view-interactions.js',
                'assets/datatables/js/dataTables.min.js',
                'assets/datatables/js/dataTables.bootstrap5.min.js',
                'assets/js/dashboard/family-datatable.js',
                'assets/js/dashboard/family-list.js',
                'assets/js/dashboard/management-forms.js',
                'assets/js/dashboard/lookup-search.js',
                'assets/js/dashboard/audit-filters.js',
                'assets/js/dashboard/dashboard-modal-loader.js',
                'assets/js/dashboard/manage-family-modal.js',
                'assets/js/dashboard/family-import.js',
                'assets/js/dashboard/account-form-modal.js',
                'assets/js/dashboard/accounts-modal.js',
                'assets/js/dashboard/sectors-modal.js',
                'assets/js/dashboard/services-modal.js',
                'assets/js/dashboard/categories-modal.js',
                'assets/js/dashboard/audit-trails-modal.js',
                'assets/js/dashboard/audit-detail-modal.js',
            ],
            'employee' => [
                'assets/js/dashboard/view-interactions.js',
                'assets/datatables/js/dataTables.min.js',
                'assets/datatables/js/dataTables.bootstrap5.min.js',
                'assets/js/dashboard/family-datatable.js',
                'assets/js/dashboard/family-list.js',
                'assets/js/dashboard/audit-filters.js',
                'assets/js/dashboard/lookup-search.js',
                'assets/js/dashboard/dashboard-modal-loader.js',
                'assets/js/dashboard/manage-family-modal.js',
                'assets/js/dashboard/family-import.js',
                'assets/js/dashboard/account-form-modal.js',
            ],
            'viewer' => [
                'assets/js/dashboard/view-interactions.js',
                'assets/datatables/js/dataTables.min.js',
                'assets/datatables/js/dataTables.bootstrap5.min.js',
                'assets/js/dashboard/family-datatable.js',
                'assets/js/dashboard/family-list.js',
                'assets/js/dashboard/lookup-search.js',
                'assets/js/dashboard/dashboard-modal-loader.js',
                'assets/js/dashboard/manage-family-modal.js',
                'assets/js/dashboard/account-form-modal.js',
            ],
            'login' => [
                'assets/js/login.js',
            ],
            'scanner' => [
                'vendor/chart.js/chart.umd.min.js',
                'assets/js/dashboard/scanner-reports.js',
            ],
        ];

        return $manifest[$context] ?? [];
    }
}
