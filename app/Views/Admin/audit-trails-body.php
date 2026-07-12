<?php
/**
 * Audit trails body: dual search bars + audit table.
 * Rendered inside components/card by Admin/audit-trails.php — see that file
 * for the variable contract (listRoute, searchTerm, auditAction,
 * auditActionOptions, perPage, perPageOptions, recentAudits,
 * hasSearchFilters, formatAuditUser, auditClearUrl).
 */
?>
    <?php /* Bar 1: search the whole audit database (server-side GET) + action filter. */ ?>
    <div class="records-search-panel">
        <form class="records-search-row records-lookup-search" method="get" action="<?= esc(site_url($listRoute), 'attr') ?>" role="search" aria-label="Search the audit database" data-records-filter-form data-records-pills="auditFilterPills">
            <input class="form-control" type="search" name="q" value="<?= esc($searchTerm, 'attr') ?>" placeholder="Search entire database..." aria-label="Search the audit database" autocomplete="off">
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
        <?= view('components/filter_pills', ['id' => 'auditFilterPills']) ?>
    </div>

    <?php /* Bar 2: full-width "search this page" local filter (client-side, no reload) + show-entries. */ ?>
    <div class="audit-table-toolbar">
        <form class="records-table-search-form audit-page-search-form" role="search" data-lookup-search aria-label="Filter shown audit logs">
            <input class="form-control audit-page-search" type="search" id="auditLocalSearch" data-lookup-search-input placeholder="Filter loaded results..." autocomplete="off" aria-label="Filter shown audit logs">
        </form>
        <form class="records-page-size-form audit-show-entries" method="get" action="<?= esc(site_url($listRoute), 'attr') ?>">
            <?php if ($searchTerm !== ''): ?><input type="hidden" name="q" value="<?= esc($searchTerm, 'attr') ?>"><?php endif; ?>
            <?php if ($auditAction !== ''): ?><input type="hidden" name="action" value="<?= esc($auditAction, 'attr') ?>"><?php endif; ?>
            <label for="auditPerPage">Show</label>
            <select class="form-select form-select-sm" id="auditPerPage" name="per_page" onchange="this.form.submit()">
                <?php foreach ($perPageOptions as $option): ?>
                    <option value="<?= esc((string) $option, 'attr') ?>" <?= $perPage === (int) $option ? 'selected' : '' ?>><?= esc((string) $option) ?></option>
                <?php endforeach; ?>
            </select>
            <span>entries</span>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table audit-trails-table align-middle">
            <thead>
                <tr>
                    <th scope="col">User</th>
                    <th scope="col">Action</th>
                    <th scope="col">Description</th>
                    <th scope="col">User Agent</th>
                    <th scope="col">Date/Time</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentAudits as $audit): ?>
                    <?php
                        $auditTs = strtotime((string) ($audit['dt_created'] ?? ''));
                        $auditUa = trim((string) ($audit['user_agent'] ?? ''));
                    ?>
                    <?php /* The whole row is the detail trigger (js-audit-detail) — audit-detail-modal.js
                             reads data-full and surfaces the narrative in that modal. */ ?>
                    <tr class="audit-row js-audit-detail" tabindex="0" role="button" aria-label="View audit log details"
                        data-full="<?= esc((string) ($audit['full_description'] ?? ''), 'attr') ?>">
                        <td class="audit-user"><?= esc($formatAuditUser($audit)) ?></td>
                        <td><span class="audit-action-pill"><?= esc((string) ($audit['user_action'] ?? '')) ?></span></td>
                        <td class="audit-desc"><?= esc((string) ($audit['description'] ?? '')) ?></td>
                        <td class="audit-ua"><?= $auditUa === '' ? '—' : esc($auditUa) ?></td>
                        <td class="audit-when"><?= $auditTs ? esc(date('M j, Y h:i A', $auditTs)) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($recentAudits === []): ?> 
                    <tr><td colspan="5" class="audit-trails-empty audit-empty-state"><?= $hasSearchFilters ? 'No matching audit logs found.' : 'No audit logs yet.' ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    
