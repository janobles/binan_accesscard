<?php
use App\Libraries\ViewFormatter;

$recentAudits       = $recentAudits ?? [];
$searchTerm         = $searchTerm ?? '';
$searchFilters      = $searchFilters ?? [];
$auditActionOptions = $auditActionOptions ?? [];
$hasSearchFilters   = $searchTerm !== '' || array_filter($searchFilters, static fn ($value): bool => trim((string) $value) !== '') !== [];
$auditPage          = max(1, (int) ($auditPage ?? 1));
$auditPerPage       = max(1, (int) ($auditPerPage ?? 50));
$auditTotal         = max(0, (int) ($auditTotal ?? count($recentAudits)));
$auditTotalPages    = max(1, (int) ($auditTotalPages ?? (int) ceil($auditTotal / $auditPerPage)));
$auditFromRecord    = max(0, (int) ($auditFromRecord ?? ($auditTotal === 0 ? 0 : (($auditPage - 1) * $auditPerPage) + 1)));
$auditToRecord      = max(0, (int) ($auditToRecord ?? min($auditTotal, $auditPage * $auditPerPage)));
$auditHasRows       = $auditTotal > 0;

$auditPageUrl = static function (int $page) use ($searchTerm, $searchFilters): string {
    $params = [];

    if ($searchTerm !== '') {
        $params['q'] = $searchTerm;
    }

    foreach (['action', 'date', 'date_from', 'date_to'] as $filterKey) {
        $filterValue = trim((string) ($searchFilters[$filterKey] ?? ''));

        if ($filterValue !== '') {
            $params[$filterKey] = $filterValue;
        }
    }

    if ($page > 1) {
        $params['page'] = $page;
    }

    $query = http_build_query($params);

    return site_url('admin/audit-trails' . ($query === '' ? '' : '?' . $query));
};

$formatAuditUser = static function (array $audit): string {
    $username = trim((string) ($audit['username'] ?? ''));
    $userId   = (int) ($audit['userID'] ?? 0);
    $role     = trim((string) ($audit['user_role'] ?? ''));

    if ($username === '') {
        $username = $userId > 0 ? 'User #' . $userId : 'System';
    }

    if ($role === 'User') {
        $role = 'Employee';
    }

    return $role === '' ? $username : $username . ' (' . $role . ')';
};
?>

<?php /* Jade-style reskin (audit-trails-* classes). Keeps the melbranch filter
         form + JS hooks (.js-audit-filter-form, .js-audit-action-filter). The
         table now focuses on timestamp/action metadata instead of member rows. */ ?>
<section class="overview-panel audit-trails" aria-label="Audit trails">
    <form class="audit-trails-toolbar js-audit-filter-form" method="get" action="<?= site_url('admin/audit-trails') ?>">
        <select class="form-select audit-trails-action-filter js-audit-action-filter" name="action" aria-label="Filter audit trails by action">
            <option value="">All actions</option>
            <?php foreach ($auditActionOptions as $action): ?>
                <?php $action = trim((string) $action); ?>
                <option value="<?= esc($action) ?>" <?= trim((string) ($searchFilters['action'] ?? '')) === $action ? 'selected' : '' ?>><?= esc($action) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="audit-trails-search-group">
            <input class="form-control audit-trails-search" type="search" name="q" value="<?= esc($searchTerm) ?>" placeholder="Search timestamp, action, description, IP, agent, or user" aria-label="Search audit trails" data-audit-manual-search>
            <button class="btn btn-success audit-trails-toolbar-action" type="submit"><i class="bi bi-search" aria-hidden="true"></i><span>Search</span></button>
        </div>
        <?php if ($hasSearchFilters): ?>
            <a class="btn btn-outline-danger audit-trails-toolbar-action audit-trails-clear" href="<?= site_url('admin/audit-trails') ?>"><i class="bi bi-x-lg" aria-hidden="true"></i><span>Clear</span></a>
        <?php endif; ?>
    </form>
    <div class="audit-trails-meta-row" data-audit-pagination-summary>
        <div class="audit-trails-results-summary">
            <?php if ($auditHasRows): ?>
                <strong>Page <?= esc(number_format($auditPage)) ?></strong>
                <span>showing audit trails <?= esc(number_format($auditFromRecord)) ?>-<?= esc(number_format($auditToRecord)) ?> of <?= esc(number_format($auditTotal)) ?></span>
            <?php else: ?>
                <strong>No audit trails to show</strong>
            <?php endif; ?>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table audit-trails-table align-middle">
            <thead>
                <tr>
                    <th scope="col">Timestamp</th>
                    <th scope="col">Action</th>
                    <th scope="col">Description</th>
                    <th scope="col">IP Address</th>
                    <th scope="col">Agent</th>
                    <th scope="col">User</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentAudits as $audit): ?>
                    <?php
                    $createdAt = $audit['dt_created'] ?? '';
                    $date = ViewFormatter::formatDate($createdAt);
                    $time = ViewFormatter::formatTime($createdAt);
                    $description = trim((string) ($audit['description'] ?? ''));
                    $ipAddress = trim((string) ($audit['ip_address'] ?? ''));
                    $userAgent = trim((string) ($audit['user_agent'] ?? ''));
                    ?>
                    <tr data-audit-row>
                        <td class="audit-trails-timestamp">
                            <?php if ($date !== '' || $time !== ''): ?>
                                <span><?= esc($date) ?></span>
                                <small><?= esc($time) ?></small>
                            <?php else: ?>
                                <span>-</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge bg-light text-dark border audit-trails-action"><?= esc((string) ($audit['user_action'] ?? '')) ?></span></td>
                        <td class="audit-trails-description"><?= esc($description === '' ? '-' : $description) ?></td>
                        <td class="audit-trails-meta"><?= esc($ipAddress === '' ? '-' : $ipAddress) ?></td>
                        <td class="audit-trails-agent"><span title="<?= esc($userAgent === '' ? '-' : $userAgent, 'attr') ?>"><?= esc($userAgent === '' ? '-' : $userAgent) ?></span></td>
                        <td class="audit-trails-user"><strong><?= esc($formatAuditUser($audit)) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($recentAudits !== []): ?>
                    <tr class="audit-trails-manual-empty d-none" data-audit-manual-empty><td colspan="6" class="audit-trails-empty">No matching rows on this page.</td></tr>
                <?php endif; ?>
                <?php if ($recentAudits === []): ?>
                    <tr><td colspan="6" class="audit-trails-empty"><?= $hasSearchFilters ? 'No matching audit logs found.' : 'No audit logs yet.' ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($auditTotalPages > 1): ?>
        <?php $firstPageUrl = $auditPageUrl(1); ?>
        <?php $previousPageUrl = $auditPageUrl(max(1, $auditPage - 1)); ?>
        <?php $nextPageUrl = $auditPageUrl(min($auditTotalPages, $auditPage + 1)); ?>
        <?php $lastPageUrl = $auditPageUrl($auditTotalPages); ?>
        <nav class="audit-trails-pagination" aria-label="Audit trails pages">
            <div class="audit-trails-pagination-actions">
                <a
                    class="btn btn-outline-secondary btn-sm<?= $auditPage <= 1 ? ' disabled' : '' ?>"
                    href="<?= esc($firstPageUrl, 'attr') ?>"
                    aria-disabled="<?= $auditPage <= 1 ? 'true' : 'false' ?>">
                    First
                </a>
                <a
                    class="btn btn-outline-secondary btn-sm<?= $auditPage <= 1 ? ' disabled' : '' ?>"
                    href="<?= esc($previousPageUrl, 'attr') ?>"
                    aria-disabled="<?= $auditPage <= 1 ? 'true' : 'false' ?>">
                    Previous
                </a>
                <a
                    class="btn btn-outline-secondary btn-sm<?= $auditPage >= $auditTotalPages ? ' disabled' : '' ?>"
                    href="<?= esc($nextPageUrl, 'attr') ?>"
                    aria-disabled="<?= $auditPage >= $auditTotalPages ? 'true' : 'false' ?>">
                    Next
                </a>
                <a
                    class="btn btn-outline-secondary btn-sm<?= $auditPage >= $auditTotalPages ? ' disabled' : '' ?>"
                    href="<?= esc($lastPageUrl, 'attr') ?>"
                    aria-disabled="<?= $auditPage >= $auditTotalPages ? 'true' : 'false' ?>">
                    Last
                </a>
            </div>
        </nav>
    <?php endif; ?>
</section>
