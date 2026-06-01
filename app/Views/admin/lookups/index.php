<?php
/**
 * Lookup Management screen (Sectors & Services reference data).
 *
 * Rendered by App\Controllers\Admin\SectorController and ServicesController,
 * both of which build their data via App\Controllers\Concerns\LookupManagementTrait
 * (buildLookupViewData). Extends layouts/admin_layout and is driven by
 * assets/js/lookups.js, which handles the add/edit/archive/restore modals and
 * posts to the admin/lookups/{sectors,services}/* routes referenced below.
 *
 * Expected data: $activeTab, $activeSectors, $archivedSectors,
 * $sectorAssignmentCounts, $serviceGroups (category => [active, archived]),
 * $serviceAssignmentCounts, $serviceCategories.
 */
?>
<?= $this->extend('layouts/admin_layout') ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/lookups.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/lookups.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-3">
    <div>
        <h2 class="mb-1">Lookup Management</h2>
        <div class="text-muted">Sectors &amp; Services</div>
    </div>
</div>

<ul class="nav nav-tabs" id="lookupTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeTab === 'sectors' ? 'active' : '' ?>" id="sectors-tab" data-bs-toggle="tab" data-bs-target="#tabSectors" type="button" role="tab" aria-controls="tabSectors" aria-selected="<?= $activeTab === 'sectors' ? 'true' : 'false' ?>">Sectors</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeTab === 'services' ? 'active' : '' ?>" id="services-tab" data-bs-toggle="tab" data-bs-target="#tabServices" type="button" role="tab" aria-controls="tabServices" aria-selected="<?= $activeTab === 'services' ? 'true' : 'false' ?>">Services</button>
    </li>
</ul>

<div class="tab-content pt-3" id="lookupTabsContent">
    <?php /* SECTORS tab: active sectors as cards (badge colour keyed off the
             shortcode prefix PWD/SP/OSCA) + a collapsible archived list. */ ?>
    <div class="tab-pane fade <?= $activeTab === 'sectors' ? 'show active' : '' ?>" id="tabSectors" role="tabpanel" aria-labelledby="sectors-tab">
        <div class="d-flex justify-content-end mb-3">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalSectorAdd"><i class="bi bi-plus-lg" aria-hidden="true"></i>Add New Sector</button>
        </div>

        <div class="row g-3 sector-card-grid">
            <?php foreach ($activeSectors as $sector): ?>
                <?php
                $shortcode = (string) ($sector['shortcode'] ?? '');
                $shortcodeUpper = strtoupper(trim($shortcode));
                $badgeClass = str_starts_with($shortcodeUpper, 'PWD')
                    ? 'badge-pwd'
                    : (str_starts_with($shortcodeUpper, 'SP') ? 'badge-sp' : 'badge-osca');
                $sectorId = (int) ($sector['sectorID'] ?? 0);
                $assignmentCount = (int) ($sectorAssignmentCounts[$sectorId] ?? 0);
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 sector-card" data-sector-id="<?= esc((string) $sectorId) ?>" data-shortcode="<?= esc($shortcode) ?>" data-name="<?= esc((string) ($sector['name'] ?? '')) ?>" data-description="<?= esc((string) ($sector['description'] ?? '')) ?>" data-assignments="<?= esc((string) $assignmentCount) ?>">
                        <div class="card-header d-flex align-items-center justify-content-between">
                            <span class="badge rounded-pill <?= esc($badgeClass) ?>"><?= esc($shortcodeUpper) ?></span>
                        </div>
                        <div class="card-body">
                            <div class="fw-semibold mb-1"><?= esc((string) ($sector['name'] ?? '')) ?></div>
                            <div class="text-muted small"><?= esc((string) ($sector['description'] ?? '')) ?></div>
                        </div>
                        <div class="card-footer d-flex justify-content-end gap-2">
                            <button type="button" class="icon-btn js-sector-edit" aria-label="Edit sector" data-bs-toggle="modal" data-bs-target="#modalSectorEdit">
                                <i class="bi bi-pencil" aria-hidden="true"></i>
                            </button>
                            <button type="button" class="icon-btn js-sector-archive" aria-label="Archive sector" data-archive-url="<?= site_url('admin/lookups/sectors/archive/' . $sectorId) ?>">
                                <i class="bi bi-archive" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if ($activeSectors === []): ?>
                <div class="col-12">
                    <div class="text-muted">No active sectors found.</div>
                </div>
            <?php endif; ?>
        </div>

        <div class="accordion mt-4" id="archivedSectorsAccordion">
            <div class="accordion-item">
                <h2 class="accordion-header" id="archivedSectorsHeading">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#archivedSectorsCollapse" aria-expanded="false" aria-controls="archivedSectorsCollapse">
                        Archived Sectors (<?= esc((string) count($archivedSectors)) ?>)
                    </button>
                </h2>
                <div id="archivedSectorsCollapse" class="accordion-collapse collapse" aria-labelledby="archivedSectorsHeading" data-bs-parent="#archivedSectorsAccordion">
                    <div class="accordion-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Shortcode</th>
                                        <th>Name</th>
                                        <th>Archived On</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($archivedSectors as $sector): ?>
                                        <?php
                                        $sectorId = (int) ($sector['sectorID'] ?? 0);
                                        $archivedOn = (string) ($sector['dt_deleted'] ?? '');
                                        ?>
                                        <tr class="archived-row" data-sector-id="<?= esc((string) $sectorId) ?>">
                                            <td><?= esc((string) ($sector['shortcode'] ?? '')) ?></td>
                                            <td><?= esc((string) ($sector['name'] ?? '')) ?></td>
                                            <td class="text-muted small"><?= $archivedOn !== '' ? esc(date('M j, Y g:i A', strtotime($archivedOn))) : '-' ?></td>
                                            <td class="text-end">
                                                <button type="button" class="icon-btn js-sector-restore" aria-label="Restore sector" data-restore-url="<?= site_url('admin/lookups/sectors/restore/' . $sectorId) ?>" data-name="<?= esc((string) ($sector['name'] ?? '')) ?>">
                                                    <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if ($archivedSectors === []): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">No archived sectors.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php /* SERVICES tab: one card per category; each card has an active table
             plus a hidden "archived-rows" tbody toggled by js-toggle-archived. */ ?>
    <div class="tab-pane fade <?= $activeTab === 'services' ? 'show active' : '' ?>" id="tabServices" role="tabpanel" aria-labelledby="services-tab">
        <div class="d-flex justify-content-end mb-3">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalServiceAdd"><i class="bi bi-plus-lg" aria-hidden="true"></i>Add New Service</button>
        </div>

        <?php foreach ($serviceGroups as $category => $group): ?>
            <?php
            $activeServices = $group['active'] ?? [];
            $archivedServices = $group['archived'] ?? [];
            $archivedCount = count($archivedServices);
            $categorySlug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $category));
            ?>
            <div class="card mb-3" data-service-category="<?= esc((string) $category) ?>">
                <div class="card-header d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
                    <strong><?= esc((string) $category) ?></strong>
                    <div class="d-flex align-items-center gap-3">
                        <?php if ($archivedCount > 0): ?>
                            <button type="button" class="btn btn-link p-0 js-toggle-archived" data-target="<?= esc($categorySlug) ?>" data-count="<?= esc((string) $archivedCount) ?>">Show archived (<?= esc((string) $archivedCount) ?>)</button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-outline-primary btn-sm js-service-add-category" data-bs-toggle="modal" data-bs-target="#modalServiceAdd" data-category="<?= esc((string) $category) ?>"><i class="bi bi-plus-lg" aria-hidden="true"></i>Add to <?= esc((string) $category) ?></button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="lookup-id-column">ID</th>
                                <th>Name</th>
                                <th>Description</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activeServices as $service): ?>
                                <?php
                                $serviceId = (int) ($service['serviceID'] ?? 0);
                                $assignmentCount = (int) ($serviceAssignmentCounts[$serviceId] ?? 0);
                                ?>
                                <tr data-service-id="<?= esc((string) $serviceId) ?>" data-category="<?= esc((string) ($service['category'] ?? '')) ?>" data-name="<?= esc((string) ($service['name'] ?? '')) ?>" data-description="<?= esc((string) ($service['description'] ?? '')) ?>" data-assignments="<?= esc((string) $assignmentCount) ?>">
                                    <td><?= esc((string) $serviceId) ?></td>
                                    <td><?= esc((string) ($service['name'] ?? '')) ?></td>
                                    <td><?= esc((string) ($service['description'] ?? '')) ?></td>
                                    <td class="text-end">
                                        <button type="button" class="icon-btn js-service-edit" aria-label="Edit service" data-bs-toggle="modal" data-bs-target="#modalServiceEdit">
                                            <i class="bi bi-pencil" aria-hidden="true"></i>
                                        </button>
                                        <button type="button" class="icon-btn js-service-archive" aria-label="Archive service" data-archive-url="<?= site_url('admin/lookups/services/archive/' . $serviceId) ?>">
                                            <i class="bi bi-archive" aria-hidden="true"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($activeServices === []): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted">No active services found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                        <tbody class="archived-rows d-none" data-archived-body="<?= esc($categorySlug) ?>">
                            <?php foreach ($archivedServices as $service): ?>
                                <?php
                                $serviceId = (int) ($service['serviceID'] ?? 0);
                                $archivedOn = (string) ($service['dt_deleted'] ?? '');
                                ?>
                                <tr class="archived-row" data-service-id="<?= esc((string) $serviceId) ?>" data-category="<?= esc((string) ($service['category'] ?? '')) ?>" data-name="<?= esc((string) ($service['name'] ?? '')) ?>" data-description="<?= esc((string) ($service['description'] ?? '')) ?>">
                                    <td><?= esc((string) $serviceId) ?></td>
                                    <td>
                                        <?= esc((string) ($service['name'] ?? '')) ?>
                                        <?php if ($archivedOn !== ''): ?>
                                            <div class="text-muted small">Archived <?= esc(date('M j, Y g:i A', strtotime($archivedOn))) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= esc((string) ($service['description'] ?? '')) ?></td>
                                    <td class="text-end">
                                        <button type="button" class="icon-btn js-service-restore" aria-label="Restore service" data-restore-url="<?= site_url('admin/lookups/services/restore/' . $serviceId) ?>" data-name="<?= esc((string) ($service['name'] ?? '')) ?>">
                                            <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if ($serviceGroups === []): ?>
            <div class="text-muted">No services found.</div>
        <?php endif; ?>
    </div>
</div>

<div class="toast-container" id="toastContainer"></div>

<?php /* Add/Edit modals for both lookups + a shared archive-confirm dialog.
         lookups.js fills the Edit forms from the clicked row's data-* attributes
         and rewrites each form's action to append the record id before submit. */ ?>
<div class="modal fade" id="modalSectorAdd" tabindex="-1" aria-labelledby="modalSectorAddLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="<?= site_url('admin/lookups/sectors/store') ?>" class="js-lookup-form" data-form-type="sector">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="modalSectorAddLabel">Add New Sector</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="sectorShortcodeAdd">Shortcode</label>
                        <input class="form-control" id="sectorShortcodeAdd" name="shortcode" maxlength="20" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="sectorNameAdd">Name</label>
                        <input class="form-control" id="sectorNameAdd" name="name" maxlength="255" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="sectorDescriptionAdd">Description</label>
                        <textarea class="form-control" id="sectorDescriptionAdd" name="description" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Sector</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalSectorEdit" tabindex="-1" aria-labelledby="modalSectorEditLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="<?= site_url('admin/lookups/sectors/update/0') ?>" class="js-lookup-form" data-form-type="sector" data-base-action="<?= site_url('admin/lookups/sectors/update') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="sectorID" id="sectorIdEdit">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalSectorEditLabel">Edit Sector</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="sectorShortcodeEdit">Shortcode</label>
                        <input class="form-control" id="sectorShortcodeEdit" name="shortcode" maxlength="20" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="sectorNameEdit">Name</label>
                        <input class="form-control" id="sectorNameEdit" name="name" maxlength="255" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="sectorDescriptionEdit">Description</label>
                        <textarea class="form-control" id="sectorDescriptionEdit" name="description" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Sector</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalServiceAdd" tabindex="-1" aria-labelledby="modalServiceAddLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="<?= site_url('admin/lookups/services/store') ?>" class="js-lookup-form" data-form-type="service">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="modalServiceAddLabel">Add New Service</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="serviceCategoryAdd">Category</label>
                        <input class="form-control" id="serviceCategoryAdd" name="category" list="serviceCategoryList" maxlength="50" required>
                        <datalist id="serviceCategoryList">
                            <?php foreach ($serviceCategories as $category): ?>
                                <option value="<?= esc((string) $category) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="serviceNameAdd">Name</label>
                        <input class="form-control" id="serviceNameAdd" name="name" maxlength="255" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="serviceDescriptionAdd">Description</label>
                        <textarea class="form-control" id="serviceDescriptionAdd" name="description" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Service</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalServiceEdit" tabindex="-1" aria-labelledby="modalServiceEditLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="<?= site_url('admin/lookups/services/update/0') ?>" class="js-lookup-form" data-form-type="service" data-base-action="<?= site_url('admin/lookups/services/update') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="serviceID" id="serviceIdEdit">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalServiceEditLabel">Edit Service</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="serviceCategoryEdit">Category</label>
                        <input class="form-control" id="serviceCategoryEdit" name="category" maxlength="50" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="serviceNameEdit">Name</label>
                        <input class="form-control" id="serviceNameEdit" name="name" maxlength="255" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="serviceDescriptionEdit">Description</label>
                        <textarea class="form-control" id="serviceDescriptionEdit" name="description" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Service</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalArchiveConfirm" tabindex="-1" aria-labelledby="modalArchiveConfirmLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalArchiveConfirmLabel">Confirm Archive</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2" id="archiveConfirmMessage"></p>
                <div class="text-danger small" id="archiveConfirmWarning"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="archiveConfirmButton">Archive</button>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/js/lookups.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/lookups.js') ?>"></script>
<?= $this->endSection() ?>
