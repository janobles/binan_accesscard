<?php
$routeBase = (string) ($routeBase ?? 'admin/manage-family');
$keyword = trim((string) ($keyword ?? ''));
$status = in_array((string) ($status ?? 'all'), ['all', 'active', 'archived'], true) ? (string) $status : 'all';
$filters = (array) ($filters ?? []);
$sectorOptions = (array) ($sectorOptions ?? []);
$barangayOptions = (array) ($barangayOptions ?? []);
// Add is hidden for read-only roles (Viewer). Defaults true so admin/employee
// record lists are unaffected. DataTable row actions are gated server-side in
// FamilyController::dataTableActions().
$canEdit = (bool) ($canEdit ?? true);
$selectedSectorIds = array_map('strval', (array) ($filters['sectorID'] ?? []));
$selectedBarangays = array_map('strval', (array) ($filters['barangay'] ?? []));
$sectorOptionLabel = static function (array $sector): string {
    $shortcode = trim((string) ($sector['shortcode'] ?? ''));
    $name = trim((string) ($sector['sector_name'] ?? $sector['name'] ?? $sector['label'] ?? ''));

    if ($shortcode !== '' && $name !== '') {
        return mb_strtoupper($shortcode) . ' - ' . $name;
    }

    return $shortcode !== '' ? mb_strtoupper($shortcode) : $name;
};
?>

<?php
$sectorOptionsGroup = [
    'name' => 'sectorID',
    'label' => 'Sector',
    'type' => 'checkbox',
    'scroll' => true,
    'options' => [],
];
foreach ($sectorOptions as $sector) {
    $sectorId = (string) ($sector['sectorID'] ?? $sector['id'] ?? '');
    $sectorName = $sectorOptionLabel((array) $sector);
    if ($sectorId !== '' && $sectorName !== '') {
        $sectorOptionsGroup['options'][] = [
            'value' => $sectorId,
            'label' => $sectorName,
            'pill' => $sectorName,
            'checked' => in_array($sectorId, $selectedSectorIds, true),
        ];
    }
}

$barangayOptionsGroup = [
    'name' => 'barangay',
    'label' => 'Barangay',
    'type' => 'checkbox',
    'scroll' => true,
    'options' => [],
];
foreach ($barangayOptions as $barangay) {
    $barangayName = trim((string) $barangay);
    if ($barangayName !== '') {
        $barangayOptionsGroup['options'][] = [
            'value' => $barangayName,
            'label' => $barangayName,
            'pill' => $barangayName,
            'checked' => in_array($barangayName, $selectedBarangays, true),
        ];
    }
}

$statusGroup = [
    'name' => 'status',
    'label' => 'Status',
    'type' => 'radio',
    'options' => [
        ['value' => 'all', 'label' => 'All', 'checked' => $status === 'all', 'default' => true],
        ['value' => 'active', 'label' => 'Active', 'pill' => 'Active', 'checked' => $status === 'active'],
        ['value' => 'archived', 'label' => 'Archived', 'pill' => 'Archived', 'checked' => $status === 'archived'],
    ],
];

$actionsHtml = '';
if ($canEdit) {
    $actionsHtml .= '<button class="' . btn('add') . ' flex-fill js-open-family-add-modal" type="button" data-family-add-record data-modal-url="' . esc(site_url($routeBase . '/create?partial=1'), 'attr') . '" data-modal-title="New Family Record">Add</button>';
    $actionsHtml .= '<button class="' . btn('import') . ' flex-fill js-open-family-import-modal" type="button" data-modal-url="' . esc(site_url($routeBase . '/import'), 'attr') . '" data-modal-title="Import from Excel" title="Bulk-import families from an Excel file">Import</button>';
}
?>

<?= view('components/toolbar', [
    'formId' => 'familyDataTableFilters',
    'disableGenericFilterJs' => true,
    'isClient' => true,
    'formAria' => 'Family records search and filters',
    'searchPlaceholder' => 'Search all family records...',
    'keyword' => $keyword,
    'searchAttrs' => 'data-records-database-keyword',
    'pillsId' => 'familyFilterPills',
    'narrow' => true,
    'actionsHtml' => $actionsHtml,
    'filterGroups' => [$sectorOptionsGroup, $barangayOptionsGroup, $statusGroup],
]) ?>

<?= view('components/card', [
    'icon' => 'table',
    'title' => 'Family Records',
    'cardClass' => 'overflow-hidden',
    'bodyClass' => 'd-flex flex-column overflow-hidden p-3',
    'bodyView' => 'Family/list-body',
    'bodyData' => [
        'routeBase' => $routeBase,
    ],
    'footer' => '<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 w-100"><div id="familyFooterLeft"></div><div id="familyFooterRight"></div></div>',
]) ?>
