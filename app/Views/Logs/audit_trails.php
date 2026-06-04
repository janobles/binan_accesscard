<section class="audit-trails" aria-label="Audit trails">
    <div class="table-responsive">
        <table class="table audit-trails-table align-middle">
            <thead>
                <tr>
                    <th scope="col">User</th>
                    <th scope="col">Member</th>
                    <th scope="col">Action</th>
                    <th scope="col">Description</th>
                </tr>
            </thead>
            <tbody>
                <?php if (($auditTrails ?? []) === []): ?>
                    <tr>
                        <td class="audit-trails-empty" colspan="4">No audit trails yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($auditTrails as $audit): ?>
                        <tr>
                            <td><?= esc($audit['display_username']) ?></td>
                            <td><?= esc($audit['display_member']) ?></td>
                            <td><?= esc($audit['display_action']) ?></td>
                            <td><?= esc($audit['display_description']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
