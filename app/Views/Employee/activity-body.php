<?php
/**
 * Employee "My Activity" body: search/filter rows + activity table.
 * Rendered inside components/card by Employee/layout.php (activity page) —
 * vars: listRoute, searchTerm, auditAction, auditActionOptions, perPage,
 * perPageOptions, myAudits, hasSearchFilters, formatAuditMember, auditClearUrl.
 */
?>
<div class="records-search-panel">
                            <form class="records-search-row records-lookup-search js-audit-filter-form" method="get" action="<?= esc(site_url($listRoute), 'attr') ?>" role="search" aria-label="Search my activity">
                                <input class="form-control" type="search" name="q" value="<?= esc($searchTerm, 'attr') ?>" placeholder="Search my activity by action or description" aria-label="Search my activity" autocomplete="off">
                                <select class="form-select records-status-select js-audit-action-filter" name="action" aria-label="Filter by action">
                                    <option value="">All actions</option>
                                    <?php foreach ($auditActionOptions as $action): ?>
                                        <?php $action = trim((string) $action); ?>
                                        <option value="<?= esc($action) ?>" <?= $auditAction === $action ? 'selected' : '' ?>><?= esc($action) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($perPage !== 50): ?><input type="hidden" name="per_page" value="<?= esc((string) $perPage, 'attr') ?>"><?php endif; ?>
                                <a class="btn btn-danger records-search-action" href="<?= esc($auditClearUrl(), 'attr') ?>"><i class="bi bi-x-lg" aria-hidden="true"></i><span>Clear</span></a>
                                <button class="btn btn-primary records-search-action" type="submit"><i class="bi bi-search" aria-hidden="true"></i><span>Search</span></button>
                            </form>
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
                                    <input class="form-control form-control-sm" type="search" id="activityLocalSearch" data-lookup-search-input placeholder="Type to filter..." autocomplete="off" aria-label="Filter shown activity">
                                </form>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm">
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
