<?php
helper('dashboard_view');
// service_management_view_data() supplies $serviceCategoryOptions (managed category
// names from the Manage Categories page + any categories already on services) for the
// Add-Program modal dropdown, so this view stays model-free.
extract(service_management_view_data(get_defined_vars()), EXTR_OVERWRITE);
$serviceCategoryOptions = $serviceCategoryOptions ?? [];

// Counts come from the server bundle (whole table), not the current page below.
$activeServiceCount   = (int) ($activeCount ?? 0);
$archivedServiceCount = (int) ($archivedCount ?? 0);
$allServiceCount      = $activeServiceCount + $archivedServiceCount;
$status               = (string) ($status ?? 'active');
$keyword              = (string) ($keyword ?? '');
$listRoute            = (string) ($listRoute ?? 'admin/services');
$perPage              = (int) ($perPage ?? 50);
$perPageOptions       = ($perPageOptions ?? []) ?: [10, 25, 50, 100];
// Read-only roles (Viewer) see the list without Add / Edit / Archive / Restore.
// Defaults true so the admin/developer services page is unaffected.
$canManage            = (bool) ($canManage ?? true);

// Builds a page URL preserving the current database keyword + status + page size.
$servicePageUrl = static function (int $targetPage) use ($listRoute, $keyword, $status, $perPage): string {
    $params = array_filter([
        'q'        => $keyword,
        'status'   => $status === 'active' ? '' : $status,
        'per_page' => $perPage !== 50 ? (string) $perPage : '',
        'page'     => $targetPage > 1 ? (string) $targetPage : '',
    ], static fn ($value): bool => $value !== '');

    return site_url($listRoute) . ($params === [] ? '' : '?' . http_build_query($params));
};

// "Clear" drops the keyword (and resets to page 1) but keeps status + page size.
$serviceClearUrl = static function () use ($listRoute, $status, $perPage): string {
    $params = array_filter([
        'status'   => $status === 'active' ? '' : $status,
        'per_page' => $perPage !== 50 ? (string) $perPage : '',
    ], static fn ($value): bool => $value !== '');

    return site_url($listRoute) . ($params === [] ? '' : '?' . http_build_query($params));
};
?>

<?php /* Reuses the Manage Records .records-* layout (managerecord.css). All melbranch hooks preserved:
         data-service-management-root, #service-status-select (data-lookup-status-select),
         data-lookup-search local filter, .js-service-modal-open + data-service-* attributes, the service-modal include. */ ?>
<div class="sector-management records-scroll-panel" data-service-management-root>
	<?php /* Database search across the whole services table (server-side GET) + status + Add. */ ?>
	<div class="records-search-panel">
		<form class="records-search-row records-lookup-search" method="get" action="<?= esc(site_url($listRoute), 'attr') ?>" role="search" aria-label="Search the services database">
			<input class="form-control" type="search" name="q" value="<?= esc($keyword, 'attr') ?>" placeholder="Search the whole services database" aria-label="Search the services database" autocomplete="off">
			<select class="form-select records-status-select" id="service-status-select" name="status" data-lookup-status-select aria-label="Service view">
				<option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active (<?= esc((string) $activeServiceCount) ?>)</option>
				<option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>Archive (<?= esc((string) $archivedServiceCount) ?>)</option>
				<option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All (<?= esc((string) $allServiceCount) ?>)</option>
			</select>
			<?php if ($perPage !== 50): ?><input type="hidden" name="per_page" value="<?= esc((string) $perPage, 'attr') ?>"><?php endif; ?>
			<a class="btn btn-danger records-search-action" href="<?= esc($serviceClearUrl(), 'attr') ?>"><i class="bi bi-x-lg" aria-hidden="true"></i><span>Clear</span></a>
			<button class="btn btn-outline-success records-search-action" type="submit"><i class="bi bi-search" aria-hidden="true"></i><span>Search All</span></button>
			<?php if ($canManage): ?>
			<button class="btn btn-primary records-search-action js-service-modal-open" type="button" data-service-mode="create"><span>Add Program</span></button>
			<?php endif; ?>
		</form>
	</div>

	<?php /* Controls row: page size (server) + local "Search:" live filter (client-side, no reload). */ ?>
	<div class="table-meta">
		<div class="records-table-controls">
			<form class="records-page-size-form" method="get" action="<?= esc(site_url($listRoute), 'attr') ?>">
				<?php if ($keyword !== ''): ?><input type="hidden" name="q" value="<?= esc($keyword, 'attr') ?>"><?php endif; ?>
				<?php if ($status !== 'active'): ?><input type="hidden" name="status" value="<?= esc($status, 'attr') ?>"><?php endif; ?>
				<label for="servicePerPage">Show</label>
				<select class="form-select form-select-sm" id="servicePerPage" name="per_page" onchange="this.form.submit()">
					<?php foreach ($perPageOptions as $option): ?>
						<option value="<?= esc((string) $option, 'attr') ?>" <?= $perPage === (int) $option ? 'selected' : '' ?>><?= esc((string) $option) ?></option>
					<?php endforeach; ?>
				</select>
				<span>entries</span>
			</form>
			<form class="records-table-search-form" role="search" data-lookup-search aria-label="Filter shown services">
				<label for="serviceLocalSearch">Search:</label>
				<input class="form-control form-control-sm" type="search" id="serviceLocalSearch" data-lookup-search-input placeholder="Type to filter..." autocomplete="off" aria-label="Filter shown services">
			</form>
		</div>
	</div>

	<div class="table-responsive">
		<table class="table table-sm manage-record-table align-middle lookup-management-table lookup-management-table--services">
			<thead>
				<tr>
					<th class="lookup-col-name">Name</th>
					<th class="lookup-col-code">Code</th>
					<th class="lookup-col-category">Category</th>
					<th class="lookup-col-description">Description</th>
					<th class="lookup-col-status">Status</th>
					<?php if ($canManage): ?><th class="lookup-col-actions text-end">Actions</th><?php endif; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($services as $service): ?>
					<?php $serviceId = (int) ($service['serviceID'] ?? 0); ?>
					<?php $isArchived = trim((string) ($service['dt_deleted'] ?? '')) !== ''; ?>
					<tr data-row-archived="<?= $isArchived ? '1' : '0' ?>">
						<td><span class="sector-name"><?= esc((string) ($service['name'] ?? '')) ?></span></td>
						<td><span class="badge bg-primary-subtle text-dark border fw-semibold"><?= esc((string) ($service['shortcode'] ?? '')) ?></span></td>
						<td><span class="badge bg-light text-dark border"><?= esc((string) ($service['category'] ?? '')) ?></span></td>
						<td><span class="text-trim d-inline-block"><?= esc((string) ($service['description'] ?? '')) ?></span></td>
						<td><span class="sector-status-badge <?= $isArchived ? 'sector-status-archived' : 'sector-status-active' ?>"><?= $isArchived ? 'Archived' : 'Active' ?></span></td>
						<?php if ($canManage): ?><td class="text-end">
							<div class="dropdown actions-menu">
								<button class="btn btn-outline-secondary btn-sm actions-menu-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false" aria-label="Service actions">
									<i class="bi bi-three-dots" aria-hidden="true"></i>
								</button>
								<div class="dropdown-menu dropdown-menu-end">
									<?php if (! $isArchived): ?>
										<button
											class="dropdown-item js-service-modal-open"
											type="button"
											data-service-mode="update"
											data-service-shortcode="<?= esc((string) ($service['shortcode'] ?? ''), 'attr') ?>"
											data-service-id="<?= esc((string) $serviceId) ?>"
											data-service-category="<?= esc((string) ($service['category'] ?? ''), 'attr') ?>"
											data-service-name="<?= esc((string) ($service['name'] ?? ''), 'attr') ?>"
											data-service-description="<?= esc((string) ($service['description'] ?? ''), 'attr') ?>">
											<i class="bi bi-pencil-square" aria-hidden="true"></i>Edit
										</button>
										<button
											class="dropdown-item text-danger js-service-modal-open"
											type="button"
											data-service-mode="archive"
											data-service-id="<?= esc((string) $serviceId) ?>"
											data-service-category="<?= esc((string) ($service['category'] ?? ''), 'attr') ?>"
											data-service-name="<?= esc((string) ($service['name'] ?? ''), 'attr') ?>"
											data-service-description="<?= esc((string) ($service['description'] ?? ''), 'attr') ?>">
											<i class="bi bi-archive" aria-hidden="true"></i>Archive
										</button>
									<?php else: ?>
										<button
											class="dropdown-item text-success js-service-modal-open"
											type="button"
											data-service-mode="restore"
											data-service-id="<?= esc((string) $serviceId) ?>"
											data-service-name="<?= esc((string) ($service['name'] ?? ''), 'attr') ?>">
											<i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>Restore
										</button>
									<?php endif; ?>
								</div>
							</div>
						</td>
						<?php endif; ?>
					</tr>
				<?php endforeach; ?>
				<?php if ($services === []): ?>
					<tr>
						<td colspan="6" class="sector-empty-state"><?= $keyword !== '' ? 'No services match your search.' : 'No service or program records found.' ?></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

	<?php if (($totalRows ?? 0) > 0): ?>
		<div class="lookup-list-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
			<span class="text-muted small">Showing <?= esc((string) $fromRecord) ?>–<?= esc((string) $toRecord) ?> of <?= esc((string) $totalRows) ?></span>
			<?php if (($totalPages ?? 1) > 1): ?>
				<div class="d-flex gap-2">
					<a class="btn btn-outline-secondary btn-sm<?= $page <= 1 ? ' disabled' : '' ?>" href="<?= esc($servicePageUrl(max(1, $page - 1)), 'attr') ?>" aria-disabled="<?= $page <= 1 ? 'true' : 'false' ?>">Previous</a>
					<span class="btn btn-sm disabled">Page <?= esc((string) $page) ?> of <?= esc((string) $totalPages) ?></span>
					<a class="btn btn-outline-secondary btn-sm<?= $page >= $totalPages ? ' disabled' : '' ?>" href="<?= esc($servicePageUrl(min($totalPages, $page + 1)), 'attr') ?>" aria-disabled="<?= $page >= $totalPages ? 'true' : 'false' ?>">Next</a>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>

<?php if ($canManage): ?>
<?= view('Lookups/service-modal', [
	'serviceCategoryOptions' => $serviceCategoryOptions,
	'serviceNextCodeMap' => $serviceNextCodeMap ?? [],
]) ?>
<?php endif; ?>
