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

if (! function_exists('stylesheet_tags')) {
    function stylesheet_tags(array $paths, bool $versioned = true): string
    {
        return implode(PHP_EOL . '    ', array_map(
            static fn (string $path): string => stylesheet_tag($path, $versioned),
            $paths
        ));
    }
}

if (! function_exists('script_tags')) {
    function script_tags(array $paths, bool $versioned = true): string
    {
        return implode(PHP_EOL . '    ', array_map(
            static fn (string $path): string => script_tag($path, $versioned),
            $paths
        ));
    }
}

if (! function_exists('dashboard_styles')) {
    function dashboard_styles(): string
    {
        $styles = [
            'css/dashboard.css',
            'css/mainlayout.css',
            'css/managerecord.css',
            'css/searchbar.css',
            'css/sector.css',
            'css/service.css',
            'css/audittrails.css',
        ];

        return implode(PHP_EOL . '    ', [
            bootstrap_styles(),
            stylesheet_tags($styles),
        ]);
    }
}

if (! function_exists('dashboard_scripts')) {
    function dashboard_scripts(bool $includeAdminModules = true): string
    {
        $scripts = [
            'assets/js/dashboard.js',
            'assets/js/search.js',
            'assets/js/familymodal.js',
        ];

        if ($includeAdminModules) {
            $scripts[] = 'assets/js/accountmanagement.js';
            $scripts[] = 'assets/js/sector_service_modal.js';
        }

        return implode(PHP_EOL . '    ', [
            bootstrap_scripts(),
            script_tags($scripts),
        ]);
    }
}

if (! function_exists('login_styles')) {
    function login_styles(): string
    {
        return implode(PHP_EOL . '    ', [
            bootstrap_styles(false),
            stylesheet_tag('css/login.css', true),
        ]);
    }
}

if (! function_exists('login_scripts')) {
    function login_scripts(): string
    {
        return bootstrap_scripts();
    }
}

if (! function_exists('account_management_styles')) {
    function account_management_styles(): string
    {
        return stylesheet_tag('css/accountmanagement.css', true);
    }
}

if (! function_exists('family_modal_styles')) {
    function family_modal_styles(): string
    {
        return stylesheet_tag('css/familymodal.css', true);
    }
}
