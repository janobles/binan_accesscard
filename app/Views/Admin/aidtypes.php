<?php
/**
 * "Subsidy Types" reference page. Lists the `subsidy` table aid types (Financial/
 * Rice/Grocery) used by distribution batches, and lets an admin add/archive/restore/
 * delete them (Admin\AidTypesController + aidtype-create-modal.php). Mirrors the
 * Sectors/Services/Categories reference pages: same toolbar (search all + status
 * filter + clear), same in-card table_controls (page search + show entries), same
 * table_footer pagination.
 */
helper('dashboard_view');
extract(aid_type_management_view_data(get_defined_vars()), EXTR_OVERWRITE);

// Counts come from the server bundle (whole table), not the current page below.
$activeAidTypeCount   = (int) ($activeCount ?? 0);
$archivedAidTypeCount = (int) ($archivedCount ?? 0);
$allAidTypeCount      = $activeAidTypeCount + $archivedAidTypeCount;
$status               = (string) ($status ?? 'active');
$keyword              = (string) ($keyword ?? '');
$listRoute            = (string) ($listRoute ?? 'admin/reference-data');
$tabParam              = (string) ($tabParam ?? '');
$perPage              = (int) ($perPage ?? 25);
$perPageOptions       = ($perPageOptions ?? []) ?: [10, 25, 50, 100];

// Builds a page URL preserving the current database keyword + status + page size.
$aidTypePageUrl = static function (int $targetPage) use ($listRoute, $keyword, $status, $perPage, $tabParam): string {
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
$aidTypeClearUrl = static function () use ($listRoute, $perPage, $tabParam): string {
    $params = array_filter([
        'tab'      => $tabParam,
        'per_page' => $perPage !== 25 ? (string) $perPage : '',
    ], static fn ($value): bool => $value !== '');

    return site_url($listRoute) . ($params === [] ? '' : '?' . http_build_query($params));
};
?>

<?php /* Toolbar above the card, Manage Records standard (components/toolbar). */ ?>
<?= view('components/toolbar', [
    'formAction' => site_url($listRoute),
    'formAria' => 'Search all subsidy types',
    'searchPlaceholder' => 'Search all subsidy types...',
    'keyword' => $keyword,
    'clearUrl' => $aidTypeClearUrl(),
    'pillsId' => 'aidTypeFilterPills',
    'hiddenHtml' => ($tabParam !== '' ? '<input type="hidden" name="tab" value="' . esc($tabParam, 'attr') . '">' : '')
        . ($perPage !== 25 ? '<input type="hidden" name="per_page" value="' . esc((string) $perPage, 'attr') . '">' : ''),
    'actionsHtml' => '<button class="' . btn('add') . ' flex-fill" type="button" data-bs-toggle="modal" data-bs-target="#addAidTypeModal">Add Subsidy Type</button>',
    'filterGroups' => [[
        'name' => 'status',
        'label' => 'Status',
        'options' => [
            ['value' => 'active', 'label' => "Active ({$activeAidTypeCount})", 'checked' => $status === 'active', 'default' => true],
            ['value' => 'archived', 'label' => "Archived ({$archivedAidTypeCount})", 'pill' => 'Archived', 'checked' => $status === 'archived'],
            ['value' => 'all', 'label' => "All ({$allAidTypeCount})", 'checked' => $status === 'all'],
        ],
    ]],
]) ?>
<?php
$aidTypeFooter = ($totalRows ?? 0) > 0 ? view('components/table_footer', [
    'fromRecord' => $fromRecord,
    'toRecord' => $toRecord,
    'totalRows' => $totalRows,
    'page' => $page,
    'totalPages' => $totalPages,
    'prevUrl' => $aidTypePageUrl(max(1, $page - 1)),
    'nextUrl' => $aidTypePageUrl(min($totalPages, $page + 1)),
]) : null;
?>
<?= view('components/card', [
    'icon' => 'box-seam',
    'title' => 'Subsidy Types',
    'cardClass' => 'sector-management',
    'bodyView' => 'Admin/aidtypes-body',
    'bodyData' => get_defined_vars(),
    'footer' => $aidTypeFooter,
]) ?>

<?= view('Admin/aidtype-create-modal') ?>
