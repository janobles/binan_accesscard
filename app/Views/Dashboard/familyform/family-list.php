<?php $isFullPage = $isFullPage ?? false; ?>
<div class="panel mb-3">
    <div class="section-title mt-0">
        <span><?= $status === 'archived' ? 'Archived Members' : 'Manage Member' ?></span>
        <?php if ($status !== 'archived'): ?>
            <button type="button" class="btn btn-primary btn-sm js-open-family-modal" data-modal-url="<?= site_url($routeBase . '?partial=1') ?>" data-modal-title="Add Record">Add Record</button>
        <?php endif; ?>
    </div>
    <?php if (! $isEmployeeList && $canRestoreArchived): ?>
        <div class="d-flex gap-2 mb-3">
            <a
                class="btn btn-sm <?= $status === 'active' ? 'btn-primary' : 'btn-outline-secondary' ?> <?= $isFullPage ? '' : 'js-open-family-list' ?>"
                href="<?= esc($listUrl('active'), 'attr') ?>"
                data-modal-url="<?= esc($listUrl('active') . '&partial=1', 'attr') ?>"
                data-modal-title="Manage Families">
                Active
            </a>
            <a
                class="btn btn-sm <?= $status === 'archived' ? 'btn-primary' : 'btn-outline-secondary' ?> <?= $isFullPage ? '' : 'js-open-family-list' ?>"
                href="<?= esc($listUrl('archived'), 'attr') ?>"
                data-modal-url="<?= esc($listUrl('archived') . '&partial=1', 'attr') ?>"
                data-modal-title="Archived Families">
                Archived
            </a>
        </div>
    <?php endif; ?>

    <form method="get" class="row g-2 mb-3" action="<?= $isFullPage ? site_url('admin/manage-members') : site_url($routeBase . '/list') ?>">
        <?php if ($status === 'archived'): ?>
            <input type="hidden" name="status" value="archived">
        <?php endif; ?>
        <div class="col-md-9">
            <input class="form-control" type="search" name="q" value="<?= esc((string) $keyword) ?>" placeholder="Search by head name or sector">
        </div>
        <div class="col-md-3 d-grid">
            <button class="btn btn-outline-secondary" type="submit">Search</button>
        </div>
    </form>

    <div class="d-flex justify-content-between align-items-center mb-2 text-muted small">
        <span><?= esc((string) $fromRecord) ?>-<?= esc((string) $toRecord) ?> of <?= esc((string) $totalFamilies) ?> families</span>
        <span>Page <?= esc((string) $page) ?> of <?= esc((string) $totalPages) ?></span>
    </div>

    <div class="table-responsive">
        <table class="table table-sm family-list-table align-middle">
            <thead>
                <tr>
                    <th>Head of Family</th>
                    <th>Sector</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($families as $family): ?>
                <?php
                $headId            = (int) ($family['memberID'] ?? 0);
                $dateValue         = $status === 'archived' ? ($family['dt_deleted'] ?? '') : ($family['dt_created'] ?? '');
                $recordAction      = $status === 'archived' ? 'restore' : ($isEmployeeList ? 'delete' : 'archive');
                $recordActionLabel = $status === 'archived' ? 'Restore' : ($isEmployeeList ? 'Delete' : 'Archive');
                $recordActionPast  = $status === 'archived' ? 'restored' : ($isEmployeeList ? 'deleted' : 'archived');
                $confirmMessage    = $status === 'archived'
                    ? 'Restore this family record to the active list?'
                    : $recordActionLabel . ' this family record? This keeps the record in the database, marks it as ' . $recordActionPast . ', and hides it from active lists.';
                ?>
                <tr>
                    <td><?= esc((string) (($family['firstname'] ?? '') . ' ' . ($family['lastname'] ?? ''))) ?></td>
                    <td><?= esc((string) ($family['sector_name'] ?? '')) ?></td>
                    <td><?= esc($formatDate($dateValue)) ?></td>
                    <td><?= esc($formatTime($dateValue)) ?></td>
                    <td class="text-end">
                        <div class="family-list-actions">
                        <?php if ($status !== 'archived'): ?>
                        <button
                            type="button"
                            class="btn btn-outline-primary btn-sm js-open-family-view-modal"
                            data-modal-url="<?= site_url($routeBase . '/view/' . $headId . '?partial=1') ?>"
                            data-modal-title="View Family">
                            View
                        </button>
                        <button
                            type="button"
                            class="btn btn-primary btn-sm js-open-family-edit-modal"
                            data-modal-url="<?= site_url($routeBase . '/edit/' . $headId . '?partial=1') ?>"
                            data-modal-title="Edit Family">
                            Edit
                        </button>
                        <?php endif; ?>
                        <form class="d-inline js-family-record-action-form" method="post" action="<?= site_url($routeBase . '/' . $recordAction . '/' . $headId) ?>" data-confirm-message="<?= esc($confirmMessage, 'attr') ?>" data-action-label="<?= esc($recordActionLabel, 'attr') ?>" data-action-past="<?= esc($recordActionPast, 'attr') ?>" data-family-name="<?= esc((string) (($family['firstname'] ?? '') . ' ' . ($family['lastname'] ?? '')), 'attr') ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn <?= $status === 'archived' ? 'btn-outline-success' : 'btn-outline-danger' ?> btn-sm"><?= esc($recordActionLabel) ?></button>
                        </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($families === []): ?>
                <tr><td colspan="5" class="text-center text-muted"><?= $status === 'archived' ? 'No archived family records found.' : 'No family records found.' ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="d-flex justify-content-end gap-2 mt-3">
            <a
                class="btn btn-outline-secondary btn-sm <?= $isFullPage ? '' : 'js-open-family-list' ?> <?= $page <= 1 ? 'disabled' : '' ?>"
                href="<?= esc($listUrl($status, max(1, $page - 1)), 'attr') ?>"
                data-modal-url="<?= esc($listUrl($status, max(1, $page - 1)) . '&partial=1', 'attr') ?>"
                data-modal-title="Manage Families"
                aria-disabled="<?= $page <= 1 ? 'true' : 'false' ?>">
                Previous
            </a>
            <a
                class="btn btn-outline-secondary btn-sm <?= $isFullPage ? '' : 'js-open-family-list' ?> <?= $page >= $totalPages ? 'disabled' : '' ?>"
                href="<?= esc($listUrl($status, min($totalPages, $page + 1)), 'attr') ?>"
                data-modal-url="<?= esc($listUrl($status, min($totalPages, $page + 1)) . '&partial=1', 'attr') ?>"
                data-modal-title="Manage Families"
                aria-disabled="<?= $page >= $totalPages ? 'true' : 'false' ?>">
                Next
            </a>
        </div>
    <?php endif; ?>
</div>
