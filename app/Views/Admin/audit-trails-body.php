<?php
/**
 * Audit trails body: dual search bars + audit table.
 * Rendered inside components/card by Admin/audit-trails.php — see that file
 * for the variable contract (listRoute, searchTerm, auditAction,
 * auditActionOptions, perPage, perPageOptions, recentAudits,
 * hasSearchFilters, formatAuditUser, auditClearUrl).
 */
?>
    <?php /* Bar 1 (database search) lives in audit-trails.php, above this card (Manage Records standard). */ ?>
    <?php /* Controls row, Manage Records standard: page search left, show-entries right. */ ?>
    <div class="table-meta">
        <div class="records-table-controls">
            <form class="records-table-search-form" role="search" data-lookup-search aria-label="Search shown audit logs">
                <div class="input-group input-group-sm">
                    <input class="form-control" type="search" id="auditLocalSearch" data-lookup-search-input placeholder="Search this page..." autocomplete="off" aria-label="Search this page">
                    <button class="btn btn-primary" type="submit" aria-label="Search this page"><i class="bi bi-search" aria-hidden="true"></i></button>
                </div>
            </form>
            <form class="records-page-size-form" method="get" action="<?= esc(site_url($listRoute), 'attr') ?>">
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

    
