<!DOCTYPE html>
<?php
$user = $user ?? [];
$username = $user['username'] ?? 'Admin';
$activePage = $activePage ?? 'dashboard';
$pageTitle = $pageTitle ?? 'Dashboard';
$modeLabel = $modeLabel ?? 'Admin Console';
$canManageAccounts = $canManageAccounts ?? false;
$navActive = $navActive ?? [];
$stats = $stats ?? ['families' => 0, 'members' => 0, 'sectors' => 0, 'assistance' => 0];
$recentFamilies = $recentFamilies ?? [];
$recentAudits = $recentAudits ?? [];
$adminAccounts = $adminAccounts ?? [];
$employeeAccounts = $employeeAccounts ?? [];
$familyFormViewData = $familyFormViewData ?? [];
$searchTerm = $searchTerm ?? '';
$searchFilters = $searchFilters ?? [];
$auditActionOptions = $auditActionOptions ?? [];
$sectorOptions = $familyFormViewData['sectorOptions'] ?? [];
$hasSearchFilters = $searchTerm !== '' || array_filter($searchFilters, static fn ($value): bool => trim((string) $value) !== '') !== [];
$canCreateFamily = $canCreateFamily ?? false;
$idleTimeoutSeconds = $idleTimeoutSeconds ?? 900;
?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($pageTitle) ?> - Binan Access Card MIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('assets/css/admin.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/admin.css') ?>">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <div>
            <div class="brand">
                <img src="<?= base_url('assets/image/binan.png') ?>" alt="City of Binan Logo">
                <div>
                    <strong>Bi&ntilde;an Access Card MIS</strong>
                    <small><?= esc($modeLabel) ?></small>
                </div>
            </div>
            <nav class="nav flex-column mt-3">
                <a class="nav-link <?= esc($navActive['dashboard'] ?? '') ?>" href="<?= site_url('admin/dashboard') ?>">Dashboard</a>
                <?php if ($canManageAccounts): ?>
                    <a class="nav-link <?= esc($navActive['accounts'] ?? '') ?> js-open-accounts-modal" href="<?= site_url('admin/accounts') ?>" data-modal-url="<?= site_url('admin/accounts?partial=1') ?>" data-modal-title="Account Management">Account Management</a>
                <?php endif; ?>
                <a class="nav-link <?= esc($navActive['family-entry'] ?? '') ?> js-open-family-modal" href="<?= site_url('admin/manage-family') ?>" data-modal-url="<?= site_url('admin/manage-family?partial=1') ?>" data-modal-title="Add Family">Add Family</a>
                <a class="nav-link <?= esc($navActive['family-manage'] ?? '') ?> js-open-family-list" href="<?= site_url('admin/manage-family/list') ?>" data-modal-url="<?= site_url('admin/manage-family/list?partial=1') ?>" data-modal-title="Manage Families">Manage Family</a>
                <a class="nav-link <?= esc($navActive['audit-trails'] ?? '') ?> js-open-audit-modal" href="<?= site_url('admin/audit-trails') ?>" data-modal-url="<?= site_url('admin/audit-trails?partial=1') ?>" data-modal-title="Audit Trails">Audit Trails</a>
            </nav>
        </div>
        <div class="sidebar-footer">
            <div class="sidebar-user"><?= esc($username) ?> &middot; <?= esc($modeLabel) ?></div>
            <a href="<?= site_url('logout') ?>" class="btn btn-outline-light btn-sm w-100">Logout</a>
        </div>
    </aside>

    <main class="content">
        <div class="topbar">
            <div class="topbar-brand">
                <img src="<?= base_url('assets/image/binan.png') ?>" alt="City of Binan Logo">
            </div>
            <div class="topbar-text">
                <div class="fw-bold"><?= esc($pageTitle) ?></div>
                <small class="text-muted">Bi&ntilde;an Access Card MIS</small>
            </div>
        </div>

        <div class="container-fluid py-4">
            <?php if (session()->getFlashdata('success')): ?>
                <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
            <?php endif; ?>
            <?php if (session()->getFlashdata('error')): ?>
                <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
            <?php endif; ?>

            <?php if ($activePage === 'dashboard'): ?>
                <div class="row g-3 mb-3">
                    <div class="col-md-3"><div class="panel"><small>Total Families</small><div class="stat-value"><?= esc((string) ($stats['families'] ?? 0)) ?></div></div></div>
                    <div class="col-md-3"><div class="panel"><small>Registered Members</small><div class="stat-value"><?= esc((string) ($stats['members'] ?? 0)) ?></div></div></div>
                    <div class="col-md-3"><div class="panel"><small>Active Sectors</small><div class="stat-value"><?= esc((string) ($stats['sectors'] ?? 0)) ?></div></div></div>
                    <div class="col-md-3"><div class="panel"><small>Member Services</small><div class="stat-value"><?= esc((string) ($stats['assistance'] ?? 0)) ?></div></div></div>
                </div>

                <div class="panel mb-3">
                    <div class="section-title mt-0">
                        <span>Recent Families</span>
                        <button type="button" class="btn btn-primary btn-sm js-open-family-modal" data-modal-url="<?= site_url('admin/manage-family?partial=1') ?>" data-modal-title="Manage Family">Add Record</button>
                    </div>
                    <form class="row g-2 mb-3" method="get" action="<?= site_url('admin/dashboard') ?>">
                        <div class="col-md-6 col-lg-4">
                            <input class="form-control" type="search" name="q" value="<?= esc($searchTerm) ?>" placeholder="Search records by name, contact number, or sector">
                        </div>
                        <div class="col-md-4 col-lg-3">
                            <select class="form-select" name="sectorID">
                                <option value="">All sectors</option>
                                <?php foreach ($sectorOptions as $sector): ?>
                                    <?php $sectorId = (string) ($sector['sectorID'] ?? ''); ?>
                                    <option value="<?= esc($sectorId) ?>" <?= (string) ($searchFilters['sectorID'] ?? '') === $sectorId ? 'selected' : '' ?>><?= esc((string) ($sector['name'] ?? '')) ?></option>
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
                                <a class="btn btn-outline-secondary" href="<?= site_url('admin/dashboard') ?>">Clear</a>
                            </div>
                        <?php endif; ?>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead><tr><th>Head</th><th>Barangay</th><th>Sector</th><th>Date</th></tr></thead>
                            <tbody>
                                <?php foreach ($recentFamilies as $family): ?>
                                    <tr>
                                        <td><?= esc(($family['firstname'] ?? '') . ' ' . ($family['lastname'] ?? '')) ?></td>
                                        <td><?= esc((string) ($family['barangay'] ?? '')) ?></td>
                                        <td><?= esc((string) ($family['sector_name'] ?? '')) ?></td>
                                        <td><?= esc((string) ($family['dt_created'] ?? '')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($recentFamilies === []): ?>
                                    <tr><td colspan="4" class="text-center text-muted"><?= $searchTerm !== '' || $hasSearchFilters ? 'No matching family records found.' : 'No family records yet.' ?></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($activePage === 'accounts' && $canManageAccounts): ?>
                <?= view('Dashboard/accounts', [
                    'adminAccounts' => $adminAccounts,
                    'employeeAccounts' => $employeeAccounts,
                    'searchTerm' => $searchTerm,
                    'searchFilters' => $searchFilters,
                ]) ?>
            <?php endif; ?>

            <?php if ($activePage === 'family-entry'): ?>
                <div class="panel mb-3">
                    <div class="section-title mt-0"><span>Family / Member Data Entry</span></div>
                    <?= view('Dashboard/familyform', array_merge(
                        $familyFormViewData,
                        ['canCreateFamily' => $canCreateFamily]
                    )) ?>
                </div>
            <?php endif; ?>

            <?php if ($activePage === 'audit-trails'): ?>
                <?= view('Dashboard/audit-trails', [
                    'recentAudits' => $recentAudits,
                    'searchTerm' => $searchTerm,
                    'searchFilters' => $searchFilters,
                    'auditActionOptions' => $auditActionOptions,
                ]) ?>
            <?php endif; ?>
        </div>
    </main>
</div>

<div class="modal fade floating-family-modal" id="familyModal" tabindex="-1" aria-labelledby="familyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="familyModalLabel">Manage Family</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="familyModalBody">
                <div class="family-modal-loading" role="status" aria-live="polite">
                    <div class="spinner-border text-primary" aria-hidden="true"></div>
                    <span>Loading...</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= base_url('assets/js/dashboard/manage-family-form.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/dashboard/manage-family-form.js') ?>"></script>
<script src="<?= base_url('assets/js/dashboard/family-form.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/dashboard/family-form.js') ?>"></script>
<script src="<?= base_url('assets/js/session-timeout.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/session-timeout.js') ?>" data-timeout-seconds="<?= esc((string) $idleTimeoutSeconds) ?>" data-logout-url="<?= site_url('logout?timeout=1') ?>" data-keep-alive-url="<?= site_url('session/keep-alive') ?>"></script>
<?php if ($activePage === 'dashboard'): ?>
<script src="<?= base_url('assets/js/dashboard/dashboard-modal-loader.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/dashboard/dashboard-modal-loader.js') ?>"></script>
<script src="<?= base_url('assets/js/dashboard/manage-family-modal.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/dashboard/manage-family-modal.js') ?>"></script>
<script src="<?= base_url('assets/js/dashboard/accounts-modal.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/dashboard/accounts-modal.js') ?>"></script>
<script src="<?= base_url('assets/js/dashboard/audit-trails-modal.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/dashboard/audit-trails-modal.js') ?>"></script>
<?php endif; ?>
</body>
</html>
