<?php
/**
 * Reference Data body (every role).
 *
 * Rendered inside layout.php as the `reference-data` page body. The lookup
 * tables share one page, switched by ?tab=. Which tabs exist and whether the
 * Add/Edit/Archive controls render is decided by DashboardPageBuilder
 * ($referenceTabs, $canManageLookups): managers get all four tables and the
 * write controls, everyone else gets the two read-only lists.
 */

$referenceTabs = (array) ($referenceTabs ?? ['sectors', 'services']);
$referenceTab = (string) ($referenceTab ?? 'sectors');
$canManageLookups = (bool) ($canManageLookups ?? false);

$tabLabels = [
    'sectors'    => 'Sectors',
    'services'   => 'Services & Programs',
    'categories' => 'Categories',
    'subsidy-types'   => 'Subsidy Types',
];
$tabs = [];
foreach ($referenceTabs as $tabKey) {
    $tabs[] = ['key' => $tabKey, 'label' => $tabLabels[$tabKey] ?? ucfirst((string) $tabKey)];
}
?>
<?= view('components/page_tabs', [
    'tabs' => $tabs,
    'active' => $referenceTab,
    'baseUrl' => 'reference-data',
]) ?>
<?php if ($referenceTab === 'sectors'): ?>
    <?= view('Lookups/sectors', [
        'sectors' => $sectors ?? [],
        'sectorShortcodeOptions' => $sectorShortcodeOptions ?? [],
        'lookupStatus' => $lookupStatus ?? 'active',
        'canManage' => $canManageLookups,
        'canRestore' => $canManageLookups,
        'tabParam' => 'sectors',
    ]) ?>
<?php elseif ($referenceTab === 'services'): ?>
    <?= view('Lookups/services', [
        'services' => $services ?? [],
        'lookupStatus' => $lookupStatus ?? 'active',
        'canManage' => $canManageLookups,
        'canRestore' => $canManageLookups,
        'tabParam' => 'services',
    ]) ?>
<?php elseif ($referenceTab === 'categories'): ?>
    <?= view('Lookups/categories', [
        'categories' => $categories ?? [],
        'lookupStatus' => $lookupStatus ?? 'active',
        'canManage' => $canManageLookups,
        'canRestore' => $canManageLookups,
        'tabParam' => 'categories',
    ]) ?>
<?php elseif ($referenceTab === 'subsidy-types'): ?>
    <?php
    // Server-side search/pagination bundle, same shape the Sectors/Services/
    // Categories tabs get from buildLookupListData().
    $subsidyTypeList = $subsidyTypeListData ?? [];
    $subsidyTypePageUrl = static fn (int $p): string => site_url('reference-data') . '?' . http_build_query(array_filter([
        'tab'      => 'subsidy-types',
        'q'        => $subsidyTypeList['keyword'] ?? '',
        'status'   => ($subsidyTypeList['status'] ?? 'all') !== 'all' ? ($subsidyTypeList['status'] ?? '') : '',
        'per_page' => (int) ($subsidyTypeList['perPage'] ?? 25),
        'page'     => $p,
    ], static fn ($v): bool => $v !== '' && $v !== null));
    ?>
    <?= view('components/card', [
        'icon' => 'box-seam',
        'title' => 'Subsidy Types',
        'cardClass' => 'sector-management',
        'bodyView' => 'Admin/subsidy-types-body',
        'bodyData' => [
            'subsidyTypes'          => $subsidyTypes ?? [],
            'currentRole'           => $currentRole ?? '',
            'canManageSubsidyTypes' => $canManageLookups,
            'keyword'               => (string) ($subsidyTypeList['keyword'] ?? ''),
            'status'                => (string) ($subsidyTypeList['status'] ?? 'all'),
            'perPage'               => (int) ($subsidyTypeList['perPage'] ?? 25),
            'perPageOptions'        => ($subsidyTypeList['perPageOptions'] ?? []) ?: [10, 25, 50, 100],
            'listRoute'             => 'reference-data',
        ],
        'footer' => view('components/table_footer', [
            'fromRecord' => (int) ($subsidyTypeList['fromRecord'] ?? 0),
            'toRecord'   => (int) ($subsidyTypeList['toRecord'] ?? 0),
            'totalRows'  => (int) ($subsidyTypeList['totalRows'] ?? 0),
            'page'       => (int) ($subsidyTypeList['page'] ?? 1),
            'totalPages' => (int) ($subsidyTypeList['totalPages'] ?? 1),
            'pageUrl'    => $subsidyTypePageUrl,
        ]),
    ]) ?>
    <?= view('Admin/subsidy-type-modal') ?>
<?php endif; ?>
