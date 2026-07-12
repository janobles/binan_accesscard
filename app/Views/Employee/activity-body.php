<?php
/**
 * Employee "My Activity" body: search/filter rows + activity table.
 * Rendered inside components/card by Employee/layout.php (activity page) —
 * vars: listRoute, searchTerm, auditAction, auditActionOptions, perPage,
 * perPageOptions, myAudits, hasSearchFilters, formatAuditMember, auditClearUrl.
 */
?>
<?php /* Search toolbar lives in Employee/layout.php, above this card (Manage Records standard). */ ?>
                        <?php /* Controls row, Manage Records standard: page search left, show-entries right. */ ?>
                        <div class="table-meta">
                            <div class="records-table-controls">
                                <form class="records-table-search-form" role="search" data-lookup-search aria-label="Search shown activity">
                                    <div class="input-group input-group-sm">
                                        <input class="form-control" type="search" id="activityLocalSearch" data-lookup-search-input placeholder="Search this page..." autocomplete="off" aria-label="Search this page">
                                        <button class="btn btn-primary" type="submit" aria-label="Search this page"><i class="bi bi-search" aria-hidden="true"></i></button>
                                    </div>
                                </form>
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
