<?php
$sectors = $sectors ?? [];
?>

<div class="panel mb-3">
    <div class="section-title mt-0">
        <span>Sector List</span>
    </div>

    <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead>
                <tr>
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
                        <td><?= esc((string) $sectorId) ?></td>
                        <td><?= esc((string) ($sector['shortcode'] ?? '')) ?></td>
                        <td><?= esc((string) ($sector['name'] ?? '')) ?></td>
                        <td><?= esc((string) ($sector['description'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($sectors === []): ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted">No sector records found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
