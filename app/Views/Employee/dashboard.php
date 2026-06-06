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
<div class="panel mb-3" data-dashboard-search-panel>
    <div class="section-title mt-0"><span>Recently Added Records</span></div>
    <?= view('Dashboard/partials/search-bar', [
        'searchTerm'        => $searchTerm,
        'sectorOptions'     => $sectorOptions,
        'selectedSectorId'  => (string) ($searchFilters['sectorID'] ?? ''),
        'searchAction'      => site_url('employee/workspace'),
        'searchAllAction'   => site_url('employee/manage-records'),
    ]) ?>
    <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Name (Head)</th><th>Sector</th></tr></thead><tbody>
        <?php foreach ($recentFamilies as $family): ?>
            <tr data-record-row data-sector-ids="<?= esc((string) ($family['sectorID'] ?? '[]'), 'attr') ?>">
                <td data-record-name><?= esc(trim(($family['firstname'] ?? '') . ' ' . ($family['lastname'] ?? ''))) ?></td>
                <td data-record-sector><?= esc((string) ($family['sector_name'] ?? '')) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($recentFamilies === []): ?><tr><td colspan="2" class="text-center text-muted"><?= $searchTerm !== '' || $hasSearchFilters ? 'No matching records found.' : 'No records yet.' ?></td></tr><?php endif; ?>
    </tbody></table></div>
</div>
<div class="panel">
    <div class="section-title mt-0"><span>Recent Activity</span><a class="btn btn-outline-secondary btn-sm" href="<?= site_url('employee/activity') ?>">View All</a></div>
    <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Action</th><th>Member</th><th>Description</th></tr></thead><tbody>
        <?php foreach ($myAudits as $audit): ?><tr><td><?= esc((string) ($audit['user_action'] ?? '')) ?></td><td><?= esc(ViewFormatter::formatAuditMember($audit)) ?></td><td><?= esc((string) ($audit['description'] ?? '')) ?></td></tr><?php endforeach; ?>
        <?php if ($myAudits === []): ?><tr><td colspan="3" class="text-center text-muted">No activity yet.</td></tr><?php endif; ?>
    </tbody></table></div>
</div>
<?= $this->endSection() ?>
