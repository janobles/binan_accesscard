<?php
$families = $families ?? [];
$keyword = $keyword ?? '';
$routeBase = $routeBase ?? 'admin/manage-family';
$listRoute = (string) ($listRoute ?? ($routeBase . '/list'));
$useModalLinks = (bool) ($useModalLinks ?? true);
$status = (string) ($status ?? 'active') === 'archived' ? 'archived' : 'active';
$canRestoreArchived = (bool) ($canRestoreArchived ?? false);
$page = max(1, (int) ($page ?? 1));
$perPage = max(1, (int) ($perPage ?? 50));
$totalFamilies = max(0, (int) ($totalFamilies ?? count($families)));
$totalPages = max(1, (int) ($totalPages ?? 1));
$fromRecord = $totalFamilies === 0 ? 0 : (($page - 1) * $perPage) + 1;
$toRecord = min($totalFamilies, $page * $perPage);
$requestPath = trim((string) service('request')->getUri()->getPath(), '/');
$isEmployeeList = (string) session()->get('role') === 'User'
    || str_starts_with((string) $routeBase, 'employee/')
    || str_starts_with($requestPath, 'employee/')
    || str_contains('/' . $requestPath, '/employee/');
// Filter controls + deep ("search the whole database") results are supplied by
// App\Libraries\DashboardPageBuilder::buildMemberListData()/buildEmployeeRecordListData().
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
$formatDate = static function (mixed $value): string {
    $timestamp = strtotime((string) $value);

    return $timestamp === false ? '' : date('Y-m-d', $timestamp);
};
$formatTime = static function (mixed $value): string {
    $timestamp = strtotime((string) $value);

    return $timestamp === false ? '' : date('h:i A', $timestamp);
};
// Records-list pagination URL. Carries the keyword AND the filter (sector/date) so
// paging through filtered results keeps the filter applied.
$listUrl = static function (string $targetStatus, int $targetPage = 1) use ($listRoute, $keyword, $filterSectorId, $filterDate): string {
    $params = ['page' => $targetPage];

    if ($targetStatus === 'archived') {
        $params['status'] = 'archived';
    }

    if (trim((string) $keyword) !== '') {
        $params['q'] = (string) $keyword;
    }

    if (trim($filterSectorId) !== '') {
        $params['sectorID'] = $filterSectorId;
    }

    if (trim($filterDate) !== '') {
        $params['date'] = $filterDate;
    }

    return site_url($listRoute . '?' . http_build_query($params));
};
$partialUrl = static function (string $url): string {
    return $url . (str_contains($url, '?') ? '&' : '?') . 'partial=1';
};
// Deep-search pagination URL. Preserves the deep keyword and active/archived status.
$deepUrl = static function (int $targetPage) use ($listRoute, $deepKeyword, $status): string {
    $params = ['deep_q' => $deepKeyword, 'deep_page' => $targetPage];

    if ($status === 'archived') {
        $params['status'] = 'archived';
    }

    return site_url($listRoute . '?' . http_build_query($params));
};
?>

<div class="panel mb-3">
    <div class="section-title mt-0">
        <span><?= $status === 'archived' ? 'Archived Records' : 'Manage Records' ?></span>
        <?php if ($status !== 'archived'): ?>
            <button type="button" class="btn btn-primary btn-sm js-open-family-modal" data-modal-url="<?= site_url($routeBase . '?partial=1') ?>" data-modal-title="Add Record">Add Record</button>
        <?php endif; ?>
    </div>
    <?php if (! $isEmployeeList && $canRestoreArchived): ?>
        <div class="d-flex gap-2 mb-3">
            <a
                class="btn btn-sm <?= $status === 'active' ? 'btn-primary' : 'btn-outline-secondary' ?><?= $useModalLinks ? ' js-open-family-list' : '' ?>"
                href="<?= esc($listUrl('active'), 'attr') ?>"
                <?= $useModalLinks ? 'data-modal-url="' . esc($partialUrl($listUrl('active')), 'attr') . '" data-modal-title="Manage Records"' : '' ?>>
                Active
            </a>
            <a
                class="btn btn-sm <?= $status === 'archived' ? 'btn-primary' : 'btn-outline-secondary' ?><?= $useModalLinks ? ' js-open-family-list' : '' ?>"
                href="<?= esc($listUrl('archived'), 'attr') ?>"
                <?= $useModalLinks ? 'data-modal-url="' . esc($partialUrl($listUrl('archived')), 'attr') . '" data-modal-title="Archived Records"' : '' ?>>
                Archived
            </a>
        </div>
    <?php endif; ?>

    <?php /* FIRST (quick) search bar + Manage Records FILTER (sector + date + status).
             Submits q + sectorID + date (+ status) and is backed by
             App\Models\MemberModel::searchFamilies() via DashboardPageBuilder. */ ?>
    <form method="get" class="row g-2 mb-3 align-items-end" action="<?= site_url($listRoute) ?>">
        <?php if ($status === 'archived'): ?>
            <input type="hidden" name="status" value="archived">
        <?php endif; ?>
        <div class="col-md-5">
            <label class="form-label small mb-1">Search records (shown list)</label>
            <input class="form-control" type="search" name="q" value="<?= esc((string) $keyword) ?>" placeholder="Search by head name or sector">
        </div>
        <div class="col-md-4">
            <label class="form-label small mb-1">Filter by sector</label>
            <select class="form-select" name="sectorID">
                <option value="">All sectors</option>
                <?php foreach ($sectorOptions as $sector): ?>
                    <?php $optionId = (string) ($sector['sectorID'] ?? ''); ?>
                    <option value="<?= esc($optionId) ?>" <?= $filterSectorId === $optionId ? 'selected' : '' ?>><?= esc((string) ($sector['name'] ?? '')) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small mb-1">Filter by date</label>
            <input class="form-control" type="date" name="date" value="<?= esc($filterDate) ?>">
        </div>
        <div class="col-12 d-flex gap-2 mt-1">
            <button class="btn btn-outline-secondary" type="submit">Search / Filter</button>
            <?php if (trim((string) $keyword) !== '' || $filterSectorId !== '' || $filterDate !== ''): ?>
                <a class="btn btn-outline-secondary" href="<?= esc(site_url($listRoute . ($status === 'archived' ? '?status=archived' : '')), 'attr') ?>">Clear</a>
            <?php endif; ?>
        </div>
    </form>

    <?php /* SECOND ("search the whole database") bar. Unlike the first bar it finds ANY
             member including non-head family members, and also matches by sector name and
             service/program name. Submits deep_q and is backed by
             App\Models\SearchModel::allMembers(). Results show in the panel below. */ ?>
    <form method="get" class="row g-2 mb-3 align-items-end" action="<?= site_url($listRoute) ?>">
        <?php if ($status === 'archived'): ?>
            <input type="hidden" name="status" value="archived">
        <?php endif; ?>
        <div class="col-md-9">
            <label class="form-label small mb-1">Search the entire database (any member, sector, or service)</label>
            <input class="form-control" type="search" name="deep_q" value="<?= esc($deepKeyword) ?>" placeholder="e.g. a family member's name, a sector, or a service/program">
        </div>
        <div class="col-md-3 d-grid">
            <button class="btn btn-primary" type="submit">Search All</button>
        </div>
    </form>

    <?php /* Deep-search results panel. Renders only when the deep search box was used.
             Each row links to its family record via the existing view modal. */ ?>
    <?php if ($deepKeyword !== ''): ?>
        <div class="panel mb-3 border">
            <div class="section-title mt-0">
                <span>Database results for "<?= esc($deepKeyword) ?>"</span>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-2 text-muted small">
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
                        $resultHeadId = (int) ($result['headID'] ?? 0);
                        $headName = trim((string) ($result['head_firstname'] ?? '') . ' ' . (string) ($result['head_lastname'] ?? ''));
                        ?>
                        <tr>
                            <td><?= esc(trim((string) ($result['firstname'] ?? '') . ' ' . (string) ($result['lastname'] ?? ''))) ?></td>
                            <td><?= esc((string) ($result['relationship'] ?? '')) ?></td>
                            <td><?= esc($headName === '' ? '-' : $headName) ?></td>
                            <td><?= esc((string) ($result['sector_name'] ?? '')) ?></td>
                            <td><?= esc((string) ($result['service_name'] ?? '')) ?></td>
                            <td><?= esc($formatDate($result['dt_created'] ?? '')) ?></td>
                            <td class="text-end">
                                <?php if ($resultHeadId > 0): ?>
                                    <button
                                        type="button"
                                        class="btn btn-outline-primary btn-sm js-open-family-view-modal"
                                        data-modal-url="<?= site_url($routeBase . '/view/' . $resultHeadId . '?partial=1') ?>"
                                        data-modal-title="View Record">
                                        View family
                                    </button>
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
                    <a class="btn btn-outline-secondary btn-sm <?= $deepPage <= 1 ? 'disabled' : '' ?>" href="<?= esc($deepUrl(max(1, $deepPage - 1)), 'attr') ?>" aria-disabled="<?= $deepPage <= 1 ? 'true' : 'false' ?>">Previous</a>
                    <a class="btn btn-outline-secondary btn-sm <?= $deepPage >= $deepTotalPages ? 'disabled' : '' ?>" href="<?= esc($deepUrl(min($deepTotalPages, $deepPage + 1)), 'attr') ?>" aria-disabled="<?= $deepPage >= $deepTotalPages ? 'true' : 'false' ?>">Next</a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-2 text-muted small">
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
                <tr>
                    <td><?= esc((string) (($family['firstname'] ?? '') . ' ' . ($family['lastname'] ?? ''))) ?></td>
                    <td><?= esc((string) ($family['sector_name'] ?? '')) ?></td>
                    <td><?= esc($formatDate($dateValue)) ?></td>
                    <td><?= esc($formatTime($dateValue)) ?></td>
                    <td class="text-end">
                        <div class="family-list-actions">
                        <?php if ($status !== 'archived'): ?>
                        <button
                            type="button"
                            class="btn btn-outline-primary btn-sm js-open-family-view-modal"
                            data-modal-url="<?= site_url($routeBase . '/view/' . $headId . '?partial=1') ?>"
                            data-modal-title="View Record">
                            View
                        </button>
                        <button
                            type="button"
                            class="btn btn-primary btn-sm js-open-family-edit-modal"
                            data-modal-url="<?= site_url($routeBase . '/edit/' . $headId . '?partial=1') ?>"
                            data-modal-title="Edit Record">
                            Edit
                        </button>
                        <?php endif; ?>
                        <form class="d-inline js-family-record-action-form" method="post" action="<?= site_url($routeBase . '/' . $recordAction . '/' . $headId) ?>" data-confirm-message="<?= esc($confirmMessage, 'attr') ?>" data-action-label="<?= esc($recordActionLabel, 'attr') ?>" data-action-past="<?= esc($recordActionPast, 'attr') ?>" data-family-name="<?= esc((string) (($family['firstname'] ?? '') . ' ' . ($family['lastname'] ?? '')), 'attr') ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn <?= $status === 'archived' ? 'btn-outline-success' : 'btn-outline-danger' ?> btn-sm"><?= esc($recordActionLabel) ?></button>
                        </form>
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
        <?php
        $pageUrl = static fn (int $targetPage): string => $listUrl($status, $targetPage);
        ?>
        <div class="d-flex justify-content-end gap-2 mt-3">
            <a
                class="btn btn-outline-secondary btn-sm<?= $useModalLinks ? ' js-open-family-list' : '' ?> <?= $page <= 1 ? 'disabled' : '' ?>"
                href="<?= esc($pageUrl(max(1, $page - 1)), 'attr') ?>"
                <?= $useModalLinks ? 'data-modal-url="' . esc($partialUrl($pageUrl(max(1, $page - 1))), 'attr') . '" data-modal-title="Manage Records"' : '' ?>
                aria-disabled="<?= $page <= 1 ? 'true' : 'false' ?>">
                Previous
            </a>
            <a
                class="btn btn-outline-secondary btn-sm<?= $useModalLinks ? ' js-open-family-list' : '' ?> <?= $page >= $totalPages ? 'disabled' : '' ?>"
                href="<?= esc($pageUrl(min($totalPages, $page + 1)), 'attr') ?>"
                <?= $useModalLinks ? 'data-modal-url="' . esc($partialUrl($pageUrl(min($totalPages, $page + 1))), 'attr') . '" data-modal-title="Manage Records"' : '' ?>
                aria-disabled="<?= $page >= $totalPages ? 'true' : 'false' ?>">
                Next
            </a>
        </div>
    <?php endif; ?>
</div>

<script>
(function () {
    document.querySelectorAll('.js-family-record-action-form').forEach(function (form) {
        if (form.dataset.recordActionBound === '1') {
            return;
        }

        form.dataset.recordActionBound = '1';
        form.addEventListener('submit', function (event) {
            const familyName = (form.dataset.familyName || 'this record').trim();
            const actionLabel = (form.dataset.actionLabel || 'Archive').trim();
            const actionPast = (form.dataset.actionPast || 'archived').trim();
            const message = (form.dataset.confirmMessage || '').trim() || (actionLabel + ' ' + familyName + '? This keeps the record in the database, marks it as ' + actionPast + ', and hides it from active lists.');
            const confirmed = window.confirm(message);

            if (!confirmed) {
                event.preventDefault();
            }
        });
    });
})();
</script>
