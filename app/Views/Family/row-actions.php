<?php
/**
 * Per-row Actions dropdown for the Manage Records DataTable. Server-side rendered:
 * returned as the `actions` cell by FamilyController::dataTableActions(). This holds
 * the modal "callers" — the VIEW/UPDATE trigger buttons and the archive/restore
 * confirm form — so they live in the view layer, not the controller. The controller
 * passes pre-computed permission flags + URLs; this template only renders markup.
 *
 * Expected vars:
 *   bool   $archived, $canEdit, $canArchive
 *   string $viewUrl, $updateUrl, $formAction, $actionLabel, $actionPast,
 *          $confirmMessage, $displayName
 */
?>
<div class="dropdown actions-menu">
    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" data-bs-strategy="fixed" aria-expanded="false" aria-label="Record actions">Actions</button>
    <div class="dropdown-menu dropdown-menu-end">
        <?php if (! $archived): ?>
        <button type="button" class="dropdown-item js-open-family-view-modal" data-modal-url="<?= esc($viewUrl, 'attr') ?>" data-modal-title="View Record">VIEW</button>
            <?php if ($canEdit): ?>
        <button type="button" class="dropdown-item js-open-family-add-modal" data-modal-url="<?= esc($updateUrl, 'attr') ?>" data-modal-title="Update Family Record">UPDATE</button>
            <?php endif; ?>
        <?php endif; ?>
        <?php if ($canArchive): ?>
        <form class="js-family-record-action-form" method="post" action="<?= esc($formAction, 'attr') ?>" data-confirm-message="<?= esc($confirmMessage, 'attr') ?>" data-action-label="<?= esc($actionLabel, 'attr') ?>" data-action-past="<?= esc($actionPast, 'attr') ?>" data-family-name="<?= esc($displayName, 'attr') ?>">
            <?= csrf_field() ?>
            <button type="submit" class="dropdown-item <?= $archived ? 'text-success' : 'text-danger' ?>"><?= esc(mb_strtoupper($actionLabel)) ?></button>
        </form>
        <?php endif; ?>
    </div>
</div>
