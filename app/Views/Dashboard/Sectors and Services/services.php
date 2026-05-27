<?php
$services = $services ?? [];
?>

<div class="panel mb-3">
    <div class="section-title mt-0">
        <span>Service List</span>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary btn-sm js-service-add-button">Add</button>
            <button type="button" class="btn btn-outline-primary btn-sm js-service-update-button" disabled>Update</button>
            <button type="button" class="btn btn-outline-danger btn-sm js-service-archive-button" disabled>Archive</button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead>
                <tr>
                    <th></th>
                    <th>ID</th>
                    <th>Category</th>
                    <th>Name</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($services as $service): ?>
                    <?php $serviceId = (int) ($service['serviceID'] ?? 0); ?>
                    <tr>
                        <td>
                            <input
                                class="form-check-input js-service-select"
                                type="radio"
                                name="selected_service"
                                aria-label="Select service"
                                data-service-id="<?= esc((string) $serviceId, 'attr') ?>"
                                data-category="<?= esc((string) ($service['category'] ?? ''), 'attr') ?>"
                                data-name="<?= esc((string) ($service['name'] ?? ''), 'attr') ?>"
                                data-description="<?= esc((string) ($service['description'] ?? ''), 'attr') ?>">
                        </td>
                        <td><?= esc((string) $serviceId) ?></td>
                        <td><?= esc((string) ($service['category'] ?? '')) ?></td>
                        <td><?= esc((string) ($service['name'] ?? '')) ?></td>
                        <td><?= esc((string) ($service['description'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($services === []): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted">No service records found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="serviceEditorModal" tabindex="-1" aria-labelledby="serviceEditorModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" method="post" data-create-action="<?= site_url('admin/services/create') ?>" data-update-action="<?= site_url('admin/services/update') ?>">
            <?= csrf_field() ?>
            <div class="modal-header">
                <h5 class="modal-title" id="serviceEditorModalLabel">Add Service</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="serviceEditorCategory">Category</label>
                    <input class="form-control" id="serviceEditorCategory" name="category" maxlength="100" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="serviceEditorName">Name</label>
                    <input class="form-control" id="serviceEditorName" name="name" maxlength="150" required>
                </div>
                <div>
                    <label class="form-label" for="serviceEditorDescription">Description</label>
                    <textarea class="form-control" id="serviceEditorDescription" name="description" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary js-service-editor-submit">Add</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="serviceArchiveModal" tabindex="-1" aria-labelledby="serviceArchiveModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" method="post" data-action-base="<?= site_url('admin/services/archive') ?>">
            <?= csrf_field() ?>
            <div class="modal-header">
                <h5 class="modal-title" id="serviceArchiveModalLabel">Archive Service</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Archive <strong class="js-service-archive-name">this service</strong>?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger">Archive</button>
            </div>
        </form>
    </div>
</div>
