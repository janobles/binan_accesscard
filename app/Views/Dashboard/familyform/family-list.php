<?php
helper('family_list_view');

$families = $families ?? [];
$keyword = $keyword ?? '';
$routeBase = $routeBase ?? 'admin/manage-family';
$listRoute = (string) ($listRoute ?? ($routeBase . '/list'));
$status = (string) ($status ?? 'active') === 'archived' ? 'archived' : 'active';
$canRestoreArchived = (bool) ($canRestoreArchived ?? false);
$page = max(1, (int) ($page ?? 1));
$perPage = max(1, (int) ($perPage ?? 50));
$totalFamilies = max(0, (int) ($totalFamilies ?? count($families)));
$totalPages = max(1, (int) ($totalPages ?? 1));
$fromRecord = $totalFamilies === 0 ? 0 : (($page - 1) * $perPage) + 1;
$toRecord = min($totalFamilies, $page * $perPage);
$requestPath = trim((string) service('request')->getUri()->getPath(), '/');
$isEmployeeList = (string) session()->get('role') === 'Employee'
    || str_starts_with((string) $routeBase, 'employee/')
    || str_starts_with($requestPath, 'employee/')
    || str_contains('/' . $requestPath, '/employee/');
// Filter controls + deep ("search the whole database") results are supplied by
// Admin DashboardPageBuilder and Employee WorkspaceModel supply filter controls and results.
$sectorOptions = $sectorOptions ?? [];
$filters = $filters ?? [];
$filterSectorId = (string) ($filters['sectorID'] ?? '');
$filterDate = (string) ($filters['date'] ?? '');
$deepKeyword = (string) ($deepKeyword ?? '');
$deepResults = $deepResults ?? [];
$deepPage = max(1, (int) ($deepPage ?? 1));
$deepTotal = max(0, (int) ($deepTotal ?? 0));
$deepTotalPages = max(1, (int) ($deepTotalPages ?? 1));
$deepFromRecord = (int) ($deepFromRecord ?? 0);
$deepToRecord = (int) ($deepToRecord ?? 0);
?>

<div
    class="panel mb-3"
    data-family-list-panel
    data-family-list-full-base="<?= esc(site_url($listRoute), 'attr') ?>">
    <div class="section-title mt-0">
        <span><?= $status === 'archived' ? 'Archived Records' : 'Manage Records' ?></span>
        <?php if ($status !== 'archived'): ?>
            <button type="button" class="btn btn-primary btn-sm js-open-family-modal" data-modal-url="<?= site_url($routeBase . '?partial=1') ?>" data-modal-title="Add Record"><i class="bi bi-plus-lg" aria-hidden="true"></i>Add Record</button>
        <?php endif; ?>
    </div>
    <?php if (! $isEmployeeList && $canRestoreArchived): ?>
        <div class="toolbar-row mb-3">
            <a
                class="btn btn-sm <?= $status === 'active' ? 'btn-primary' : 'btn-outline-secondary' ?>"
                href="<?= esc(family_list_url($listRoute, (string) $keyword, $filterSectorId, $filterDate, 'active'), 'attr') ?>">
                <i class="bi bi-check2-circle" aria-hidden="true"></i>Active
            </a>
            <a
                class="btn btn-sm <?= $status === 'archived' ? 'btn-primary' : 'btn-outline-secondary' ?>"
                href="<?= esc(family_list_url($listRoute, (string) $keyword, $filterSectorId, $filterDate, 'archived'), 'attr') ?>">
                <i class="bi bi-archive" aria-hidden="true"></i>Archived
            </a>
        </div>
    <?php endif; ?>

    <div class="toolbar-row mb-3">
        <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#recordSearchModal"><i class="bi bi-search" aria-hidden="true"></i>Search</button>
        <?php if (trim((string) $keyword) !== '' || $filterSectorId !== '' || $filterDate !== '' || $deepKeyword !== ''): ?>
            <a class="btn btn-outline-secondary" href="<?= esc(site_url($listRoute . ($status === 'archived' ? '?status=archived' : '')), 'attr') ?>"><i class="bi bi-x-lg" aria-hidden="true"></i>Clear</a>
        <?php endif; ?>
    </div>

    <div class="modal fade" id="recordSearchModal" tabindex="-1" aria-labelledby="recordSearchModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="recordSearchModalLabel">Search Records</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <ul class="nav nav-tabs mb-3" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="recordNormalSearchTab" data-bs-toggle="tab" data-bs-target="#recordNormalSearchPane" type="button" role="tab" aria-controls="recordNormalSearchPane" aria-selected="true">Normal Search</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="recordAdvancedSearchTab" data-bs-toggle="tab" data-bs-target="#recordAdvancedSearchPane" type="button" role="tab" aria-controls="recordAdvancedSearchPane" aria-selected="false">Advanced Search</button>
                        </li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="recordNormalSearchPane" role="tabpanel" aria-labelledby="recordNormalSearchTab" tabindex="0">
                            <form method="get" action="<?= site_url($listRoute) ?>">
                                <?php if ($status === 'archived'): ?>
                                    <input type="hidden" name="status" value="archived">
                                <?php endif; ?>
                                <div class="mb-3">
                                    <label class="form-label" for="recordSearchKeyword">Search records</label>
                                    <input class="form-control" id="recordSearchKeyword" type="search" name="q" value="<?= esc((string) $keyword, 'attr') ?>" placeholder="Search record heads by name, contact, address, or sector">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="recordSearchSector">Filter records by sector</label>
                                    <select class="form-select" id="recordSearchSector" name="sectorID">
                                        <option value="">All sectors</option>
                                        <?php foreach ($sectorOptions as $sector): ?>
                                            <?php $optionId = (string) ($sector['sectorID'] ?? ''); ?>
                                            <option value="<?= esc($optionId) ?>" <?= $filterSectorId === $optionId ? 'selected' : '' ?>><?= esc((string) ($sector['name'] ?? '')) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="recordSearchDate">Filter records by date</label>
                                    <input class="form-control" id="recordSearchDate" type="date" name="date" value="<?= esc($filterDate, 'attr') ?>">
                                </div>
                                <div class="d-flex justify-content-end gap-2">
                                    <a class="btn btn-outline-secondary" href="<?= esc(site_url($listRoute . ($status === 'archived' ? '?status=archived' : '')), 'attr') ?>">Clear</a>
                                    <button class="btn btn-primary" type="submit"><i class="bi bi-search" aria-hidden="true"></i>Search Records</button>
                                </div>
                            </form>
                        </div>
                        <div class="tab-pane fade" id="recordAdvancedSearchPane" role="tabpanel" aria-labelledby="recordAdvancedSearchTab" tabindex="0">
                            <form method="get" action="<?= site_url($listRoute) ?>">
                                <?php if ($status === 'archived'): ?>
                                    <input type="hidden" name="status" value="archived">
                                <?php endif; ?>
                                <div class="mb-3">
                                    <label class="form-label" for="recordAdvancedSearchKeyword">Search the entire database</label>
                                    <input class="form-control" id="recordAdvancedSearchKeyword" type="search" name="deep_q" value="<?= esc($deepKeyword) ?>" placeholder="Any member, sector, or service/program">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="recordAdvancedSearchSector">Filter entire database by sector</label>
                                    <select class="form-select" id="recordAdvancedSearchSector" name="sectorID">
                                        <option value="">All sectors in database</option>
                                        <?php foreach ($sectorOptions as $sector): ?>
                                            <?php $optionId = (string) ($sector['sectorID'] ?? ''); ?>
                                            <option value="<?= esc($optionId) ?>" <?= $filterSectorId === $optionId ? 'selected' : '' ?>><?= esc((string) ($sector['name'] ?? '')) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="recordAdvancedSearchDate">Filter entire database by date</label>
                                    <input class="form-control" id="recordAdvancedSearchDate" type="date" name="date" value="<?= esc($filterDate, 'attr') ?>">
                                </div>
                                <div class="d-flex justify-content-end gap-2 mt-3">
                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button class="btn btn-primary" type="submit"><i class="bi bi-search" aria-hidden="true"></i>Search All</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php?>
    <?php if ($deepKeyword !== ''): ?>
        <div class="panel mb-3 border">
            <div class="section-title mt-0">
                <span>Database results for "<?= esc($deepKeyword) ?>"</span>
            </div>
            <div class="table-meta">
                <span><?= esc((string) $deepFromRecord) ?>-<?= esc((string) $deepToRecord) ?> of <?= esc((string) $deepTotal) ?> matches</span>
                <span>Page <?= esc((string) $deepPage) ?> of <?= esc((string) $deepTotalPages) ?></span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                    <tr>
                        <th>Name</th>
                        <th>Relationship</th>
                        <th>Belongs to</th>
                        <th>Sector</th>
                        <th>Service</th>
                        <th>Date</th>
                        <th class="text-end">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($deepResults as $result): ?>
                        <?php
                        // A deep-search row is any matched MEMBER, but view/edit/archive all
                        // operate on the family HEAD (resultHeadId), same as the main list below.
                        $resultHeadId = (int) ($result['headID'] ?? 0);
                        $headName = trim((string) ($result['head_firstname'] ?? '') . ' ' . (string) ($result['head_lastname'] ?? ''));
                        $memberName = trim((string) ($result['firstname'] ?? '') . ' ' . (string) ($result['lastname'] ?? ''));
                        $deepFamilyName = $headName !== '' ? $headName : $memberName;
                        // Mirror the main list's per-status action: admins archive, employees
                        // delete, and the archived view restores.
                        $deepAction = $status === 'archived' ? 'restore' : ($isEmployeeList ? 'delete' : 'archive');
                        $deepActionLabel = $status === 'archived' ? 'Restore' : ($isEmployeeList ? 'Delete' : 'Archive');
                        $deepActionPast = $status === 'archived' ? 'restored' : ($isEmployeeList ? 'deleted' : 'archived');
                        $deepConfirm = $status === 'archived'
                            ? 'Restore this record to the active list?'
                            : $deepActionLabel . ' this record? This keeps the record in the database, marks it as ' . $deepActionPast . ', and hides it from active lists.';
                        ?>
                        <tr>
                            <td><?= esc($memberName) ?></td>
                            <td><?= esc((string) ($result['relationship'] ?? '')) ?></td>
                            <td><?= esc($headName === '' ? '-' : $headName) ?></td>
                            <td><?= esc((string) ($result['sector_name'] ?? '')) ?></td>
                            <td><?= esc((string) ($result['service_name'] ?? '')) ?></td>
                            <td><?= esc(family_list_format_date($result['dt_created'] ?? '')) ?></td>
                            <td class="text-end">
                                <?php if ($resultHeadId > 0): ?>
                                    <div class="dropdown actions-menu">
                                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false" aria-label="Record actions">
                                            <i class="bi bi-three-dots" aria-hidden="true"></i>
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-end">
                                            <?php if ($status !== 'archived'): ?>
                                                <button
                                                    type="button"
                                                    class="dropdown-item js-open-family-view-modal"
                                                    data-modal-url="<?= site_url($routeBase . '/view/' . $resultHeadId . '?partial=1') ?>"
                                                    data-modal-title="View Record">
                                                    <i class="bi bi-eye" aria-hidden="true"></i>View
                                                </button>
                                                <button
                                                    type="button"
                                                    class="dropdown-item js-open-family-edit-modal"
                                                    data-modal-url="<?= site_url($routeBase . '/edit/' . $resultHeadId . '?partial=1') ?>"
                                                    data-modal-title="Edit Record">
                                                    <i class="bi bi-pencil-square" aria-hidden="true"></i>Edit
                                                </button>
                                            <?php endif; ?>
                                            <form class="js-family-record-action-form" method="post" action="<?= site_url($routeBase . '/' . $deepAction . '/' . $resultHeadId) ?>" data-confirm-message="<?= esc($deepConfirm, 'attr') ?>" data-action-label="<?= esc($deepActionLabel, 'attr') ?>" data-action-past="<?= esc($deepActionPast, 'attr') ?>" data-family-name="<?= esc($deepFamilyName, 'attr') ?>">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="dropdown-item <?= $status === 'archived' ? 'text-success' : 'text-danger' ?>">
                                                    <i class="bi <?= $status === 'archived' ? 'bi-arrow-counterclockwise' : 'bi-archive' ?>" aria-hidden="true"></i><?= esc($deepActionLabel) ?>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($deepResults === []): ?>
                        <tr><td colspan="7" class="text-center text-muted">No matching data found in the database.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($deepTotalPages > 1): ?>
                <div class="d-flex justify-content-end gap-2 mt-3">
                    <a class="btn btn-outline-secondary btn-sm <?= $deepPage <= 1 ? 'disabled' : '' ?>" href="<?= esc(family_list_deep_url($listRoute, $deepKeyword, $filterSectorId, $filterDate, $status, max(1, $deepPage - 1)), 'attr') ?>" aria-disabled="<?= $deepPage <= 1 ? 'true' : 'false' ?>">Previous</a>
                    <a class="btn btn-outline-secondary btn-sm <?= $deepPage >= $deepTotalPages ? 'disabled' : '' ?>" href="<?= esc(family_list_deep_url($listRoute, $deepKeyword, $filterSectorId, $filterDate, $status, min($deepTotalPages, $deepPage + 1)), 'attr') ?>" aria-disabled="<?= $deepPage >= $deepTotalPages ? 'true' : 'false' ?>">Next</a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="table-meta">
        <span><?= esc((string) $fromRecord) ?>-<?= esc((string) $toRecord) ?> of <?= esc((string) $totalFamilies) ?> records</span>
        <span>Page <?= esc((string) $page) ?> of <?= esc((string) $totalPages) ?></span>
    </div>

    <div class="table-responsive">
        <table class="table table-sm family-list-table align-middle">
            <thead>
            <tr>
                <th>Record Head</th>
                <th>Sector</th>
                <th>Date</th>
                <th>Time</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($families as $family): ?>
                <?php
                $headId = (int) ($family['memberID'] ?? 0);
                $dateValue = $status === 'archived' ? ($family['dt_deleted'] ?? '') : ($family['dt_created'] ?? '');
                $recordAction = $status === 'archived' ? 'restore' : ($isEmployeeList ? 'delete' : 'archive');
                $recordActionLabel = $status === 'archived' ? 'Restore' : ($isEmployeeList ? 'Delete' : 'Archive');
                $recordActionPast = $status === 'archived' ? 'restored' : ($isEmployeeList ? 'deleted' : 'archived');
                $confirmMessage = $status === 'archived'
                    ? 'Restore this record to the active list?'
                    : $recordActionLabel . ' this record? This keeps the record in the database, marks it as ' . $recordActionPast . ', and hides it from active lists.';
                ?>
                <tr data-record-row>
                    <td data-record-name>
                        <span class="entity-title"><?= esc((string) (($family['firstname'] ?? '') . ' ' . ($family['lastname'] ?? ''))) ?></span>
                    </td>
                    <td data-record-sector><?= esc((string) ($family['sector_name'] ?? '')) ?></td>
                    <td data-record-date><?= esc(family_list_format_date($dateValue)) ?></td>
                    <td><?= esc(family_list_format_time($dateValue)) ?></td>
                    <td class="text-end">
                        <div class="dropdown actions-menu">
                            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false" aria-label="Record actions">
                                <i class="bi bi-three-dots" aria-hidden="true"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end">
                                <?php if ($status !== 'archived'): ?>
                                    <button
                                        type="button"
                                        class="dropdown-item js-open-family-view-modal"
                                        data-modal-url="<?= site_url($routeBase . '/view/' . $headId . '?partial=1') ?>"
                                        data-modal-title="View Record">
                                        <i class="bi bi-eye" aria-hidden="true"></i>View
                                    </button>
                                    <button
                                        type="button"
                                        class="dropdown-item js-open-family-edit-modal"
                                        data-modal-url="<?= site_url($routeBase . '/edit/' . $headId . '?partial=1') ?>"
                                        data-modal-title="Edit Record">
                                        <i class="bi bi-pencil-square" aria-hidden="true"></i>Edit
                                    </button>
                                <?php endif; ?>
                                <form class="js-family-record-action-form" method="post" action="<?= site_url($routeBase . '/' . $recordAction . '/' . $headId) ?>" data-confirm-message="<?= esc($confirmMessage, 'attr') ?>" data-action-label="<?= esc($recordActionLabel, 'attr') ?>" data-action-past="<?= esc($recordActionPast, 'attr') ?>" data-family-name="<?= esc((string) (($family['firstname'] ?? '') . ' ' . ($family['lastname'] ?? '')), 'attr') ?>">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="dropdown-item <?= $status === 'archived' ? 'text-success' : 'text-danger' ?>">
                                        <i class="bi <?= $status === 'archived' ? 'bi-arrow-counterclockwise' : 'bi-archive' ?>" aria-hidden="true"></i><?= esc($recordActionLabel) ?>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($families === []): ?>
                <tr><td colspan="5" class="text-center text-muted"><?= $status === 'archived' ? 'No archived records found.' : 'No records found.' ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <?php $previousPageUrl = family_list_url($listRoute, (string) $keyword, $filterSectorId, $filterDate, $status, max(1, $page - 1)); ?>
        <?php $nextPageUrl = family_list_url($listRoute, (string) $keyword, $filterSectorId, $filterDate, $status, min($totalPages, $page + 1)); ?>
        <div class="d-flex justify-content-end gap-2 mt-3">
            <a
                class="btn btn-outline-secondary btn-sm<?= $page <= 1 ? ' disabled' : '' ?>"
                href="<?= esc($previousPageUrl, 'attr') ?>"
                aria-disabled="<?= $page <= 1 ? 'true' : 'false' ?>">
                Previous
            </a>
            <a
                class="btn btn-outline-secondary btn-sm<?= $page >= $totalPages ? ' disabled' : '' ?>"
                href="<?= esc($nextPageUrl, 'attr') ?>"
                aria-disabled="<?= $page >= $totalPages ? 'true' : 'false' ?>">
                Next
            </a>
        </div>
    <?php endif; ?>
</div>
