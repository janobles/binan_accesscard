<?php
helper('dashboard_view');
// sector_management_view_data() also supplies the Add-Sector modal data
// ($existingShortcodes, for the inline duplicate check) so this view never
// instantiates a model itself. Sectors are flat classifications — no category.
extract(sector_management_view_data(get_defined_vars()), EXTR_OVERWRITE);

// Counts come from the server bundle (whole table), not the current page below.
$activeSectorCount   = (int) ($activeCount ?? 0);
$archivedSectorCount = (int) ($archivedCount ?? 0);
$allSectorCount      = $activeSectorCount + $archivedSectorCount;
$status              = (string) ($status ?? 'active');
$keyword             = (string) ($keyword ?? '');
$listRoute           = (string) ($listRoute ?? 'admin/sectors');
$perPage             = (int) ($perPage ?? 50);
$perPageOptions      = ($perPageOptions ?? []) ?: [10, 25, 50, 100];
// Read-only roles (Viewer) see the list without Add / Edit / Archive / Restore.
// Defaults true so the admin/developer sector page is unaffected.
$canManage           = (bool) ($canManage ?? true);

// Builds a page URL preserving the current database keyword + status + page size.
$sectorPageUrl = static function (int $targetPage) use ($listRoute, $keyword, $status, $perPage): string {
    $params = array_filter([
        'q'        => $keyword,
        'status'   => $status === 'active' ? '' : $status,
        'per_page' => $perPage !== 50 ? (string) $perPage : '',
        'page'     => $targetPage > 1 ? (string) $targetPage : '',
    ], static fn ($value): bool => $value !== '');

    return site_url($listRoute) . ($params === [] ? '' : '?' . http_build_query($params));
};

// "Clear" drops the keyword (and resets to page 1) but keeps status + page size.
$sectorClearUrl = static function () use ($listRoute, $status, $perPage): string {
    $params = array_filter([
        'status'   => $status === 'active' ? '' : $status,
        'per_page' => $perPage !== 50 ? (string) $perPage : '',
    ], static fn ($value): bool => $value !== '');

    return site_url($listRoute) . ($params === [] ? '' : '?' . http_build_query($params));
};
?>

<?php /* Reuses the Manage Records .records-* layout (managerecord.css). All melbranch hooks preserved:
         data-sector-management-root, #sector-status-select (data-lookup-status-select),
         data-lookup-search local filter, .js-sector-modal-open + data-sector-* attributes, the sector-modal include. */ ?>
<?php
$sectorFooter = ($totalRows ?? 0) > 0 ? view('components/table_footer', [
    'fromRecord' => $fromRecord,
    'toRecord' => $toRecord,
    'totalRows' => $totalRows,
    'page' => $page,
    'totalPages' => $totalPages,
    'prevUrl' => $sectorPageUrl(max(1, $page - 1)),
    'nextUrl' => $sectorPageUrl(min($totalPages, $page + 1)),
]) : null;
?>
<?= view('components/card', [
    'icon' => 'diagram-3-fill',
    'title' => 'Sector Management',
    'cardClass' => 'sector-management records-scroll-panel',
    'attrs' => 'data-sector-management-root',
    'bodyView' => 'Lookups/sectors-body',
    'bodyData' => get_defined_vars(),
    'footer' => $sectorFooter,
]) ?>

<?php if ($canManage): ?>
<?= view('Lookups/sector-modal', [
	'existingShortcodes' => $existingShortcodes,
]) ?>
<?php endif; ?>
