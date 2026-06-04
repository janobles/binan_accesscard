<section class="sector-management" aria-label="Sector management">
    <header class="sector-toolbar">
        <div class="sector-status-tabs" aria-label="Sector status">
            <a
                class="btn <?= ($status ?? 'active') === 'active' ? 'btn-success' : 'btn-outline-secondary' ?>"
                href="<?= esc($activeUrl, 'attr') ?>"
                data-workspace-partial-link
            >
                <i class="bi bi-check2-circle" aria-hidden="true"></i>
                <span>Active</span>
            </a>
            <a
                class="btn <?= ($status ?? 'active') === 'archived' ? 'btn-success' : 'btn-outline-secondary' ?>"
                href="<?= esc($archivedUrl, 'attr') ?>"
                data-workspace-partial-link
            >
                <i class="bi bi-archive" aria-hidden="true"></i>
                <span>Archived</span>
            </a>
        </div>

        <button class="btn btn-success" type="button" data-sector-service-modal-open="sector-modal">
            <i class="bi bi-plus-lg" aria-hidden="true"></i>
            <span>Add Sector</span>
        </button>
    </header>

    <form
        class="sector-searchbar"
        method="get"
        action="<?= site_url('admin/sectors') ?>"
        data-workspace-search-form
        data-page-title="Sector Management"
        aria-label="Search sectors"
    >
        <input type="hidden" name="status" value="<?= esc($status ?? 'active', 'attr') ?>">
        <input
            class="form-control"
            type="search"
            name="q"
            value="<?= esc($keyword ?? '', 'attr') ?>"
            placeholder="Search sectors by name, code, or description"
            aria-label="Search sectors"
        >

        <div class="sector-searchbar-actions">
            <button class="btn btn-success sector-searchbar-action" type="submit" name="search_scope" value="sectors">
                <i class="bi bi-search" aria-hidden="true"></i>
                <span>Search</span>
            </button>

            <button class="btn btn-outline-success sector-searchbar-action" type="submit" name="search_scope" value="all">
                <i class="bi bi-database-search" aria-hidden="true"></i>
                <span>Search All</span>
            </button>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table sector-table align-middle">
            <thead>
                <tr>
                    <th scope="col">Short Code</th>
                    <th scope="col">Sector</th>
                    <th scope="col">Description</th>
                    <th scope="col">Status</th>
                    <th class="text-end" scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (($sectors ?? []) === []): ?>
                    <tr>
                        <td class="sector-empty-state" colspan="5">No sectors found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($sectors as $sector): ?>
                        <?php $isArchived = ! empty($sector['dt_deleted']); ?>
                        <tr>
                            <td><?= esc((string) ($sector['shortcode'] ?? '-')) ?></td>
                            <td class="sector-name"><?= esc((string) ($sector['name'] ?? '-')) ?></td>
                            <td><?= esc((string) ($sector['description'] ?? '-')) ?></td>
                            <td>
                                <span class="sector-status-badge <?= $isArchived ? 'sector-status-archived' : 'sector-status-active' ?>">
                                    <?= $isArchived ? 'Archived' : 'Active' ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <details class="sector-action-menu">
                                    <summary class="btn btn-outline-secondary sector-actions" aria-label="Sector actions">
                                        <i class="bi bi-three-dots" aria-hidden="true"></i>
                                    </summary>

                                    <div class="sector-action-options">
                                        <button
                                            type="button"
                                            data-sector-service-modal-open="sector-modal"
                                            data-sector-service-modal-title="Edit Sector"
                                            data-sector-service-modal-save-label="Update"
                                            data-sector-service-modal-field-sector-short-code="<?= esc((string) ($sector['shortcode'] ?? ''), 'attr') ?>"
                                            data-sector-service-modal-field-sector-name="<?= esc((string) ($sector['name'] ?? ''), 'attr') ?>"
                                            data-sector-service-modal-field-sector-description="<?= esc((string) ($sector['description'] ?? ''), 'attr') ?>"
                                        >
                                            <i class="bi bi-pencil-square" aria-hidden="true"></i>
                                            <span>Edit</span>
                                        </button>
                                        <button class="sector-action-danger" type="button">
                                            <i class="bi bi-archive" aria-hidden="true"></i>
                                            <span>Archive</span>
                                        </button>
                                    </div>
                                </details>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?= view('components/sector_service_modal', [
    'modalId' => 'sector-modal',
    'title' => 'Add Sector',
    'kicker' => 'Sector Management',
    'saveLabel' => 'Save',
    'fields' => [
        ['id' => 'sector-short-code', 'label' => 'Short Code', 'type' => 'text'],
        ['id' => 'sector-name', 'label' => 'Sector Name', 'type' => 'text'],
        ['id' => 'sector-description', 'label' => 'Description', 'type' => 'textarea'],
    ],
]) ?>
