<?php
helper('dashboard_view');
extract(family_list_view_data(get_defined_vars()), EXTR_OVERWRITE);
?>

<div class="panel mb-3">
    <div class="section-title mt-0">
        <span>Manage Families</span>
        <button type="button" class="btn btn-primary btn-sm js-open-family-modal" data-modal-url="<?= site_url($routeBase . '?partial=1') ?>" data-modal-title="Add Family">Add Family</button>
    </div>

    <form method="get" class="row g-2 mb-3" action="<?= site_url($routeBase . '/list') ?>">
        <div class="col-md-9">
            <input class="form-control" type="search" name="q" value="<?= esc((string) $keyword) ?>" placeholder="Search by head name or sector">
        </div>
        <div class="col-md-3 d-grid">
            <button class="btn btn-outline-secondary" type="submit">Search</button>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-sm">
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
                <?php $headId = (int) ($family['memberID'] ?? 0); ?>
                <tr>
                    <td><?= esc((string) (($family['firstname'] ?? '') . ' ' . ($family['lastname'] ?? ''))) ?></td>
                    <td><?= esc((string) ($family['sector_name'] ?? '')) ?></td>
                    <td><?= esc($formatDate($family['dt_created'] ?? '')) ?></td>
                    <td><?= esc($formatTime($family['dt_created'] ?? '')) ?></td>
                    <td class="text-end">
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
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($families === []): ?>
                <tr><td colspan="5" class="text-center text-muted">No family records found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
