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
    <?= view('components/card', [
        'icon' => 'box-seam',
        'title' => 'Subsidy Types',
        'cardClass' => 'sector-management',
        'attrs' => 'data-table-paginate data-paginate-key="subsidy-types" data-paginate-label="subsidy types"',
        'bodyView' => 'Admin/subsidy-types-body',
        'bodyData' => [
            'subsidyTypes' => $subsidyTypes ?? [],
            'currentRole' => $currentRole ?? '',
        ],
        'footer' => view('components/table_footer', ['clientKey' => 'subsidy-types', 'entityLabel' => 'subsidy types']),
    ]) ?>
    <?= view('Admin/subsidy-type-modal') ?>
<?php endif; ?>
