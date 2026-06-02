<?php

if (! function_exists('versioned_css_link')) {
    /**
     * Builds a <link> tag for a CSS file with a cache-busting ?v= timestamp from
     * the file's mtime. Called by views/layouts to load styles. Frontend: outputs
     * the stylesheet tag so browsers refetch CSS after a deploy.
     */
    function versioned_css_link(string $relativeCssPath): string
    {
        $trimmedPath = ltrim($relativeCssPath, '/');
        $absolutePath = FCPATH . $trimmedPath;
        $version = is_file($absolutePath) ? filemtime($absolutePath) : time();

        return '<link rel="stylesheet" href="' . base_url($trimmedPath) . '?v=' . $version . '">';
    }
}

if (! function_exists('admin_dashboard_style_links')) {
    /**
     * Returns the full set of cache-busted admin dashboard stylesheet tags as one
     * string. Frontend: emitted in the admin layout <head>.
     */
    function admin_dashboard_style_links(): string
    {
        $styles = [
            'assets/css/admin-layout.css',
            'assets/css/admin-components.css',
            'assets/css/admin-modal.css',
            'assets/css/admin-responsive.css',
        ];

        $links = array_map(static fn (string $path): string => versioned_css_link($path), $styles);

        return implode("\n", $links);
    }
}
