<?php
/*
 * "Manage Categories" management page. Lists the standalone SERVICE categories from
 * the `category` table (FA/SWPS/EDA — the ones with no matching sector; a sector acts
 * as its own service category) and lets an admin add/rename/archive/restore them via
 * the shared #categoryActionModal (see category-modal.php + categories-modal.js).
 *
 * Server-side guards (Lookups\CategoryController): a category may not duplicate a sector
 * (code or name), and one still used by an active service cannot be archived. Reuses the
 * Manage Records .records-* layout (managerecord.css) plus the shared lookup badge/action
 * styles (lookupmanagement.css).
 */
helper('dashboard_view');
// category_management_view_data() also supplies $existingCodes (all codes incl.
// archived, for the modal's duplicate check) so this view stays model-free.
extract(category_management_view_data(get_defined_vars()), EXTR_OVERWRITE);

// Counts come from the server bundle (whole table), not the current page below.
$activeCategoryCount   = (int) ($activeCount ?? 0);
$archivedCategoryCount = (int) ($archivedCount ?? 0);
$allCategoryCount      = $activeCategoryCount + $archivedCategoryCount;
$status                = (string) ($status ?? 'active');
$keyword               = (string) ($keyword ?? '');
$listRoute             = (string) ($listRoute ?? 'admin/categories');
$perPage               = (int) ($perPage ?? 50);
$perPageOptions        = ($perPageOptions ?? []) ?: [10, 25, 50, 100];

// Builds a page URL preserving the current database keyword + status + page size.
$categoryPageUrl = static function (int $targetPage) use ($listRoute, $keyword, $status, $perPage): string {
    $params = array_filter([
        'q'        => $keyword,
        'status'   => $status === 'active' ? '' : $status,
        'per_page' => $perPage !== 50 ? (string) $perPage : '',
        'page'     => $targetPage > 1 ? (string) $targetPage : '',
    ], static fn ($value): bool => $value !== '');

    return site_url($listRoute) . ($params === [] ? '' : '?' . http_build_query($params));
};

// "Clear" resets the whole toolbar (keyword + status filter, back to page 1)
// per the one-role-per-control rule; only the page size survives.
$categoryClearUrl = static function () use ($listRoute, $perPage): string {
    $params = $perPage !== 50 ? ['per_page' => (string) $perPage] : [];

    return site_url($listRoute) . ($params === [] ? '' : '?' . http_build_query($params));
};
?>

<?php
$categoryFooter = ($totalRows ?? 0) > 0 ? view('components/table_footer', [
    'fromRecord' => $fromRecord,
    'toRecord' => $toRecord,
    'totalRows' => $totalRows,
    'page' => $page,
    'totalPages' => $totalPages,
    'prevUrl' => $categoryPageUrl(max(1, $page - 1)),
    'nextUrl' => $categoryPageUrl(min($totalPages, $page + 1)),
]) : null;
?>
<?= view('components/card', [
    'icon' => 'tags-fill',
    'title' => 'Manage Categories',
    'cardClass' => 'sector-management records-scroll-panel',
    'attrs' => 'data-category-management-root',
    'bodyView' => 'Lookups/categories-body',
    'bodyData' => get_defined_vars(),
    'footer' => $categoryFooter,
]) ?>

<?= view('Lookups/category-modal', [
	'existingCodes' => $existingCodes,
]) ?>
