<?php

if (! function_exists('asset_url')) {
    function asset_url(string $path, bool $versioned = false): string
    {
        $path = ltrim($path, '/');
        $url = base_url($path);
        $file = FCPATH . str_replace('/', DIRECTORY_SEPARATOR, $path);

        if ($versioned && is_file($file)) {
            $url .= '?v=' . filemtime($file);
        }

        return $url;
    }
}

if (! function_exists('stylesheet_tag')) {
    function stylesheet_tag(string $path, bool $versioned = false): string
    {
        return '<link rel="stylesheet" href="' . esc(asset_url($path, $versioned), 'attr') . '">';
    }
}

if (! function_exists('script_tag')) {
    function script_tag(string $path, bool $versioned = false): string
    {
        return '<script src="' . esc(asset_url($path, $versioned), 'attr') . '"></script>';
    }
}

if (! function_exists('bootstrap_styles')) {
    function bootstrap_styles(bool $includeIcons = true): string
    {
        $tags = [
            stylesheet_tag('bootstrap/css/bootstrap.min.css'),
        ];

        if ($includeIcons) {
            $tags[] = '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">';
        }

        return implode(PHP_EOL . '    ', $tags);
    }
}

if (! function_exists('bootstrap_scripts')) {
    function bootstrap_scripts(): string
    {
        return script_tag('bootstrap/js/bootstrap.bundle.min.js');
    }
}
