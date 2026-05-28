<?php

if (! function_exists('versioned_css_link')) {
    function versioned_css_link(string $relativeCssPath): string
    {
        $trimmedPath = ltrim($relativeCssPath, '/');
        $absolutePath = FCPATH . $trimmedPath;
        $version = is_file($absolutePath) ? filemtime($absolutePath) : time();

        return '<link rel="stylesheet" href="' . base_url($trimmedPath) . '?v=' . $version . '">';
    }
}

if (! function_exists('admin_dashboard_style_links')) {
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
