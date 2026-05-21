<?php
$recentAudits = $recentAudits ?? [];
$searchTerm = $searchTerm ?? '';
$searchFilters = $searchFilters ?? [];
$auditActionOptions = $auditActionOptions ?? [];
$hasSearchFilters = $searchTerm !== '' || array_filter($searchFilters, static fn ($value): bool => trim((string) $value) !== '') !== [];
$formatDate = static function (mixed $value): string {
    $timestamp = strtotime((string) $value);

    return $timestamp === false ? '' : date('Y-m-d', $timestamp);
};
$formatTime = static function (mixed $value): string {
    $timestamp = strtotime((string) $value);

    return $timestamp === false ? '' : date('h:i A', $timestamp);
};
?>

<div class="panel">
    <div class="section-title mt-0"><span>Audit Trails</span></div>
    <form class="row g-2 mb-3" method="get" action="<?= site_url('admin/audit-trails') ?>">
        <div class="col-md-6 col-lg-4">
            <input class="form-control" type="search" name="q" value="<?= esc($searchTerm) ?>" placeholder="Search audit trails by user, action, or description">
        </div>
        <div class="col-md-4 col-lg-3">
            <select class="form-select" name="action">
                <option value="">All actions</option>
                <?php foreach ($auditActionOptions as $action): ?>
                    <option value="<?= esc((string) $action) ?>" <?= (string) ($searchFilters['action'] ?? '') === (string) $action ? 'selected' : '' ?>><?= esc((string) $action) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 col-lg-2">
            <input class="form-control" type="date" name="date_from" value="<?= esc((string) ($searchFilters['date_from'] ?? '')) ?>">
        </div>
        <div class="col-md-3 col-lg-2">
            <input class="form-control" type="date" name="date_to" value="<?= esc((string) ($searchFilters['date_to'] ?? '')) ?>">
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
            <thead><tr><th>User</th><th>Action</th><th>Description</th><th>Date</th><th>Time</th></tr></thead>
            <tbody>
                <?php foreach ($recentAudits as $audit): ?>
                    <tr>
                        <td><?= esc((string) ($audit['username'] ?? $audit['userID'] ?? '')) ?></td>
                        <td><?= esc((string) ($audit['user_action'] ?? '')) ?></td>
                        <td><?= esc((string) ($audit['description'] ?? '')) ?></td>
                        <td><?= esc($formatDate($audit['dt_created'] ?? '')) ?></td>
                        <td><?= esc($formatTime($audit['dt_created'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($recentAudits === []): ?>
                    <tr><td colspan="5" class="text-center text-muted"><?= $hasSearchFilters ? 'No matching audit logs found.' : 'No audit logs yet.' ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
