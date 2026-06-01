<?php
use App\Libraries\ViewFormatter;

$stats = $stats ?? [];
$recentFamilies = $recentFamilies ?? [];
$myAudits = $myAudits ?? [];
$searchTerm = $searchTerm ?? '';
$searchFilters = $searchFilters ?? [];
$sectorOptions = $sectorOptions ?? [];
$selectedFilterDate = $selectedFilterDate ?? '';
$hasSearchFilters = $hasSearchFilters ?? false;
?>
<?= $this->extend('Employee/layout') ?>
<?= $this->section('content') ?>
<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="panel"><small>Total Records</small><div class="stat-value"><?= esc((string) ($stats['families'] ?? 0)) ?></div></div></div>
    <div class="col-md-3"><div class="panel"><small>Registered Members</small><div class="stat-value"><?= esc((string) ($stats['members'] ?? 0)) ?></div></div></div>
    <div class="col-md-3"><div class="panel"><small>Active Sectors</small><div class="stat-value"><?= esc((string) ($stats['sectors'] ?? 0)) ?></div></div></div>
    <div class="col-md-3"><div class="panel"><small>Services and Programs</small><div class="stat-value"><?= esc((string) ($stats['assistance'] ?? 0)) ?></div></div></div>
</div>
<div class="panel mb-3">
    <div class="section-title mt-0"><span>Recently Added Records</span></div>
    <form class="row g-2 mb-3" method="get" action="<?= site_url('employee/workspace') ?>">
        <div class="col-md-6 col-lg-4"><input class="form-control" type="search" name="q" value="<?= esc($searchTerm) ?>" placeholder="Search records by name, contact number, or sector"></div>
        <div class="col-md-4 col-lg-3"><select class="form-select" name="sectorID"><option value="">All sectors</option><?php foreach ($sectorOptions as $sector): ?><?php $sectorId = (string) ($sector['sectorID'] ?? ''); ?><option value="<?= esc($sectorId) ?>" <?= (string) ($searchFilters['sectorID'] ?? '') === $sectorId ? 'selected' : '' ?>><?= esc((string) ($sector['name'] ?? '')) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3 col-lg-2"><input class="form-control" type="date" name="date" value="<?= esc($selectedFilterDate) ?>" aria-label="Filter by date"></div>
        <div class="col-auto"><button class="btn btn-primary" type="submit">Search</button></div>
        <?php if ($hasSearchFilters): ?><div class="col-auto"><a class="btn btn-outline-secondary" href="<?= site_url('employee/workspace') ?>">Clear</a></div><?php endif; ?>
    </form>
    <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Head</th><th>Sector</th><th>Date</th><th>Time</th></tr></thead><tbody>
        <?php foreach ($recentFamilies as $family): ?><tr><td><?= esc(($family['firstname'] ?? '') . ' ' . ($family['lastname'] ?? '')) ?></td><td><?= esc((string) ($family['sector_name'] ?? '')) ?></td><td><?= esc(ViewFormatter::formatDate($family['dt_created'] ?? '')) ?></td><td><?= esc(ViewFormatter::formatTime($family['dt_created'] ?? '')) ?></td></tr><?php endforeach; ?>
        <?php if ($recentFamilies === []): ?><tr><td colspan="4" class="text-center text-muted"><?= $searchTerm !== '' || $hasSearchFilters ? 'No matching records found.' : 'No records yet.' ?></td></tr><?php endif; ?>
    </tbody></table></div>
</div>
<div class="panel">
    <div class="section-title mt-0"><span>Recent Activity</span><a class="btn btn-outline-secondary btn-sm" href="<?= site_url('employee/activity') ?>">View All</a></div>
    <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Action</th><th>Member</th><th>Description</th><th>Date</th><th>Time</th></tr></thead><tbody>
        <?php foreach ($myAudits as $audit): ?><tr><td><?= esc((string) ($audit['user_action'] ?? '')) ?></td><td><?= esc(ViewFormatter::formatAuditMember($audit)) ?></td><td><?= esc((string) ($audit['description'] ?? '')) ?></td><td><?= esc(ViewFormatter::formatDate($audit['dt_created'] ?? '')) ?></td><td><?= esc(ViewFormatter::formatTime($audit['dt_created'] ?? '')) ?></td></tr><?php endforeach; ?>
        <?php if ($myAudits === []): ?><tr><td colspan="5" class="text-center text-muted">No activity yet.</td></tr><?php endif; ?>
    </tbody></table></div>
</div>
<?= $this->endSection() ?>
