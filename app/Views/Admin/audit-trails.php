<?php
$recentAudits       = $recentAudits ?? [];
$searchTerm         = $searchTerm ?? '';
$searchFilters      = $searchFilters ?? [];
$auditActionOptions = $auditActionOptions ?? [];
$auditListData      = $auditListData ?? [];
$hasSearchFilters   = $searchTerm !== '' || array_filter($searchFilters, static fn ($value): bool => trim((string) $value) !== '') !== [];

// Pagination + page-size bundle (from DashboardPageBuilder::buildAuditListData).
$listRoute      = (string) ($auditListData['listRoute'] ?? 'admin/audit-trails');
$auditAction    = trim((string) ($searchFilters['action'] ?? ''));
$perPage        = (int) ($auditListData['perPage'] ?? 50);
$perPageOptions = ($auditListData['perPageOptions'] ?? []) ?: [10, 25, 50, 100];
$page           = (int) ($auditListData['page'] ?? 1);
$totalPages     = (int) ($auditListData['totalPages'] ?? 1);
$totalRows      = (int) ($auditListData['totalRows'] ?? count($recentAudits));
$fromRecord     = (int) ($auditListData['fromRecord'] ?? 0);
$toRecord       = (int) ($auditListData['toRecord'] ?? 0);

// Page URL preserving the database keyword + action filter + page size.
$auditPageUrl = static function (int $targetPage) use ($listRoute, $searchTerm, $auditAction, $perPage): string {
    $params = array_filter([
        'q'        => $searchTerm,
        'action'   => $auditAction,
        'per_page' => $perPage !== 50 ? (string) $perPage : '',
        'page'     => $targetPage > 1 ? (string) $targetPage : '',
    ], static fn ($value): bool => $value !== '');

    return site_url($listRoute) . ($params === [] ? '' : '?' . http_build_query($params));
};

// "Clear" drops the keyword (resets to page 1) but keeps the action filter + page size.
$auditClearUrl = static function () use ($listRoute, $auditAction, $perPage): string {
    $params = array_filter([
        'action'   => $auditAction,
        'per_page' => $perPage !== 50 ? (string) $perPage : '',
    ], static fn ($value): bool => $value !== '');

    return site_url($listRoute) . ($params === [] ? '' : '?' . http_build_query($params));
};

$formatAuditMember = static function (array $audit): string {
    $memberName = trim((string) ($audit['member_name'] ?? ''));

    if ($memberName === '') {
        $memberName = trim((string) ($audit['firstname'] ?? '') . ' ' . (string) ($audit['lastname'] ?? ''));
    }

    return $memberName === '' ? '-' : $memberName;
};

$formatAuditUser = static function (array $audit): string {
    $username = trim((string) ($audit['username'] ?? $audit['userID'] ?? ''));
    $role     = trim((string) ($audit['user_role'] ?? ''));
    $role     = \App\Libraries\RoleAccess::auditRoleLabel($role) ?? $role;

    return $role === '' ? $username : $username . ' (' . $role . ')';
};
?>

<?php /* Jade-style audit panel reusing the Lookups dual-search layout (records-* classes,
         managerecord.css). Bar 1 = database search (server GET) keeping the melbranch hooks
         .js-audit-filter-form + .js-audit-action-filter (audit-filters.js auto-submit). Bar 2 =
         page-size + client-side local "Search:" filter via data-lookup-search (lookup-search.js,
         scoped by data-audit-management-root). */ ?>
<section class="overview-panel audit-trails" aria-label="Audit trails" data-audit-management-root>
    <header class="panel-header">
        <h2>Audit Trails</h2>
    </header>

    <?php /* Bar 1: search the whole audit database (server-side GET) + action filter. */ ?>
    <div class="records-search-panel">
        <form class="records-search-row records-lookup-search js-audit-filter-form" method="get" action="<?= esc(site_url($listRoute), 'attr') ?>" role="search" aria-label="Search the audit database">
            <input class="form-control" type="search" name="q" value="<?= esc($searchTerm, 'attr') ?>" placeholder="Search the whole audit database by user, action, or description" aria-label="Search the audit database" autocomplete="off">
            <select class="form-select records-status-select js-audit-action-filter" name="action" aria-label="Filter by action">
                <option value="">All actions</option>
                <?php foreach ($auditActionOptions as $action): ?>
                    <?php $action = trim((string) $action); ?>
                    <option value="<?= esc($action) ?>" <?= $auditAction === $action ? 'selected' : '' ?>><?= esc($action) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($perPage !== 50): ?><input type="hidden" name="per_page" value="<?= esc((string) $perPage, 'attr') ?>"><?php endif; ?>
            <a class="btn btn-outline-secondary records-search-action" href="<?= esc($auditClearUrl(), 'attr') ?>"><i class="bi bi-x-lg" aria-hidden="true"></i><span>Clear</span></a>
            <button class="btn btn-outline-success records-search-action" type="submit"><i class="bi bi-search" aria-hidden="true"></i><span>Search All</span></button>
        </form>
    </div>

    <?php /* Bar 2: full-width "search this page" local filter (client-side, no reload) + show-entries. */ ?>
    <div class="audit-table-toolbar">
        <form class="records-table-search-form audit-page-search-form" role="search" data-lookup-search aria-label="Filter shown audit logs">
            <input class="form-control audit-page-search" type="search" id="auditLocalSearch" data-lookup-search-input placeholder="Enter keyword to search this page" autocomplete="off" aria-label="Filter shown audit logs">
        </form>
        <form class="records-page-size-form audit-show-entries" method="get" action="<?= esc(site_url($listRoute), 'attr') ?>">
            <?php if ($searchTerm !== ''): ?><input type="hidden" name="q" value="<?= esc($searchTerm, 'attr') ?>"><?php endif; ?>
            <?php if ($auditAction !== ''): ?><input type="hidden" name="action" value="<?= esc($auditAction, 'attr') ?>"><?php endif; ?>
            <label for="auditPerPage">Show</label>
            <select class="form-select form-select-sm" id="auditPerPage" name="per_page" onchange="this.form.submit()">
                <?php foreach ($perPageOptions as $option): ?>
                    <option value="<?= esc((string) $option, 'attr') ?>" <?= $perPage === (int) $option ? 'selected' : '' ?>><?= esc((string) $option) ?></option>
                <?php endforeach; ?>
            </select>
            <span>entries</span>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table audit-trails-table align-middle">
            <thead>
                <tr>
                    <th scope="col">User</th>
                    <th scope="col">Action</th>
                    <th scope="col">Description</th>
                    <th scope="col">User Agent</th>
                    <th scope="col">Date/Time</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentAudits as $audit): ?>
                    <?php
                        $auditTs = strtotime((string) ($audit['dt_created'] ?? ''));
                        $auditUa = trim((string) ($audit['user_agent'] ?? ''));
                    ?>
                    <?php /* The whole row is the detail trigger (js-audit-detail) — audit-detail-modal.js
                             reads data-full and surfaces the narrative in that modal. */ ?>
                    <tr class="audit-row js-audit-detail" tabindex="0" role="button" aria-label="View audit log details"
                        data-full="<?= esc((string) ($audit['full_description'] ?? ''), 'attr') ?>">
                        <td class="audit-user"><?= esc($formatAuditUser($audit)) ?></td>
                        <td><span class="audit-action-pill"><?= esc((string) ($audit['user_action'] ?? '')) ?></span></td>
                        <td class="audit-desc"><?= esc((string) ($audit['description'] ?? '')) ?></td>
                        <td class="audit-ua"><?= $auditUa === '' ? '—' : esc($auditUa) ?></td>
                        <td class="audit-when"><?= $auditTs ? esc(date('M j, Y h:i A', $auditTs)) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($recentAudits === []): ?>
                    <tr><td colspan="5" class="audit-trails-empty audit-empty-state"><?= $hasSearchFilters ? 'No matching audit logs found.' : 'No audit logs yet.' ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalRows > 0): ?>
        <div class="lookup-list-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="text-muted small">Showing <?= esc((string) $fromRecord) ?>–<?= esc((string) $toRecord) ?> of <?= esc((string) $totalRows) ?></span>
            <?php if ($totalPages > 1): ?>
                <div class="d-flex gap-2">
                    <a class="btn btn-outline-secondary btn-sm<?= $page <= 1 ? ' disabled' : '' ?>" href="<?= esc($auditPageUrl(max(1, $page - 1)), 'attr') ?>" aria-disabled="<?= $page <= 1 ? 'true' : 'false' ?>">Previous</a>
                    <span class="btn btn-sm disabled">Page <?= esc((string) $page) ?> of <?= esc((string) $totalPages) ?></span>
                    <a class="btn btn-outline-secondary btn-sm<?= $page >= $totalPages ? ' disabled' : '' ?>" href="<?= esc($auditPageUrl(min($totalPages, $page + 1)), 'attr') ?>" aria-disabled="<?= $page >= $totalPages ? 'true' : 'false' ?>">Next</a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>
