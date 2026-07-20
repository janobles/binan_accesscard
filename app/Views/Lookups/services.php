<?php
helper('dashboard_view');
// service_management_view_data() supplies $serviceCategoryOptions (managed category
// names from the Manage Categories page + any categories already on services) for the
// Add-Program modal dropdown, so this view stays model-free.
extract(service_management_view_data(get_defined_vars()), EXTR_OVERWRITE);
$serviceCategoryOptions = $serviceCategoryOptions ?? [];

// Counts come from the server bundle (whole table), not the current page below.
$activeServiceCount   = (int) ($activeCount ?? 0);
$archivedServiceCount = (int) ($archivedCount ?? 0);
$allServiceCount      = $activeServiceCount + $archivedServiceCount;
$status               = (string) ($status ?? 'active');
$keyword              = (string) ($keyword ?? '');
$listRoute            = (string) ($listRoute ?? 'admin/reference-data');
$tabParam             = (string) ($tabParam ?? '');
$perPage              = (int) ($perPage ?? 25);
$perPageOptions       = ($perPageOptions ?? []) ?: [10, 25, 50, 100];
// Read-only roles (Viewer) see the list without Add / Edit / Archive / Restore.
// Defaults true so the admin/developer services page is unaffected.
$canManage            = (bool) ($canManage ?? true);

// Builds a page URL preserving the current database keyword + status + page size.
$servicePageUrl = static function (int $targetPage) use ($listRoute, $keyword, $status, $perPage, $tabParam): string {
    $params = array_filter([
        'tab'      => $tabParam,
        'q'        => $keyword,
        'status'   => $status === 'active' ? '' : $status,
        'per_page' => $perPage !== 25 ? (string) $perPage : '',
        'page'     => $targetPage > 1 ? (string) $targetPage : '',
    ], static fn ($value): bool => $value !== '');

    return site_url($listRoute) . ($params === [] ? '' : '?' . http_build_query($params));
};

// "Clear" resets the whole toolbar (keyword + status filter, back to page 1)
// per the one-role-per-control rule; only the page size survives.
$serviceClearUrl = static function () use ($listRoute, $perPage, $tabParam): string {
    $params = array_filter([
        'tab'      => $tabParam,
        'per_page' => $perPage !== 25 ? (string) $perPage : '',
    ], static fn ($value): bool => $value !== '');

    return site_url($listRoute) . ($params === [] ? '' : '?' . http_build_query($params));
};
?>

<?php /* Toolbar above the card, Manage Records standard (components/records_toolbar_server +
         records-filter-panel.js live-apply + pills). Melbranch hooks preserved:
         data-service-management-root, data-lookup-search local filter, .js-service-modal-open +
         data-service-* attributes, the service-modal include. */ ?>
<?= view('components/toolbar', [
    'formAction' => site_url($listRoute),
    'formAria' => 'Search all services',
    'searchPlaceholder' => 'Search all services...',
    'keyword' => $keyword,
    'clearUrl' => $serviceClearUrl(),
    'pillsId' => 'serviceFilterPills',
    'hiddenHtml' => ($tabParam !== '' ? '<input type="hidden" name="tab" value="' . esc($tabParam, 'attr') . '">' : '')
        . ($perPage !== 25 ? '<input type="hidden" name="per_page" value="' . esc((string) $perPage, 'attr') . '">' : ''),
    'actionsHtml' => $canManage ? '<button class="' . btn('add') . ' flex-fill js-service-modal-open" type="button" data-service-mode="create">Add Program</button>' : '',
    'filterGroups' => [[
        'name' => 'status',
        'label' => 'Status',
        'options' => [
            ['value' => 'active', 'label' => "Active ({$activeServiceCount})", 'checked' => $status === 'active', 'default' => true],
            ['value' => 'archived', 'label' => "Archived ({$archivedServiceCount})", 'pill' => 'Archived', 'checked' => $status === 'archived'],
            ['value' => 'all', 'label' => "All ({$allServiceCount})", 'checked' => $status === 'all'],
        ],
    ]],
]) ?>
<?php
$serviceFooter = ($totalRows ?? 0) > 0 ? view('components/table_footer', [
    'fromRecord' => $fromRecord,
    'toRecord' => $toRecord,
    'totalRows' => $totalRows,
    'page' => $page,
    'totalPages' => $totalPages,
    'prevUrl' => $servicePageUrl(max(1, $page - 1)),
    'nextUrl' => $servicePageUrl(min($totalPages, $page + 1)),
]) : null;
?>
<?= view('components/card', [
    'icon' => 'grid-fill',
    'title' => 'Services and Programs',
    'cardClass' => 'sector-management',
    'attrs' => 'data-service-management-root',
    'bodyView' => 'Lookups/services-body',
    'bodyData' => get_defined_vars(),
    'footer' => $serviceFooter,
]) ?>

<?php if ($canManage): ?>
<?= view('Lookups/service-modal', [
	'serviceCategoryOptions' => $serviceCategoryOptions,
	'serviceNextCodeMap' => $serviceNextCodeMap ?? [],
	'existingShortcodes' => $existingShortcodes ?? [],
]) ?>
<?php endif; ?>
