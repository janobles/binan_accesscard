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
