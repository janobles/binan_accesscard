<?php
$sectors = $sectors ?? [];
?>

<div class="panel mb-3">
    <div class="section-title mt-0">
        <span>Sector List</span>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary btn-sm js-sector-add-button">Add</button>
            <button type="button" class="btn btn-outline-primary btn-sm js-sector-update-button" disabled>Update</button>
            <button type="button" class="btn btn-outline-danger btn-sm js-sector-archive-button" disabled>Archive</button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead>
                <tr>
                    <th></th>
                    <th>ID</th>
                    <th>Shortcode</th>
                    <th>Name</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sectors as $sector): ?>
                    <?php $sectorId = (int) ($sector['sectorID'] ?? 0); ?>
                    <tr>
                        <td>
                            <input
                                class="form-check-input js-sector-select"
                                type="radio"
                                name="selected_sector"
                                aria-label="Select sector"
                                data-sector-id="<?= esc((string) $sectorId, 'attr') ?>"
                                data-shortcode="<?= esc((string) ($sector['shortcode'] ?? ''), 'attr') ?>"
                                data-name="<?= esc((string) ($sector['name'] ?? ''), 'attr') ?>"
                                data-description="<?= esc((string) ($sector['description'] ?? ''), 'attr') ?>">
                        </td>
                        <td><?= esc((string) $sectorId) ?></td>
                        <td><?= esc((string) ($sector['shortcode'] ?? '')) ?></td>
                        <td><?= esc((string) ($sector['name'] ?? '')) ?></td>
                        <td><?= esc((string) ($sector['description'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($sectors === []): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted">No sector records found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="sectorEditorModal" tabindex="-1" aria-labelledby="sectorEditorModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" method="post" data-create-action="<?= site_url('admin/sectors/create') ?>" data-update-action="<?= site_url('admin/sectors/update') ?>">
            <?= csrf_field() ?>
            <div class="modal-header">
                <h5 class="modal-title" id="sectorEditorModalLabel">Add Sector</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="sectorEditorShortcode">Shortcode</label>
                    <input class="form-control" id="sectorEditorShortcode" name="shortcode" maxlength="20" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="sectorEditorName">Name</label>
                    <input class="form-control" id="sectorEditorName" name="name" maxlength="150" required>
                </div>
                <div>
                    <label class="form-label" for="sectorEditorDescription">Description</label>
                    <textarea class="form-control" id="sectorEditorDescription" name="description" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary js-sector-editor-submit">Add</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="sectorArchiveModal" tabindex="-1" aria-labelledby="sectorArchiveModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" method="post" data-action-base="<?= site_url('admin/sectors/archive') ?>">
            <?= csrf_field() ?>
            <div class="modal-header">
                <h5 class="modal-title" id="sectorArchiveModalLabel">Archive Sector</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Archive <strong class="js-sector-archive-name">this sector</strong>?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger">Archive</button>
            </div>
        </form>
    </div>
</div>
