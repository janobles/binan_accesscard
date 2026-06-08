<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($pageTitle ?? 'Dashboard') ?> | Binan Access Card Portal</title>
    <?= bootstrap_styles() ?>
    <?= stylesheet_tag('css/dashboard.css', true) ?>
    <?= stylesheet_tag('css/mainlayout.css', true) ?>
    <?= stylesheet_tag('css/managerecord.css', true) ?>
    <?= stylesheet_tag('css/searchbar.css', true) ?>
    <?= stylesheet_tag('css/sector.css', true) ?>
    <?= stylesheet_tag('css/service.css', true) ?>
    <?= stylesheet_tag('css/audittrails.css', true) ?>
</head>
<body>
    <?php $currentPage = $activePage ?? 'dashboard'; ?>

    <div class="dashboard-shell">
        <aside class="dashboard-sidebar">
            <a class="sidebar-brand" href="<?= site_url('admin/dashboard') ?>">
                <img
                    class="sidebar-logo"
                    src="<?= base_url('assets/image/binan.png') ?>"
                    alt="City of Binan Logo"
                >
                <span class="sidebar-brand-text">Binan AccessCard MIS</span>
            </a>

            <nav aria-label="Admin navigation">
                <section class="sidebar-section">
                    <h2 class="sidebar-heading">Overview</h2>
                    <ul class="nav nav-pills flex-column">
                        <li class="nav-item">
                            <a
                                class="nav-link<?= $currentPage === 'dashboard' ? ' active' : '' ?>"
                                href="<?= site_url('admin/dashboard') ?>"
                                data-workspace-link
                                data-page-title="Dashboard"
                            >
                                <i class="bi bi-speedometer2" aria-hidden="true"></i>
                                <span>Dashboard</span>
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
                                href="<?= site_url('admin/manage-records') ?>"
                                data-workspace-link
                                data-page-title="Manage Records"
                            >
                                <i class="bi bi-people" aria-hidden="true"></i>
                                <span>Manage Records</span>
                            </a>
                        </li>
                    </ul>
                </section>

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

                <section class="sidebar-section">
                    <h2 class="sidebar-heading">Administration</h2>
                    <ul class="nav nav-pills flex-column">
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
                        <li class="nav-item">
                            <a
                                class="nav-link<?= $currentPage === 'audit-trails' ? ' active' : '' ?>"
                                href="<?= site_url('admin/audit-trails') ?>"
                                data-workspace-link
                                data-page-title="Audit Trails"
                            >
                                <i class="bi bi-clock-history" aria-hidden="true"></i>
                                <span>Audit Trails</span>
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
                data-workspace-url="<?= esc($workspaceUrl ?? site_url($currentPage === 'family-manage' ? 'admin/manage-records' : 'admin/dashboard'), 'attr') ?>"
            ></main>
        </div>
    </div>
    <?= bootstrap_scripts() ?>
    <?= script_tag('assets/js/dashboard.js', true) ?>
    <?= script_tag('assets/js/search.js', true) ?>
    <?= script_tag('assets/js/familymodal.js', true) ?>
    <?= script_tag('assets/js/sector_service_modal.js', true) ?>
</body>
</html>
