<section class="manage-records" aria-label="Manage records">
    <header class="manage-records-toolbar">
        <div class="record-status-tabs" aria-label="Record status">
            <a
                class="btn <?= $status === 'active' ? 'btn-success' : 'btn-outline-secondary' ?>"
                href="<?= esc($activeUrl, 'attr') ?>"
                data-workspace-partial-link
            >
                <i class="bi bi-check2-circle" aria-hidden="true"></i>
                <span>Active</span>
            </a>
            <a
                class="btn <?= $status === 'archived' ? 'btn-success' : 'btn-outline-secondary' ?>"
                href="<?= esc($archivedUrl, 'attr') ?>"
                data-workspace-partial-link
            >
                <i class="bi bi-archive" aria-hidden="true"></i>
                <span>Archived</span>
            </a>
        </div>

       <a
    class="btn btn-success"
    href="<?= site_url('admin/family-record/new') ?>"
    data-workspace-partial-link
    aria-label="new record"
>
    <i class="bi bi-plus-lg" aria-hidden="true"></i>
    <span>New Record</span>
</a>
    </header>

    <?= view('components/searchbar', [
        'keyword' => $keyword,
        'sectorId' => $sectorId,
        'sectorOptions' => $sectorOptions,
        'status' => $status,
        'pageTitle' => 'Manage Records',
    ]) ?>

    <div class="record-table-meta">
        <span><?= esc((string) $fromRecord) ?>-<?= esc((string) $toRecord) ?> of <?= esc((string) $totalRecords) ?> records</span>
        <span>Page <?= esc((string) $page) ?> of <?= esc((string) $totalPages) ?></span>
    </div>

    <div class="table-responsive">
        <table class="table manage-record-table align-middle">
            <thead>
                <tr>
                    <th scope="col"><?= $searchScope === 'all' ? 'Name' : 'NAME' ?></th>
                    <th scope="col">Sector</th>
                    <th scope="col">Barangay</th>
                    <th scope="col">Birthday</th>
                    <th class="text-end" scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($records === []): ?>
                    <tr>
                        <td class="record-empty-state" colspan="5">
                            <?= $status === 'archived' ? 'No archived records found.' : 'No records found.' ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($records as $record): ?>
                        <tr>
                            <td class="record-name"><?= esc($record['display_name']) ?></td>
                            <td><?= esc($record['display_sector']) ?></td>
                            <td><?= esc($record['display_barangay']) ?></td>
                            <td><?= esc($record['display_birthday']) ?></td>
                            <td class="text-end">
                                <?php if ($status === 'archived'): ?>
                                    <button class="btn btn-outline-success record-restore-action" type="button">
                                        <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
                                        <span>Restore</span>
                                    </button>
                                <?php else: ?>
                                    <details class="record-action-menu">
                                        <summary class="btn btn-outline-secondary record-actions" aria-label="Record actions">
                                            <i class="bi bi-three-dots" aria-hidden="true"></i>
                                        </summary>

                                        <div class="record-action-options">
                                            <a href="#" role="menuitem">
                                                <i class="bi bi-eye" aria-hidden="true"></i>
                                                <span>View</span>
                                            </a>
                                            <a
                                                href="<?= site_url('admin/family-record/' . (int) ($record['memberID'] ?? 0) . '/edit') ?>"
                                                role="menuitem"
                                                data-workspace-partial-link
                                            >
                                                <i class="bi bi-pencil-square" aria-hidden="true"></i>
                                                <span>Edit</span>
                                            </a>
                                            <a class="record-action-danger" href="#" role="menuitem">
                                                <i class="bi bi-archive" aria-hidden="true"></i>
                                                <span>Archive</span>
                                            </a>
                                        </div>
                                    </details>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="record-pagination" aria-label="Manage records pagination">
            <?php if ($previousUrl !== null): ?>
                <a class="btn btn-outline-secondary btn-sm" href="<?= esc($previousUrl, 'attr') ?>" data-workspace-partial-link>Previous</a>
            <?php else: ?>
                <button class="btn btn-outline-secondary btn-sm" type="button" disabled>Previous</button>
            <?php endif; ?>

            <?php if ($nextUrl !== null): ?>
                <a class="btn btn-outline-secondary btn-sm" href="<?= esc($nextUrl, 'attr') ?>" data-workspace-partial-link>Next</a>
            <?php else: ?>
                <button class="btn btn-outline-secondary btn-sm" type="button" disabled>Next</button>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
</section>
