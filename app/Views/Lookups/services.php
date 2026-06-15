<?php
helper('dashboard_view');
extract(service_management_view_data(get_defined_vars()), EXTR_OVERWRITE);
$defaultServiceCategoryOptions = \App\Support\FamilyProfilingFormV2::SERVICE_CATEGORIES;
$serviceCategoryOptions = array_values(array_unique(array_filter(array_map(
    static fn (array $service): string => trim((string) ($service['category'] ?? '')),
    $services
))));
$serviceCategoryOptions = array_values(array_unique(array_merge($defaultServiceCategoryOptions, $serviceCategoryOptions)));

// Counts come from the server bundle (whole table), not the 50-row page below.
$activeServiceCount   = (int) ($activeCount ?? 0);
$archivedServiceCount = (int) ($archivedCount ?? 0);
$allServiceCount      = $activeServiceCount + $archivedServiceCount;
$status               = (string) ($status ?? 'active');
$keyword              = (string) ($keyword ?? '');
$listRoute            = (string) ($listRoute ?? 'admin/services');

// Builds a page URL preserving the current database keyword + status filter.
$servicePageUrl = static function (int $targetPage) use ($listRoute, $keyword, $status): string {
    $params = array_filter([
        'q'      => $keyword,
        'status' => $status === 'active' ? '' : $status,
        'page'   => $targetPage > 1 ? (string) $targetPage : '',
    ], static fn ($value): bool => $value !== '');

    return site_url($listRoute) . ($params === [] ? '' : '?' . http_build_query($params));
};
?>

<?php /* Shared lookup-management styling (sector-* classes kept for compatibility). All
         melbranch hooks preserved: data-service-management-root, the
         #service-status-select toggle, .js-service-modal-open
         + data-service-* attributes, and the service-modal include. */ ?>
<div class="sector-management" data-service-management-root>
	<?php /* Bar 1 — local quick filter over the rows currently shown (client-side, no reload) + status + Add. */ ?>
	<form class="sector-toolbar sector-lookup-toolbar" role="search" data-lookup-search aria-label="Filter shown services">
		<input class="form-control sector-toolbar-search" type="search" data-lookup-search-input placeholder="Filter the services shown below" aria-label="Filter shown services">
		<select class="form-select sector-status-select" id="service-status-select" name="status" data-lookup-status-select aria-label="Service view">
			<option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active (<?= esc((string) $activeServiceCount) ?>)</option>
			<option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>Archive (<?= esc((string) $archivedServiceCount) ?>)</option>
			<option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All (<?= esc((string) $allServiceCount) ?>)</option>
		</select>
		<span id="service-add-btn-wrap">
			<button class="btn btn-success sector-toolbar-action js-service-modal-open" type="button" data-service-mode="create"><i class="bi bi-plus-lg" aria-hidden="true"></i><span>Add Program</span></button>
		</span>
	</form>

	<?php /* Bar 2 — database search across the whole services table (server-side GET + pagination). */ ?>
	<form class="sector-toolbar sector-lookup-toolbar sector-database-search" method="get" action="<?= esc(site_url($listRoute), 'attr') ?>" role="search" aria-label="Search the services database">
		<?php if ($status !== 'active'): ?><input type="hidden" name="status" value="<?= esc($status, 'attr') ?>"><?php endif; ?>
		<input class="form-control sector-toolbar-search" type="search" name="q" value="<?= esc($keyword, 'attr') ?>" placeholder="Search the whole services database" aria-label="Search the services database" autocomplete="off">
		<button class="btn btn-outline-success sector-toolbar-action" type="submit"><i class="bi bi-search" aria-hidden="true"></i><span>Search All</span></button>
		<?php if ($keyword !== ''): ?>
			<a class="btn btn-outline-secondary sector-toolbar-action" href="<?= esc(site_url($listRoute) . ($status === 'active' ? '' : '?status=' . $status), 'attr') ?>"><i class="bi bi-x-lg" aria-hidden="true"></i><span>Clear</span></a>
		<?php endif; ?>
	</form>

	<div class="table-responsive">
		<table class="table sector-table align-middle management-table">
			<thead>
				<tr>
					<th>Category</th>
					<th>Name</th>
					<th>Description</th>
					<th>Status</th>
					<th class="text-end">Actions</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($services as $service): ?>
					<?php $serviceId = (int) ($service['serviceID'] ?? 0); ?>
					<?php $isArchived = trim((string) ($service['dt_deleted'] ?? '')) !== ''; ?>
					<tr data-row-archived="<?= $isArchived ? '1' : '0' ?>">
						<td><span class="badge bg-light text-dark border"><?= esc((string) ($service['category'] ?? '')) ?></span></td>
						<td><span class="sector-name"><?= esc((string) ($service['name'] ?? '')) ?></span></td>
						<td><span class="text-trim d-inline-block"><?= esc((string) ($service['description'] ?? '')) ?></span></td>
						<td><span class="sector-status-badge <?= $isArchived ? 'sector-status-archived' : 'sector-status-active' ?>"><?= $isArchived ? 'Archived' : 'Active' ?></span></td>
						<td class="text-end">
							<div class="dropdown actions-menu">
								<button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false" aria-label="Service actions">
									<i class="bi bi-three-dots" aria-hidden="true"></i>
								</button>
								<div class="dropdown-menu dropdown-menu-end">
									<?php if (! $isArchived): ?>
										<button
											class="dropdown-item js-service-modal-open"
											type="button"
											data-service-mode="update"
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
					</tr>
				<?php endforeach; ?>
				<?php if ($services === []): ?>
					<tr>
						<td colspan="5" class="sector-empty-state"><?= $keyword !== '' ? 'No services match your search.' : 'No service or program records found.' ?></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

	<?php if (($totalRows ?? 0) > 0): ?>
		<div class="lookup-list-footer d-flex flex-wrap justify-content-between align-items-center gap-2 mt-2">
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

<?= view('Lookups/service-modal', [
	'serviceCategoryOptions' => $serviceCategoryOptions,
]) ?>
