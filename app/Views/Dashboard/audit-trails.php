<?php
helper('dashboard_view');
extract(audit_trails_view_data(get_defined_vars()), EXTR_OVERWRITE);
?>

<div class="panel">
    <div class="section-title mt-0">
        <span>Audit Trails</span>
    </div>
    <form class="row g-2 mb-3 js-audit-filter-form" method="get" action="<?= site_url('admin/audit-trails') ?>">
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
        <div class="col-md-3 col-lg-2">
            <input class="form-control" type="date" name="date" value="<?= esc($selectedFilterDate) ?>" aria-label="Filter by date">
        </div>
        <div class="col-auto">
            <button class="btn btn-primary" type="submit">Search</button>
        </div>
        <?php if ($hasSearchFilters): ?>
            <div class="col-auto">
                <a class="btn btn-outline-secondary" href="<?= site_url('admin/audit-trails') ?>">Clear</a>
            </div>
        <?php endif; ?>
    </form>
    <div class="table-responsive">
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Member</th>
                    <th>Action</th>
                    <th>Description</th>
                    <th>Date</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentAudits as $audit): ?>
                    <tr>
                        <td><?= esc($formatAuditUser($audit)) ?></td>
                        <td><?= esc($formatAuditMember($audit)) ?></td>
                        <td><?= esc((string) ($audit['user_action'] ?? '')) ?></td>
                        <td><?= esc((string) ($audit['description'] ?? '')) ?></td>
                        <td><?= esc($formatDate($audit['dt_created'] ?? '')) ?></td>
                        <td><?= esc($formatTime($audit['dt_created'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($recentAudits === []): ?>
                    <tr><td colspan="6" class="text-center text-muted"><?= $hasSearchFilters ? 'No matching audit logs found.' : 'No audit logs yet.' ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
(function () {
    document.querySelectorAll('.js-audit-action-filter').forEach(function (select) {
        select.addEventListener('change', function () {
            const form = select.closest('.js-audit-filter-form');

            if (form) {
                form.submit();
            }
        });
    });
})();
</script>
