<?php
use App\Libraries\ViewFormatter;

$myAudits = $myAudits ?? [];
$searchTerm = $searchTerm ?? '';
$searchFilters = $searchFilters ?? [];
$auditActionOptions = $auditActionOptions ?? [];
$hasSearchFilters = $hasSearchFilters ?? false;
?>
<?= $this->extend('Employee/layout') ?>
<?= $this->section('content') ?>
<div class="panel">
    <div class="section-title mt-0"><span>My Recent Activity</span></div>
    <form class="row g-2 mb-3 js-audit-filter-form" method="get" action="<?= site_url('employee/activity') ?>">
        <div class="col-md-6 col-lg-4"><input class="form-control" type="search" name="q" value="<?= esc($searchTerm) ?>" placeholder="Search activity by action or description"></div>
        <div class="col-md-4 col-lg-3"><select class="form-select js-audit-action-filter" name="action"><option value="">All actions</option><?php foreach ($auditActionOptions as $action): ?><?php $action = trim((string) $action); ?><option value="<?= esc($action) ?>" <?= trim((string) ($searchFilters['action'] ?? '')) === $action ? 'selected' : '' ?>><?= esc($action) ?></option><?php endforeach; ?></select></div>
        <div class="col-auto"><button class="btn btn-primary" type="submit">Search</button></div>
        <?php if ($hasSearchFilters): ?><div class="col-auto"><a class="btn btn-outline-secondary" href="<?= site_url('employee/activity') ?>">Clear</a></div><?php endif; ?>
    </form>
    <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Action</th><th>Member</th><th>Description</th></tr></thead><tbody>
        <?php foreach ($myAudits as $audit): ?><tr><td><?= esc((string) ($audit['user_action'] ?? '')) ?></td><td><?= esc(ViewFormatter::formatAuditMember($audit)) ?></td><td><?= esc((string) ($audit['description'] ?? '')) ?></td></tr><?php endforeach; ?>
        <?php if ($myAudits === []): ?><tr><td colspan="3" class="text-center text-muted"><?= $hasSearchFilters ? 'No matching activity found.' : 'No activity yet.' ?></td></tr><?php endif; ?>
    </tbody></table></div>
</div>
<?= $this->endSection() ?>
