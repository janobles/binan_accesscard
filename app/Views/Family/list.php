<?php
helper('family_list_view');

$families = $families ?? [];
$keyword = $keyword ?? '';
$routeBase = $routeBase ?? 'admin/manage-family';
$listRoute = (string) ($listRoute ?? ($routeBase . '/list'));
$status = (string) ($status ?? 'active') === 'archived' ? 'archived' : 'active';
$canRestoreArchived = (bool) ($canRestoreArchived ?? false);
$canArchive = (bool) ($canArchive ?? false);
$page = max(1, (int) ($page ?? 1));
$perPage = max(1, (int) ($perPage ?? 50));
$totalFamilies = max(0, (int) ($totalFamilies ?? count($families)));
$totalPages = max(1, (int) ($totalPages ?? 1));
$fromRecord = $totalFamilies === 0 ? 0 : (($page - 1) * $perPage) + 1;
$toRecord = min($totalFamilies, $page * $perPage);
// Filter controls + deep ("search the whole database") results are supplied by
// Admin DashboardPageBuilder and Employee WorkspaceModel supply filter controls and results.
$sectorOptions = $sectorOptions ?? [];
$filters = $filters ?? [];
$filterSectorId = (string) ($filters['sectorID'] ?? '');
$filterDate = (string) ($filters['date'] ?? '');
$deepKeyword = (string) ($deepKeyword ?? '');
$deepActive = (bool) ($deepActive ?? ($deepKeyword !== ''));
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
    data-current-status="<?= esc($status, 'attr') ?>"
    data-family-list-full-base="<?= esc(site_url($listRoute), 'attr') ?>">
    <div class="records-search-panel">
        <form class="records-search-row records-quick-search" method="get" action="<?= esc(site_url($listRoute), 'attr') ?>" aria-label="Manual records search" data-records-search="quick">
            <input
                class="form-control"
                type="search"
                name="keyword"
                value="<?= esc((string) $keyword, 'attr') ?>"
  
                aria-label="Keyword"
                autocomplete="off"
            >
            <select class="form-select records-status-select" name="status" aria-label="Record status">
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>Archived</option>
            </select>
            <button class="btn btn-success records-search-action" type="submit" data-search-mode="local">
                <i class="bi bi-search" aria-hidden="true"></i>
                <span>Search</span>
            </button>
            <button class="btn btn-outline-secondary records-search-action" type="button" data-records-clear>
                <i class="bi bi-x-lg" aria-hidden="true"></i>
                <span>Clear</span>
            </button>
            <button type="button" class="btn btn-primary records-search-action js-open-family-modal" data-modal-url="<?= site_url($routeBase . '?partial=1') ?>" data-modal-title="Add Record">
                <i class="bi bi-plus-lg" aria-hidden="true"></i>
                <span>Add</span>
            </button>
        </form>

        <form class="records-search-row records-database-search" method="get" action="<?= esc(site_url($listRoute), 'attr') ?>" aria-label="Database records search" data-records-search="database">
            <input type="hidden" name="search_scope" value="all">
            <?php if ($status === 'archived'): ?>
                <input type="hidden" name="status" value="archived">
            <?php endif; ?>
            <input
                class="form-control"
                type="search"
                name="deep_q"
                value="<?= esc($deepKeyword, 'attr') ?>"
                placeholder="Search the full database"
                aria-label="Search the full database"
                autocomplete="off"
            >
            <button class="btn btn-outline-success records-search-action" type="submit" data-search-mode="database">
                <i class="bi bi-database-search" aria-hidden="true"></i>
                <span>Search</span>
            </button>
        </form>
    </div>

    <?php if ($deepActive): ?>
        <div class="panel mb-3 border">
            <div class="section-title mt-0">
                <span><?= $deepKeyword === '' ? 'Database results' : 'Database results for "' . esc($deepKeyword) . '"' ?></span>
                <a
                    class="btn btn-outline-secondary btn-sm js-exit-deep-search"
                    href="<?= esc(family_list_url($listRoute, '', $filterSectorId, $filterDate, $status), 'attr') ?>">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>Remove filters
                </a>
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
                        // Active records can be archived; archived records can be restored.
                        $deepAction = $status === 'archived' ? 'restore' : 'archive';
                        $deepActionLabel = $status === 'archived' ? 'Restore' : 'Archive';
                        $deepActionPast = $status === 'archived' ? 'restored' : 'archived';
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
                                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" data-bs-strategy="fixed" aria-expanded="false" aria-label="Record actions">
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
                                            <?php if ($canArchive): ?>
                                                <form class="js-family-record-action-form" method="post" action="<?= site_url($routeBase . '/' . $deepAction . '/' . $resultHeadId) ?>" data-confirm-message="<?= esc($deepConfirm, 'attr') ?>" data-action-label="<?= esc($deepActionLabel, 'attr') ?>" data-action-past="<?= esc($deepActionPast, 'attr') ?>" data-family-name="<?= esc($deepFamilyName, 'attr') ?>">
                                                    <?= csrf_field() ?>
                                                    <button type="submit" class="dropdown-item <?= $status === 'archived' ? 'text-success' : 'text-danger' ?>">
                                                        <i class="bi <?= $status === 'archived' ? 'bi-arrow-counterclockwise' : 'bi-archive' ?>" aria-hidden="true"></i><?= esc($deepActionLabel) ?>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
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
                <th>Address</th>
                <th>Birthday</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($families as $family): ?>
                <?php
                $headId = (int) ($family['memberID'] ?? 0);
                $recordAction = $status === 'archived' ? 'restore' : 'archive';
                $recordActionLabel = $status === 'archived' ? 'Restore' : 'Archive';
                $recordActionPast = $status === 'archived' ? 'restored' : 'archived';
                $confirmMessage = $status === 'archived'
                    ? 'Restore this record to the active list?'
                    : $recordActionLabel . ' this record? This keeps the record in the database, marks it as ' . $recordActionPast . ', and hides it from active lists.';
                ?>
                <?php $recordFullName = trim((string) ($family['firstname'] ?? '') . ' ' . (string) ($family['middlename'] ?? '') . ' ' . (string) ($family['lastname'] ?? '')); ?>
                <tr data-record-row data-sector-ids="<?= esc((string) ($family['sectorID'] ?? '[]'), 'attr') ?>" data-record-fullname="<?= esc(strtolower($recordFullName), 'attr') ?>">
                    <td data-record-name>
                        <span class="entity-title"><?= esc((string) (($family['firstname'] ?? '') . ' ' . ($family['lastname'] ?? ''))) ?></span>
                    </td>
                    <td data-record-sector><?= esc((string) ($family['sector_name'] ?? '')) ?></td>
                    <td><?= esc((string) ($family['address'] ?? '')) ?></td>
                    <td><?= esc((string) ($family['birthday'] ?? '')) ?></td>
                    <td class="text-end">
                        <div class="dropdown actions-menu">
                            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" data-bs-strategy="fixed" aria-expanded="false" aria-label="Record actions">
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
                                        <i class="bi bi-pencil-square" aria-hidden="true"></i>Update
                                    </button>
                                <?php endif; ?>
                                <?php if ($canArchive): ?>
                                    <form class="js-family-record-action-form" method="post" action="<?= site_url($routeBase . '/' . $recordAction . '/' . $headId) ?>" data-confirm-message="<?= esc($confirmMessage, 'attr') ?>" data-action-label="<?= esc($recordActionLabel, 'attr') ?>" data-action-past="<?= esc($recordActionPast, 'attr') ?>" data-family-name="<?= esc((string) (($family['firstname'] ?? '') . ' ' . ($family['lastname'] ?? '')), 'attr') ?>">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="dropdown-item <?= $status === 'archived' ? 'text-success' : 'text-danger' ?>">
                                            <i class="bi <?= $status === 'archived' ? 'bi-arrow-counterclockwise' : 'bi-archive' ?>" aria-hidden="true"></i><?= esc($recordActionLabel) ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
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
