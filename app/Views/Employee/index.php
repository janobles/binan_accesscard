<?php
/**
 * View: Employee Workspace
 *
 * Main layout shell for the employee (User role) interface.
 * Loads the dashboard_view helper and extracts view data via
 * employee_dashboard_view_data().
 *
 * Variables injected:
 *   @var string  $pageTitle          Current page heading
 *   @var string  $username           Logged-in employee username
 *   @var string  $activePage         Which content panel to render
 *   @var array   $navActive          Active-class map for nav links
 *   @var bool    $canCreateFamily    Whether the family form submit is enabled
 *   @var array   $stats              Summary counts (families, members, …)
 *   @var array   $sectorOptions      Sector list for filter dropdowns
 *   @var array   $recentFamilies     Latest family records
 *   @var array   $myAudits           Audit entries for the current user
 *   @var array   $searchFilters      Active filter values
 *   @var string  $searchTerm         Current search keyword
 *   @var bool    $hasSearchFilters   Whether any filter is applied
 *   @var array   $familyFormViewData Data for the family wizard
 *   @var int     $idleTimeoutSeconds Idle timeout for session-timeout.js
 */
helper('dashboard_view');
extract(employee_dashboard_view_data(get_defined_vars()), EXTR_OVERWRITE);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($pageTitle) ?> - Binan Access Card MIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('assets/css/admin.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/admin.css') ?>">
</head>
<body>

<!-- ================================================================
     APP SHELL — wraps the sidebar and main content area
     ================================================================ -->
<div class="app-shell">

    <!-- ============================================================
         SIDEBAR — branding, navigation, and logout footer
         ============================================================ -->
    <aside class="sidebar employee">
        <div>
            <div class="brand">
                <img src="<?= base_url('assets/image/binan.png') ?>" alt="City of Binan Logo">
                <div>
                    <strong>Bi&ntilde;an Access Card MIS</strong>
                    <small class="d-block">Employee Workspace</small>
                </div>
            </div>
            <nav class="nav flex-column mt-3">
                <a class="nav-link <?= esc($navActive['dashboard'] ?? '') ?>" href="<?= site_url('employee/workspace') ?>">Workspace</a>
                <a class="nav-link <?= esc($navActive['family-entry'] ?? '') ?> js-open-family-modal" href="<?= site_url('employee/manage-family') ?>" data-modal-url="<?= site_url('employee/manage-family?partial=1') ?>" data-modal-title="Add Family">Add Family</a>
                <a class="nav-link <?= esc($navActive['family-manage'] ?? '') ?> js-open-family-list" href="<?= site_url('employee/manage-family/list') ?>" data-modal-url="<?= site_url('employee/manage-family/list?partial=1') ?>" data-modal-title="Manage Families">Manage Family</a>
                <a class="nav-link <?= esc($navActive['activity'] ?? '') ?>" href="<?= site_url('employee/activity') ?>">My Activity</a>
            </nav>
        </div>
        <!-- Sidebar footer — shows logged-in employee and logout button -->
        <div class="sidebar-footer">
            <div class="sidebar-user"><?= esc($username) ?> &middot; Employee</div>
            <a href="<?= site_url('logout') ?>" class="btn btn-outline-light btn-sm w-100">Logout</a>
        </div>
    </aside>
    <!-- /SIDEBAR -->

    <!-- ============================================================
         MAIN CONTENT — topbar header + scrollable page body
         ============================================================ -->
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

            <!-- Flash messages — displayed once after a redirect, then discarded -->
            <?php if (session()->getFlashdata('success')): ?>
                <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
            <?php endif; ?>
            <?php if (session()->getFlashdata('error')): ?>
                <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
            <?php endif; ?>

            <!-- ==================================================
                 PAGE: WORKSPACE — summary stats + recent records
                 ================================================== -->
            <?php if ($activePage === 'dashboard'): ?>

                <!-- Summary stat cards -->
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <div class="panel">
                            <small>Total Families</small>
                            <div class="stat-value"><?= esc((string) ($stats['families'] ?? 0)) ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="panel">
                            <small>Registered Members</small>
                            <div class="stat-value"><?= esc((string) ($stats['members'] ?? 0)) ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="panel">
                            <small>Active Sectors</small>
                            <div class="stat-value"><?= esc((string) ($stats['sectors'] ?? 0)) ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="panel">
                            <small>Member Services</small>
                            <div class="stat-value"><?= esc((string) ($stats['assistance'] ?? 0)) ?></div>
                        </div>
                    </div>
                </div>

                <div class="panel mb-3">
                    <div class="section-title mt-0">
                        <span>Recently Added Families</span>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-primary btn-sm js-open-family-modal" data-modal-url="<?= site_url('employee/manage-family?partial=1') ?>" data-modal-title="Add Family">Add Family</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm js-open-family-list" data-modal-url="<?= site_url('employee/manage-family/list?partial=1') ?>" data-modal-title="Manage Families">Manage Family</button>
                        </div>
                    </div>
                    <form class="row g-2 mb-3" method="get" action="<?= site_url('employee/workspace') ?>">
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
                                <a class="btn btn-outline-secondary" href="<?= site_url('employee/workspace') ?>">Clear</a>
                            </div>
                        <?php endif; ?>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Head</th>
                                    <th>Barangay</th>
                                    <th>Sector</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentFamilies as $family): ?>
                                    <tr>
                                        <td><?= esc(($family['firstname'] ?? '') . ' ' . ($family['lastname'] ?? '')) ?></td>
                                        <td><?= esc((string) ($family['barangay'] ?? '')) ?></td>
                                        <td><?= esc((string) ($family['sector_name'] ?? '')) ?></td>
                                        <td><?= esc($formatDate($family['dt_created'] ?? '')) ?></td>
                                        <td><?= esc($formatTime($family['dt_created'] ?? '')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($recentFamilies === []): ?>
                                    <tr><td colspan="5" class="text-center text-muted"><?= $searchTerm !== '' || $hasSearchFilters ? 'No matching family records found.' : 'No family records yet.' ?></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="panel">
                    <div class="section-title mt-0">
                        <span>Recent Activity</span>
                        <a class="btn btn-outline-secondary btn-sm" href="<?= site_url('employee/activity') ?>">View All</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Action</th>
                                    <th>Description</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($myAudits as $audit): ?>
                                    <tr>
                                        <td><?= esc((string) ($audit['user_action'] ?? '')) ?></td>
                                        <td><?= esc((string) ($audit['description'] ?? '')) ?></td>
                                        <td><?= esc($formatDate($audit['dt_created'] ?? '')) ?></td>
                                        <td><?= esc($formatTime($audit['dt_created'] ?? '')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($myAudits === []): ?>
                                    <tr><td colspan="4" class="text-center text-muted">No activity yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ==================================================
                 PAGE: FAMILY ENTRY — multi-step family data wizard
                 ================================================== -->
            <?php if ($activePage === 'family-entry'): ?>
                <div class="panel">
                    <div class="section-title mt-0">
                        <span>Family / Member Data Entry</span>
                    </div>
                    <?= view('Dashboard/familyform', array_merge(
                        $familyFormViewData,
                        ['canCreateFamily' => $canCreateFamily]
                    )) ?>
                </div>
            <?php endif; ?>

            <!-- ==================================================
                 PAGE: ACTIVITY — employee’s own audit history
                 ================================================== -->
            <?php if ($activePage === 'activity'): ?>
                <div class="panel">
                    <div class="section-title mt-0"><span>My Recent Activity</span></div>
                    <form class="row g-2 mb-3" method="get" action="<?= site_url('employee/activity') ?>">
                        <div class="col-md-6 col-lg-4">
                            <input class="form-control" type="search" name="q" value="<?= esc($searchTerm) ?>" placeholder="Search activity by action or description">
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
                                <a class="btn btn-outline-secondary" href="<?= site_url('employee/activity') ?>">Clear</a>
                            </div>
                        <?php endif; ?>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Action</th>
                                    <th>Description</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($myAudits as $audit): ?>
                                    <tr>
                                        <td><?= esc((string) ($audit['user_action'] ?? '')) ?></td>
                                        <td><?= esc((string) ($audit['description'] ?? '')) ?></td>
                                        <td><?= esc($formatDate($audit['dt_created'] ?? '')) ?></td>
                                        <td><?= esc($formatTime($audit['dt_created'] ?? '')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($myAudits === []): ?>
                                    <tr><td colspan="4" class="text-center text-muted"><?= $hasSearchFilters ? 'No matching activity found.' : 'No activity yet.' ?></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <!-- /MAIN CONTENT -->

</div>
<!-- /APP SHELL -->

<!-- ================================================================
     FAMILY MODAL — floating overlay for family-related actions
     ================================================================ -->
<div class="modal fade floating-family-modal" id="familyModal" tabindex="-1" aria-labelledby="familyModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="btn btn-outline-secondary family-modal-back js-family-modal-back" aria-label="Back">
                    <span aria-hidden="true">&larr;</span> Back
                </button>
                <h5 class="modal-title visually-hidden" id="familyModalLabel">Manage Family</h5>
                <button type="button" class="btn-close family-modal-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="familyModalBody">
                <!-- Loading spinner — visible while the partial view is fetched via AJAX -->
            <div class="family-modal-loading" role="status" aria-live="polite">
                    <div class="spinner-border text-primary" aria-hidden="true"></div>
                    <span>Loading...</span>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- /FAMILY MODAL -->

<!-- ================================================================
     SCRIPTS — jQuery, Bootstrap bundle, and app-specific JS modules
     ================================================================ -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Family form UI behaviour (wizard step transitions, inline validation) -->
<script src="<?= base_url('assets/js/dashboard/family-form-ui.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/dashboard/family-form-ui.js') ?>"></script>
<!-- Family form AJAX submission and dynamic member row management -->
<script src="<?= base_url('assets/js/dashboard/family-form.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/dashboard/family-form.js') ?>"></script>
<!-- Session idle-timeout handler — auto-logs out after inactivity -->
<script src="<?= base_url('assets/js/session-timeout.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/session-timeout.js') ?>"
        data-timeout-seconds="<?= esc((string) $idleTimeoutSeconds) ?>"
        data-logout-url="<?= site_url('logout?timeout=1') ?>"
        data-keep-alive-url="<?= site_url('session/keep-alive') ?>"></script>
<!-- Dashboard modal loader — fetches partial views into #familyModalBody via AJAX -->
<script src="<?= base_url('assets/js/dashboard/dashboard-modal-loader.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/dashboard/dashboard-modal-loader.js') ?>"></script>
<!-- Individual modal openers -->
<script src="<?= base_url('assets/js/dashboard/manage-family-modal.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/dashboard/manage-family-modal.js') ?>"></script>
</body>
</html>
