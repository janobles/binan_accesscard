<?php
$recentAudits       = $recentAudits ?? [];
$searchTerm         = $searchTerm ?? '';
$searchFilters      = $searchFilters ?? [];
$auditActionOptions = $auditActionOptions ?? [];
$hasSearchFilters   = $searchTerm !== '' || array_filter($searchFilters, static fn ($value): bool => trim((string) $value) !== '') !== [];

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
    $role     = \App\Libraries\RoleAccess::normalizeRole($role) ?? $role;

    return $role === '' ? $username : $username . ' (' . $role . ')';
};
?>

<?php /* Jade-style reskin (audit-trails-* classes). Keeps the melbranch filter
         form + JS hooks (.js-audit-filter-form, .js-audit-action-filter). The
         date filter and Date/Time columns were removed to match the jade design. */ ?>
<section class="overview-panel audit-trails" aria-label="Audit trails">
    <header class="panel-header">
        <h2>Audit Trails</h2>
    </header>
    <form class="row g-2 filter-bar audit-filter-bar js-audit-filter-form" method="get" action="<?= site_url('admin/audit-trails') ?>">
        <div class="col-md-6 col-lg-4">
            <input class="form-control" type="search" name="q" value="<?= esc($searchTerm) ?>" placeholder="Search audit trails by user, action, or description">
        </div>
        <div class="col-md-4 col-lg-3">
            <select class="form-select js-audit-action-filter" name="action">
                <option value="">All actions</option>
                <?php foreach ($auditActionOptions as $action): ?>
                    <?php $action = trim((string) $action); ?>
                    <option value="<?= esc($action) ?>" <?= trim((string) ($searchFilters['action'] ?? '')) === $action ? 'selected' : '' ?>><?= esc($action) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <button class="btn btn-success" type="submit"><i class="bi bi-search me-1" aria-hidden="true"></i>Search</button>
        </div>
        <?php if ($hasSearchFilters): ?>
            <div class="col-auto">
                <a class="btn btn-outline-secondary" href="<?= site_url('admin/audit-trails') ?>"><i class="bi bi-x-lg me-1" aria-hidden="true"></i>Clear</a>
            </div>
        <?php endif; ?>
    </form>
    <div class="table-responsive">
        <table class="table audit-trails-table align-middle">
            <thead>
                <tr>
                    <th scope="col">User</th>
                    <th scope="col">Member</th>
                    <th scope="col">Action</th>
                    <th scope="col">Description</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentAudits as $audit): ?>
                    <tr>
                        <td><strong><?= esc($formatAuditUser($audit)) ?></strong></td>
                        <td><?= esc($formatAuditMember($audit)) ?></td>
                        <td><span class="badge bg-light text-dark border"><?= esc((string) ($audit['user_action'] ?? '')) ?></span></td>
                        <td><?= esc((string) ($audit['description'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($recentAudits === []): ?>
                    <tr><td colspan="4" class="audit-trails-empty"><?= $hasSearchFilters ? 'No matching audit logs found.' : 'No audit logs yet.' ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
