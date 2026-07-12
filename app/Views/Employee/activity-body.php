<?php
/**
 * Employee "My Activity" body: search/filter rows + activity table.
 * Rendered inside components/card by Employee/layout.php (activity page) —
 * vars: listRoute, searchTerm, auditAction, auditActionOptions, perPage,
 * perPageOptions, myAudits, hasSearchFilters, formatAuditMember, auditClearUrl.
 */
?>
<div class="records-search-panel">
                            <form class="records-search-row records-lookup-search" method="get" action="<?= esc(site_url($listRoute), 'attr') ?>" role="search" aria-label="Search my activity" data-records-filter-form data-records-pills="activityFilterPills">
                                <input class="form-control" type="search" name="q" value="<?= esc($searchTerm, 'attr') ?>" placeholder="Search entire database..." aria-label="Search my activity" autocomplete="off">
                                <div class="dropdown" data-records-panel>
                                    <button class="<?= btn('filter') ?> dropdown-toggle w-100" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                                        <i class="bi bi-funnel" aria-hidden="true"></i> Filters
                                    </button>
                                    <div class="dropdown-menu records-filter-panel p-3">
                                        <input class="form-control form-control-sm mb-2" type="search" placeholder="Search filters..." aria-label="Search filter options" data-records-narrow>
                                        <div data-records-filter="action" data-records-group-label="Action">
                                            <div class="fw-semibold small text-uppercase text-muted mb-1">Action</div>
                                            <div class="records-filter-list overflow-auto">
                                                <label class="form-check d-flex align-items-center gap-2 py-1" data-records-option>
                                                    <input class="form-check-input m-0" type="radio" name="action" value="" data-records-default <?= $auditAction === '' ? 'checked' : '' ?>>
                                                    <span class="form-check-label small">All actions</span>
                                                </label>
                                                <?php foreach ($auditActionOptions as $action): ?>
                                                    <?php $action = trim((string) $action); ?>
                                                    <label class="form-check d-flex align-items-center gap-2 py-1" data-records-option>
                                                        <input class="form-check-input m-0" type="radio" name="action" value="<?= esc($action, 'attr') ?>" data-records-pill-label="<?= esc($action, 'attr') ?>" <?= $auditAction === $action ? 'checked' : '' ?>>
                                                        <span class="form-check-label text-wrap small"><?= esc($action) ?></span>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php if ($perPage !== 50): ?><input type="hidden" name="per_page" value="<?= esc((string) $perPage, 'attr') ?>"><?php endif; ?>
                                <button class="<?= btn('search') ?> records-search-action" type="submit"><i class="bi bi-search" aria-hidden="true"></i><span>Search</span></button>
                                <a class="<?= btn('clear') ?> records-search-action" href="<?= esc($auditClearUrl(), 'attr') ?>"><i class="bi bi-x-lg" aria-hidden="true"></i><span>Clear</span></a>
                            </form>
                            <?= view('components/filter_pills', ['id' => 'activityFilterPills']) ?>
                        </div>

                        <div class="table-meta">
                            <div class="records-table-controls">
                                <form class="records-page-size-form" method="get" action="<?= esc(site_url($listRoute), 'attr') ?>">
                                    <?php if ($searchTerm !== ''): ?><input type="hidden" name="q" value="<?= esc($searchTerm, 'attr') ?>"><?php endif; ?>
                                    <?php if ($auditAction !== ''): ?><input type="hidden" name="action" value="<?= esc($auditAction, 'attr') ?>"><?php endif; ?>
                                    <label for="activityPerPage">Show</label>
                                    <select class="form-select form-select-sm" id="activityPerPage" name="per_page" onchange="this.form.submit()">
                                        <?php foreach ($perPageOptions as $option): ?>
                                            <option value="<?= esc((string) $option, 'attr') ?>" <?= $perPage === (int) $option ? 'selected' : '' ?>><?= esc((string) $option) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <span>entries</span>
                                </form>
                                <form class="records-table-search-form" role="search" data-lookup-search aria-label="Filter shown activity">
                                    <label for="activityLocalSearch">Search:</label>
                                    <input class="form-control form-control-sm" type="search" id="activityLocalSearch" data-lookup-search-input placeholder="Filter loaded results..." autocomplete="off" aria-label="Filter shown activity">
                                </form>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead><tr><th>Action</th><th>Member</th><th>Description</th></tr></thead>
                                <tbody>
                                    <?php foreach ($myAudits as $audit): ?>
                                        <tr>
                                            <td><span class="status-pill is-muted"><?= esc((string) ($audit['user_action'] ?? '')) ?></span></td>
                                            <td><?= esc(isset($formatAuditMember) ? $formatAuditMember($audit) : '') ?></td>
                                            <td><?= esc((string) ($audit['description'] ?? '')) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if ($myAudits === []): ?>
                                        <tr><td colspan="3" class="text-center text-muted audit-empty-state"><?= $hasSearchFilters ? 'No matching activity found.' : 'No activity yet.' ?></td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
