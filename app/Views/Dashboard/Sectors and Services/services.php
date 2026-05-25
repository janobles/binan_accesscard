<?php
helper('dashboard_view');
extract(service_management_view_data(get_defined_vars()), EXTR_OVERWRITE);
?>

<div class="panel mb-3">
    <div class="section-title mt-0">
        <span>Service List</span>
    </div>

    <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead>
                <tr>
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
                        <td><?= esc((string) $serviceId) ?></td>
                        <td><?= esc((string) ($service['category'] ?? '')) ?></td>
                        <td><?= esc((string) ($service['name'] ?? '')) ?></td>
                        <td><?= esc((string) ($service['description'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($services === []): ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted">No service records found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
