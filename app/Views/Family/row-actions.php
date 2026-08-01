<?php
/**
 * Per-row Actions dropdown for the Manage Records DataTable. Server-side rendered:
 * returned as the `actions` cell by FamilyController::dataTableActions(). This holds
 * the VIEW and EDIT navigation links and the archive/restore confirm form, so they
 * live in the view layer, not the controller. The controller passes pre-computed
 * permission flags + URLs; this template only renders markup.
 *
 * Expected vars:
 *   bool   $archived, $canEdit, $canArchive
 *   string $viewUrl, $updateUrl, $formAction, $actionLabel, $actionPast,
 *          $confirmMessage, $displayName
 */
?>
<div class="dropdown actions-menu">
    <button class="btn btn-outline-secondary btn-sm actions-menu-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" data-bs-strategy="fixed" aria-expanded="false" aria-label="Record actions">
        <i class="bi bi-three-dots" aria-hidden="true"></i>
    </button>
    <div class="dropdown-menu dropdown-menu-end">
        <?php if (! $archived): ?>
        <?php /* Plain navigations, not modal triggers: the target is a full page (the
                 Family Profile page), and loading a full page's response into the
                 shared modal is the bug records/entry used to have here. VIEW opens
                 the read-only profile, EDIT the form at `records/{id}/edit`. */ ?>
        <a class="dropdown-item" href="<?= esc($viewUrl, 'attr') ?>">VIEW</a>
            <?php if ($canEdit): ?>
        <a class="dropdown-item" href="<?= esc($updateUrl, 'attr') ?>">EDIT</a>
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
