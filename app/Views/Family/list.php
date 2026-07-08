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

<?= view('components/card', [
    'icon' => 'table',
    'title' => 'Family Records',
    'cardClass' => 'overflow-hidden',
    'bodyClass' => 'd-flex flex-column overflow-hidden p-3',
    'bodyView' => 'Family/list-body',
    'bodyData' => [
        'routeBase' => $routeBase,
        'keyword' => $keyword,
        'status' => $status,
        'sectorOptions' => $sectorOptions,
        'barangayOptions' => $barangayOptions,
        'selectedSectorIds' => $selectedSectorIds,
        'selectedBarangays' => $selectedBarangays,
        'sectorOptionLabel' => $sectorOptionLabel,
        'canEdit' => $canEdit,
    ],
]) ?>
