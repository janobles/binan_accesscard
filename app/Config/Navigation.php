<?php

namespace Config;

/**
 * The sidebar manifest. One entry per page: what it is called, where it lives,
 * which heading groups it, and which roles may reach it. The sidebar view, the
 * layout's page title, and RoleNavFilter all read this, so a new page is one
 * entry here rather than an edit in three layouts.
 *
 * Order is display order. Headings group consecutive entries.
 */
class Navigation
{
    private const ALL_STAFF = ['Developer', 'Admin', 'Encoder', 'Viewer'];
    private const MANAGERS  = ['Developer', 'Admin'];

    /**
     * @var list<array{key: string, label: string, icon: string, route: string, heading: string, roles: list<string>}>
     */
    public const LINKS = [
        [
            'key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'bi-house-door',
            'route' => 'dashboard', 'heading' => 'Core', 'roles' => self::ALL_STAFF,
        ],
        [
            'key' => 'records', 'label' => 'Family Records', 'icon' => 'bi-people-fill',
            'route' => 'records', 'heading' => 'Profiling', 'roles' => self::ALL_STAFF,
        ],
        [
            'key' => 'reference-data', 'label' => 'Reference Data', 'icon' => 'bi-collection',
            'route' => 'reference-data', 'heading' => 'Profiling', 'roles' => self::ALL_STAFF,
        ],
        [
            'key' => 'cards', 'label' => 'Access Cards', 'icon' => 'bi-qr-code',
            'route' => 'cards', 'heading' => 'Distribution',
            'roles' => ['Developer', 'Admin', 'Encoder'],
        ],
        [
            'key' => 'distribution', 'label' => 'Distribution', 'icon' => 'bi-clipboard-check-fill',
            'route' => 'distribution', 'heading' => 'Distribution',
            'roles' => ['Developer', 'Admin', 'Viewer'],
        ],
        [
            'key' => 'accounts', 'label' => 'Account Management', 'icon' => 'bi-person-fill-gear',
            'route' => 'accounts', 'heading' => 'Administration', 'roles' => self::MANAGERS,
        ],
        [
            'key' => 'audit-trails', 'label' => 'Audit Trails', 'icon' => 'bi-clock-history',
            'route' => 'audit-trails', 'heading' => 'Administration', 'roles' => self::MANAGERS,
        ],
    ];

    /**
     * Pages that are reachable but carry no sidebar link, because nobody sets out
     * to visit them: they are reached from a toolbar on the page that owns them.
     * Same shape as LINKS minus the display fields.
     *
     * @var array<string, list<string>> page key => allowed roles
     */
    public const UNLISTED = [
        'records-entry'   => ['Developer', 'Admin', 'Encoder'],
        'records-import'  => ['Developer', 'Admin', 'Encoder'],
        'records-profile' => self::ALL_STAFF,
        'records-edit'    => ['Developer', 'Admin', 'Encoder'],
        'records-update'  => ['Developer', 'Admin', 'Encoder'],
    ];

    /**
     * Titles for the unlisted pages. Listed pages take their title from their label.
     *
     * @var array<string, string>
     */
    private const UNLISTED_TITLES = [
        'records-entry'   => 'New Family Record',
        'records-import'  => 'Import Family Records',
        'records-profile' => 'Family Profile',
        'records-edit'    => 'Edit Family Record',
        'records-update'  => 'Edit Family Record',
    ];

    /**
     * Roles allowed on a page key. An unknown key grants nobody, so a typo in a
     * route definition fails closed rather than opening a page to everyone.
     *
     * @return list<string>
     */
    public static function pageRoles(string $key): array
    {
        foreach (self::LINKS as $link) {
            if ($link['key'] === $key) {
                return $link['roles'];
            }
        }

        return self::UNLISTED[$key] ?? [];
    }

    /**
     * Sidebar entries visible to a role, in declaration order.
     *
     * @return list<array{key: string, label: string, icon: string, route: string, heading: string, roles: list<string>}>
     */
    public static function linksFor(string $role): array
    {
        return array_values(array_filter(
            self::LINKS,
            static fn (array $link): bool => in_array($role, $link['roles'], true)
        ));
    }

    /** Page heading for a key, for both listed and unlisted pages. */
    public static function titleFor(string $key): string
    {
        foreach (self::LINKS as $link) {
            if ($link['key'] === $key) {
                return $link['label'];
            }
        }

        return self::UNLISTED_TITLES[$key] ?? ucwords(str_replace('-', ' ', $key));
    }
}
