<?php
$recentAudits = $recentAudits ?? [];
?>

<div class="panel">
    <div class="section-title mt-0"><span>Audit Trails</span></div>
    <div class="table-responsive">
        <table class="table table-sm">
            <thead><tr><th>User</th><th>Action</th><th>Description</th><th>Date</th></tr></thead>
            <tbody>
                <?php foreach ($recentAudits as $audit): ?>
                    <tr>
                        <td><?= esc((string) ($audit['username'] ?? $audit['userID'] ?? '')) ?></td>
                        <td><?= esc((string) ($audit['user_action'] ?? '')) ?></td>
                        <td><?= esc((string) ($audit['description'] ?? '')) ?></td>
                        <td><?= esc((string) ($audit['dt_created'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($recentAudits === []): ?>
                    <tr><td colspan="4" class="text-center text-muted">No audit logs yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
