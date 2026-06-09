<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($pageTitle ?? 'Dashboard') ?> | Binan Access Card Portal</title>
    <?= dashboard_styles() ?>
</head>
<body>
    <?php
    $currentPage = $activePage ?? 'dashboard';
    $dashboardMode = (string) ($dashboardMode ?? 'admin');
    $isEmployeeDashboard = $dashboardMode === 'employee';
    $dashboardHomeUrl = $isEmployeeDashboard ? site_url('employee/workspace') : site_url('admin/dashboard');
    $manageRecordsUrl = $isEmployeeDashboard ? site_url('employee/manage-records') : site_url('admin/manage-records');
    $activityUrl = $isEmployeeDashboard ? site_url('employee/activity') : site_url('admin/audit-trails');
    $workspaceUrl ??= $currentPage === 'family-manage' ? $manageRecordsUrl : $dashboardHomeUrl;
    ?>

    <div class="dashboard-shell">
        <aside class="dashboard-sidebar" id="dashboard-sidebar">
            <a class="sidebar-brand" href="<?= esc($dashboardHomeUrl, 'attr') ?>">
                <img
                    class="sidebar-logo"
                    src="<?= base_url('assets/image/binan.png') ?>"
                    alt="City of Binan Logo"
                >
                <span class="sidebar-brand-text">Binan AccessCard MIS</span>
            </a>

            <nav aria-label="<?= $isEmployeeDashboard ? 'Employee navigation' : 'Admin navigation' ?>">
                <section class="sidebar-section">
                    <h2 class="sidebar-heading">Overview</h2>
                    <ul class="nav nav-pills flex-column">
                        <li class="nav-item">
                            <a
                                class="nav-link<?= $currentPage === 'dashboard' ? ' active' : '' ?>"
                                href="<?= esc($dashboardHomeUrl, 'attr') ?>"
                                data-workspace-link
                                data-page-title="<?= $isEmployeeDashboard ? 'Workspace' : 'Dashboard' ?>"
                            >
                                <i class="bi bi-speedometer2" aria-hidden="true"></i>
                                <span><?= $isEmployeeDashboard ? 'Workspace' : 'Dashboard' ?></span>
                            </a>
                        </li>
                    </ul>
                </section>

                <section class="sidebar-section">
                    <h2 class="sidebar-heading">Records</h2>
                    <ul class="nav nav-pills flex-column">
                        <li class="nav-item">
                            <a
                                class="nav-link<?= $currentPage === 'family-manage' ? ' active' : '' ?>"
                                href="<?= esc($manageRecordsUrl, 'attr') ?>"
                                data-workspace-link
                                data-page-title="Manage Records"
                            >
                                <i class="bi bi-people" aria-hidden="true"></i>
                                <span>Manage Records</span>
                            </a>
                        </li>
                    </ul>
                </section>

                <?php if (! $isEmployeeDashboard): ?>
                    <section class="sidebar-section">
                        <h2 class="sidebar-heading">Reference Data</h2>
                        <ul class="nav nav-pills flex-column">
                            <li class="nav-item">
                                <a
                                    class="nav-link<?= $currentPage === 'sectors' ? ' active' : '' ?>"
                                    href="<?= site_url('admin/sectors') ?>"
                                    data-workspace-link
                                    data-page-title="Sector Management">
                                    <i class="bi bi-diagram-3" aria-hidden="true"></i>
                                    <span>Sector Management</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a
                                    class="nav-link<?= $currentPage === 'services' ? ' active' : '' ?>"
                                    href="<?= site_url('admin/services') ?>"
                                    data-workspace-link
                                    data-page-title="Services and Programs"
                                >
                                    <i class="bi bi-grid" aria-hidden="true"></i>
                                    <span>Services and Programs</span>
                                </a>
                            </li>
                        </ul>
                    </section>
                <?php endif; ?>

                <section class="sidebar-section">
                    <h2 class="sidebar-heading">Administration</h2>
                    <ul class="nav nav-pills flex-column">
                        <?php if (! $isEmployeeDashboard): ?>
                            <li class="nav-item">
                                <a
                                    class="nav-link<?= $currentPage === 'accounts' ? ' active' : '' ?>"
                                    href="<?= site_url('admin/accounts') ?>"
                                    data-workspace-link
                                    data-page-title="Account Management"
                                >
                                    <i class="bi bi-person-gear" aria-hidden="true"></i>
                                    <span>Account Management</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a
                                class="nav-link<?= in_array($currentPage, ['audit-trails', 'activity'], true) ? ' active' : '' ?>"
                                href="<?= esc($activityUrl, 'attr') ?>"
                                data-workspace-link
                                data-page-title="<?= $isEmployeeDashboard ? 'My Trails' : 'Audit Trails' ?>"
                            >
                                <i class="bi bi-clock-history" aria-hidden="true"></i>
                                <span><?= $isEmployeeDashboard ? 'My Trails' : 'Audit Trails' ?></span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= site_url('logout') ?>">
                                <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
                                <span>Logout</span>
                            </a>
                        </li>
                    </ul>
                </section>
            </nav>
        </aside>

        <div class="dashboard-workspace">
            <header class="dashboard-header">
                <button
                    class="sidebar-toggle"
                    type="button"
                    aria-label="Toggle navigation"
                    aria-controls="dashboard-sidebar"
                    aria-expanded="false"
                    data-sidebar-toggle
                >
                    <i class="bi bi-list" aria-hidden="true"></i>
                </button>

                <img
                    class="header-logo"
                    src="<?= base_url('assets/image/binan.png') ?>"
                    alt="City of Binan Logo"
                >

                <div>
                    <h1 id="dashboard-page-title"><?= esc($pageTitle ?? 'Dashboard') ?></h1>
                    <p>Binan Access Card MIS</p>
                </div>
            </header>

            <main
                class="dashboard-content"
                id="dashboard-content"
                data-workspace-url="<?= esc($workspaceUrl, 'attr') ?>"
            ></main>
        </div>
    </div>
    <?= dashboard_scripts(! $isEmployeeDashboard) ?>
</body>
</html>
