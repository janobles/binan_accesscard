<section class="service-management" aria-label="Services and programs">
    <header class="service-toolbar">
        <div class="service-status-tabs" aria-label="Service status">
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

        <button class="btn btn-success" type="button" data-sector-service-modal-open="service-modal">
            <i class="bi bi-plus-lg" aria-hidden="true"></i>
            <span>Add Service</span>
        </button>
    </header>

    <form
        class="service-searchbar"
        method="get"
        action="<?= site_url('admin/services') ?>"
        data-workspace-search-form
        data-page-title="Services and Programs"
        aria-label="Search services and programs"
    >
        <input type="hidden" name="status" value="<?= esc($status ?? 'active', 'attr') ?>">
        <input
            class="form-control"
            type="search"
            name="q"
            value="<?= esc($keyword ?? '', 'attr') ?>"
            placeholder="Search services by category, name, or description"
            aria-label="Search services and programs"
        >

        <div class="service-searchbar-actions">
            <button class="btn btn-success service-searchbar-action" type="submit" name="search_scope" value="services">
                <i class="bi bi-search" aria-hidden="true"></i>
                <span>Search</span>
            </button>

            <button class="btn btn-outline-success service-searchbar-action" type="submit" name="search_scope" value="all">
                <i class="bi bi-database-search" aria-hidden="true"></i>
                <span>Search All</span>
            </button>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table service-table align-middle">
            <thead>
                <tr>
                    <th scope="col">Program / Service</th>
                    <th scope="col">Category</th>
                    <th scope="col">Description</th>
                    <th scope="col">Status</th>
                    <th class="text-end" scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (($services ?? []) === []): ?>
                    <tr>
                        <td class="service-empty-state" colspan="5">No services or programs found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($services as $service): ?>
                        <?php $isArchived = ! empty($service['dt_deleted']); ?>
                        <tr>
                            <td class="service-name"><?= esc((string) ($service['name'] ?? '-')) ?></td>
                            <td><?= esc((string) ($service['category'] ?? '-')) ?></td>
                            <td><?= esc((string) ($service['description'] ?? '-')) ?></td>
                            <td>
                                <span class="service-status-badge <?= $isArchived ? 'service-status-archived' : 'service-status-active' ?>">
                                    <?= $isArchived ? 'Archived' : 'Active' ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <details class="service-action-menu">
                                    <summary class="btn btn-outline-secondary service-actions" aria-label="Service actions">
                                        <i class="bi bi-three-dots" aria-hidden="true"></i>
                                    </summary>

                                    <div class="service-action-options">
                                        <button
                                            type="button"
                                            data-sector-service-modal-open="service-modal"
                                            data-sector-service-modal-title="update Service"
                                            data-sector-service-modal-save-label="Update"
                                            data-sector-service-modal-field-service-name="<?= esc((string) ($service['name'] ?? ''), 'attr') ?>"
                                            data-sector-service-modal-field-service-category="<?= esc((string) ($service['category'] ?? ''), 'attr') ?>"
                                            data-sector-service-modal-field-service-description="<?= esc((string) ($service['description'] ?? ''), 'attr') ?>"
                                        >
                                            <i class="bi bi-pencil-square" aria-hidden="true"></i>
                                            <span>Update</span>
                                        </button>
                                        <button class="service-action-danger" type="button">
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
    'modalId' => 'service-modal',
    'title' => 'Add Service',
    'kicker' => 'Services and Programs',
    'saveLabel' => 'Save',
    'fields' => [
        ['id' => 'service-name', 'label' => 'Program / Service', 'type' => 'text'],
        ['id' => 'service-category', 'label' => 'Category', 'type' => 'text'],
        ['id' => 'service-description', 'label' => 'Description', 'type' => 'textarea'],
    ],
]) ?>
