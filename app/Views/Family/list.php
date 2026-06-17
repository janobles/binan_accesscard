<?php
use App\Libraries\SectorIds;

helper('family_list_view');

$families = $families ?? [];
$keyword = (string) ($keyword ?? '');
$routeBase = (string) ($routeBase ?? 'admin/manage-family');
$listRoute = (string) ($listRoute ?? ($routeBase . '/list'));
$status = in_array((string) ($status ?? 'all'), ['active', 'archived'], true) ? (string) $status : 'all';
$canArchive = (bool) ($canArchive ?? false);
// Add + Update are hidden for read-only roles (Viewer). Defaults true so the
// admin and employee record lists are unaffected.
$canEdit = (bool) ($canEdit ?? true);
$page = max(1, (int) ($page ?? 1));
$perPage = max(1, (int) ($perPage ?? 50));
$perPageOptions = [10, 25, 50, 100];
$totalPages = max(1, (int) ($totalPages ?? 1));
$filters = $filters ?? [];
$filterSectorIds = array_values(array_filter(array_map('strval', (array) ($filters['sectorID'] ?? [])), static fn (string $value): bool => $value !== '' && $value !== '__all'));
$filterBarangays = array_values(array_filter(array_map('strval', (array) ($filters['barangay'] ?? [])), static fn (string $value): bool => $value !== '' && $value !== '__all'));
$filterDate = (string) ($filters['date'] ?? '');
$sectorOptions = $sectorOptions ?? [];
$barangayOptions = $barangayOptions ?? [];
$deepKeyword = (string) ($deepKeyword ?? '');
$deepActive = (bool) ($deepActive ?? ($deepKeyword !== ''));
$deepResults = $deepResults ?? [];
$deepPage = max(1, (int) ($deepPage ?? 1));
$deepTotalPages = max(1, (int) ($deepTotalPages ?? 1));

$activeRows = $deepActive ? $deepResults : $families;
$activePage = $deepActive ? $deepPage : $page;
$activeTotalPages = $deepActive ? $deepTotalPages : $totalPages;
$emptyMessage = $deepActive
    ? 'No matching data found in the database.'
    : ($status === 'archived' ? 'No archived records found.' : 'No records found.');
$displayUpper = static fn (mixed $value): string => mb_strtoupper((string) $value);
$displayTimestamp = static function (mixed $value): string {
    $timestamp = strtotime((string) $value);

    return $timestamp === false ? '-' : date('Y-m-d h:i A', $timestamp);
};
$sectorShortcodeMap = [];

foreach ((array) $sectorOptions as $sector) {
    $sectorId = (int) ($sector['sectorID'] ?? $sector['id'] ?? 0);
    $shortcode = trim((string) ($sector['shortcode'] ?? ''));

    if ($sectorId > 0 && $shortcode !== '') {
        $sectorShortcodeMap[$sectorId] = mb_strtoupper($shortcode);
    }
}

$sectorShortcodesFor = static function (mixed $value) use ($sectorShortcodeMap): string {
    $shortcodes = [];

    foreach (SectorIds::normalize($value) as $sectorId) {
        if (isset($sectorShortcodeMap[$sectorId])) {
            $shortcodes[] = $sectorShortcodeMap[$sectorId];
        }
    }

    return implode(', ', array_values(array_unique($shortcodes)));
};

$sectorOptionLabel = static function (array $sector): string {
    $shortcode = trim((string) ($sector['shortcode'] ?? ''));
    $name = trim((string) ($sector['sector_name'] ?? $sector['name'] ?? $sector['label'] ?? ''));

    if ($shortcode !== '' && $name !== '') {
        return mb_strtoupper($shortcode) . ' - ' . $name;
    }

    return $shortcode !== '' ? mb_strtoupper($shortcode) : $name;
};
$displayListName = static function (string $lastName, string $firstName, string $middleName, string $suffix = ''): string {
    $middleInitial = trim($middleName) !== '' ? mb_substr(trim($middleName), 0, 1) . '.' : '';
    // Suffix (Jr./Sr./III) belongs with the surname: "Dela Cruz Jr., Juan A.".
    $lastName = trim(trim($lastName) . (trim($suffix) !== '' ? ' ' . trim($suffix) : ''));
    $firstNameParts = trim(implode(' ', array_filter([
        trim($firstName),
        $middleInitial,
    ], static fn (string $part): bool => $part !== '')));

    if ($lastName !== '' && $firstNameParts !== '') {
        return $lastName . ', ' . $firstNameParts;
    }

    return $lastName !== '' ? $lastName : $firstNameParts;
};
?>

<div
    class="panel mb-3 records-scroll-panel"
    data-family-list-panel
    data-current-status="<?= esc($status, 'attr') ?>"
    data-family-list-full-base="<?= esc(site_url($listRoute), 'attr') ?>">
    <div class="records-search-panel">
        <form class="records-search-row records-quick-search" method="get" action="<?= esc(site_url($listRoute), 'attr') ?>" aria-label="Database records search" data-records-search="database">
            <input
                class="form-control"
                type="search"
                name="q"
                value="<?= esc($deepActive ? $deepKeyword : $keyword, 'attr') ?>"
                aria-label="Database keyword search"
                autocomplete="off"
                data-records-database-keyword
            >
            <div class="dropdown records-multiselect" data-records-filter="sector">
                <button class="form-select records-multiselect-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                    <span data-records-filter-label>-Select sector-</span>
                </button>
                <div class="dropdown-menu records-multiselect-menu">
                    <?php foreach ($sectorOptions as $sector): ?>
                        <?php
                        $sectorId = (string) ($sector['sectorID'] ?? $sector['id'] ?? '');
                        $sectorName = $sectorOptionLabel((array) $sector);
                        ?>
                        <?php if ($sectorId !== '' && $sectorName !== ''): ?>
                            <label class="dropdown-item records-check-option">
                                <input class="form-check-input" type="checkbox" name="sectorID[]" value="<?= esc($sectorId, 'attr') ?>" <?= in_array($sectorId, $filterSectorIds, true) ? 'checked' : '' ?>>
                                <span><?= esc($sectorName) ?></span>
                            </label>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="dropdown records-multiselect" data-records-filter="barangay">
                <button class="form-select records-multiselect-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                    <span data-records-filter-label>-Select barangay-</span>
                </button>
                <div class="dropdown-menu records-multiselect-menu">
                    <?php foreach ($barangayOptions as $barangay): ?>
                        <?php $barangayName = (string) $barangay; ?>
                        <?php if ($barangayName !== ''): ?>
                            <label class="dropdown-item records-check-option">
                                <input class="form-check-input" type="checkbox" name="barangay[]" value="<?= esc($barangayName, 'attr') ?>" <?= in_array($barangayName, $filterBarangays, true) ? 'checked' : '' ?>>
                                <span><?= esc($barangayName) ?></span>
                            </label>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <select class="form-select records-status-select" name="status" aria-label="Record status">
                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All</option>
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>Archived</option>
            </select>
            <input type="hidden" name="per_page" value="<?= esc((string) $perPage, 'attr') ?>">
            <button class="btn btn-outline-secondary records-search-action" type="button" data-records-clear>
                <i class="bi bi-x-lg" aria-hidden="true"></i>
                <span>Clear</span>
            </button>
            <button class="btn btn-outline-success records-search-action" type="submit" name="search_scope" value="all" data-search-mode="all">
                <i class="bi bi-search" aria-hidden="true"></i>
                <span>Search</span>
            </button>
            <?php if ($canEdit): ?>
            <button type="button" class="btn btn-primary records-search-action js-open-family-modal" data-modal-url="<?= site_url($routeBase . '?partial=1') ?>" data-modal-title="Add Record">
                <i class="bi bi-plus-lg" aria-hidden="true"></i>
                <span>Add</span>
            </button>
            <?php endif; ?>
        </form>
    </div>

    <div class="table-meta">
        <div class="records-table-controls">
            <form class="records-page-size-form" method="get" action="<?= esc(site_url($listRoute), 'attr') ?>">
                <?php if ($keyword !== ''): ?>
                    <input type="hidden" name="q" value="<?= esc($keyword, 'attr') ?>">
                <?php endif; ?>
                <?php if ($status !== 'all'): ?>
                    <input type="hidden" name="status" value="<?= esc($status, 'attr') ?>">
                <?php endif; ?>
                <?php foreach ($filterSectorIds as $selectedSectorId): ?>
                    <input type="hidden" name="sectorID[]" value="<?= esc($selectedSectorId, 'attr') ?>">
                <?php endforeach; ?>
                <?php foreach ($filterBarangays as $selectedBarangay): ?>
                    <input type="hidden" name="barangay[]" value="<?= esc($selectedBarangay, 'attr') ?>">
                <?php endforeach; ?>
                <?php if ($filterDate !== ''): ?>
                    <input type="hidden" name="date" value="<?= esc($filterDate, 'attr') ?>">
                <?php endif; ?>
                <?php if ($deepActive): ?>
                    <input type="hidden" name="search_scope" value="all">
                    <input type="hidden" name="deep_q" value="<?= esc($deepKeyword, 'attr') ?>">
                <?php endif; ?>
                <label for="recordsPerPage">Show</label>
                <select class="form-select form-select-sm" id="recordsPerPage" name="per_page" onchange="this.form.submit()">
                    <?php foreach ($perPageOptions as $option): ?>
                        <option value="<?= esc((string) $option, 'attr') ?>" <?= $perPage === $option ? 'selected' : '' ?>><?= esc((string) $option) ?></option>
                    <?php endforeach; ?>
                </select>
                <span>entries</span>
            </form>
            <form class="records-table-search-form" method="get" action="<?= esc(site_url($listRoute), 'attr') ?>" data-records-search="table">
                <?php if ($status !== 'all'): ?>
                    <input type="hidden" name="status" value="<?= esc($status, 'attr') ?>">
                <?php endif; ?>
                <?php foreach ($filterSectorIds as $selectedSectorId): ?>
                    <input type="hidden" name="sectorID[]" value="<?= esc($selectedSectorId, 'attr') ?>">
                <?php endforeach; ?>
                <?php foreach ($filterBarangays as $selectedBarangay): ?>
                    <input type="hidden" name="barangay[]" value="<?= esc($selectedBarangay, 'attr') ?>">
                <?php endforeach; ?>
                <?php if ($perPage !== 50): ?>
                    <input type="hidden" name="per_page" value="<?= esc((string) $perPage, 'attr') ?>">
                <?php endif; ?>
                <label for="recordsKeyword">Search:</label>
                <input
                    class="form-control form-control-sm"
                    type="search"
                    id="recordsKeyword"
                    name="q"
                    value=""
                    placeholder="Type to search..."
                    autocomplete="off"
                    data-records-table-keyword
                >
            </form>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-sm manage-record-table align-middle">
            <thead>
            <tr>
                <th><?= esc($displayUpper('Date')) ?></th>
                <th><?= esc($displayUpper($deepActive ? 'Name (Head)' : 'Head of the Family')) ?></th>
                <th><?= esc($displayUpper('Sector')) ?></th>
                <th><?= esc($displayUpper('Address')) ?></th>
                <th><?= esc($displayUpper('Birthday')) ?></th>
                <th class="text-end"><?= esc($displayUpper('Actions')) ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($activeRows as $row): ?>
                <?php
                $headId = (int) ($deepActive ? ($row['headID'] ?? $row['memberID'] ?? 0) : ($row['memberID'] ?? 0));
                $firstName = (string) ($row['firstname'] ?? '');
                $middleName = (string) ($row['middlename'] ?? '');
                $lastName = (string) ($row['lastname'] ?? '');
                $suffix = (string) ($row['suffix'] ?? '');
                $fullName = trim($firstName . ' ' . $middleName . ' ' . $lastName . ' ' . $suffix);
                $displayName = $displayListName($lastName, $firstName, $middleName, $suffix);
                $rowArchived = trim((string) ($row['dt_deleted'] ?? '')) !== '';
                $recordAction = $rowArchived ? 'restore' : 'archive';
                $recordActionLabel = $rowArchived ? 'Restore' : 'Archive';
                $recordActionPast = $rowArchived ? 'restored' : 'archived';
                $confirmMessage = $rowArchived
                    ? 'Restore this record to the active list?'
                    : $recordActionLabel . ' this record? This keeps the record in the database, marks it as ' . $recordActionPast . ', and hides it from active lists.';
                ?>
                <tr data-record-row data-sector-ids="<?= esc((string) ($row['sectorID'] ?? '[]'), 'attr') ?>" data-record-fullname="<?= esc(strtolower($fullName), 'attr') ?>">
                    <td><?= esc($displayTimestamp($row['dt_created'] ?? '')) ?></td>
                    <td data-record-name>
                        <span class="entity-title"><?= esc($displayUpper($displayName)) ?></span>
                        <?php if ($deepActive && trim((string) ($row['relationship'] ?? '')) !== ''): ?>
                            <small class="text-muted d-block"><?= esc($displayUpper($row['relationship'])) ?></small>
                        <?php endif; ?>
                    </td>
                    <td data-record-sector><?= esc($sectorShortcodesFor($row['sectorID'] ?? '')) ?></td>
                    <td><?= esc($displayUpper($row['address'] ?? '')) ?></td>
                    <td><?= esc(family_list_format_date($row['birthday'] ?? '')) ?></td>
                    <td class="text-end">
                        <?php
                        // An active row always has at least "View"; an archived row only
                        // has actions when archive/restore is allowed. Hiding the menu
                        // entirely avoids an empty dropdown for read-only roles (Viewer).
                        $rowHasActions = ! $rowArchived || $canArchive;
                        ?>
                        <?php if ($headId > 0 && $rowHasActions): ?>
                            <div class="dropdown actions-menu">
                                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" data-bs-strategy="fixed" aria-expanded="false" aria-label="Record actions">
                                    <i class="bi bi-three-dots" aria-hidden="true"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <?php if (! $rowArchived): ?>
                                        <button
                                            type="button"
                                            class="dropdown-item js-open-family-view-modal"
                                            data-modal-url="<?= site_url($routeBase . '/view/' . $headId . '?partial=1') ?>"
                                            data-modal-title="View Record">
                                            <i class="bi bi-eye" aria-hidden="true"></i><?= esc($displayUpper('View')) ?>
                                        </button>
                                        <?php if ($canEdit): ?>
                                        <button
                                            type="button"
                                            class="dropdown-item js-open-family-edit-modal"
                                            data-modal-url="<?= site_url($routeBase . '/edit/' . $headId . '?partial=1') ?>"
                                            data-modal-title="Edit Record">
                                            <i class="bi bi-pencil-square" aria-hidden="true"></i><?= esc($displayUpper('Update')) ?>
                                        </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if ($canArchive): ?>
                                        <form class="js-family-record-action-form" method="post" action="<?= site_url($routeBase . '/' . $recordAction . '/' . $headId) ?>" data-confirm-message="<?= esc($confirmMessage, 'attr') ?>" data-action-label="<?= esc($recordActionLabel, 'attr') ?>" data-action-past="<?= esc($recordActionPast, 'attr') ?>" data-family-name="<?= esc($displayName, 'attr') ?>">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="dropdown-item <?= $rowArchived ? 'text-success' : 'text-danger' ?>">
                                                <i class="bi <?= $rowArchived ? 'bi-arrow-counterclockwise' : 'bi-archive' ?>" aria-hidden="true"></i><?= esc($displayUpper($recordActionLabel)) ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($activeRows === []): ?>
                <tr><td colspan="6" class="text-center text-muted"><?= esc($displayUpper($emptyMessage)) ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($activeTotalPages > 1): ?>
        <?php if ($deepActive): ?>
            <?php $previousPageUrl = family_list_deep_url($listRoute, $deepKeyword, $filterSectorIds, $filterDate, $status, max(1, $deepPage - 1), $perPage, $filterBarangays); ?>
            <?php $nextPageUrl = family_list_deep_url($listRoute, $deepKeyword, $filterSectorIds, $filterDate, $status, min($deepTotalPages, $deepPage + 1), $perPage, $filterBarangays); ?>
        <?php else: ?>
            <?php $previousPageUrl = family_list_url($listRoute, $keyword, $filterSectorIds, $filterDate, $status, max(1, $page - 1), $perPage, $filterBarangays); ?>
            <?php $nextPageUrl = family_list_url($listRoute, $keyword, $filterSectorIds, $filterDate, $status, min($totalPages, $page + 1), $perPage, $filterBarangays); ?>
        <?php endif; ?>
        <div class="d-flex justify-content-end gap-2 mt-3">
            <a
                class="btn btn-outline-secondary btn-sm<?= $activePage <= 1 ? ' disabled' : '' ?>"
                href="<?= esc($previousPageUrl, 'attr') ?>"
                aria-disabled="<?= $activePage <= 1 ? 'true' : 'false' ?>">
                Previous
            </a>
            <a
                class="btn btn-outline-secondary btn-sm<?= $activePage >= $activeTotalPages ? ' disabled' : '' ?>"
                href="<?= esc($nextPageUrl, 'attr') ?>"
                aria-disabled="<?= $activePage >= $activeTotalPages ? 'true' : 'false' ?>">
                Next
            </a>
        </div>
    <?php endif; ?>
</div>
