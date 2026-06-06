<?php
helper('dashboard_view');
extract(service_management_view_data(get_defined_vars()), EXTR_OVERWRITE);
$defaultServiceCategoryOptions = \App\Support\FamilyProfilingFormV2::SERVICE_CATEGORIES;
$serviceCategoryOptions = array_values(array_unique(array_filter(array_map(
    static fn (array $service): string => trim((string) ($service['category'] ?? '')),
    $services
))));
$serviceCategoryOptions = array_values(array_unique(array_merge($defaultServiceCategoryOptions, $serviceCategoryOptions)));

$activeServiceCount   = count(array_filter($services, static fn ($s) => trim((string) ($s['dt_deleted'] ?? '')) === ''));
$archivedServiceCount = count($services) - $activeServiceCount;
?>

<?php /* Jade-style reskin (sector-* class system, shared with service.css). All
         melbranch hooks preserved: data-service-management-root, the
         #btn-service-active/#btn-service-archive toggle, .js-service-modal-open
         + data-service-* attributes, and the service-modal include. */ ?>
<div class="sector-management" data-service-management-root>
	<header class="sector-toolbar">
		<div class="sector-status-tabs btn-group" role="group" aria-label="Service view toggle">
			<button type="button" class="btn btn-success active" id="btn-service-active" aria-pressed="true">Active (<?= esc((string) $activeServiceCount) ?>)</button>
			<button type="button" class="btn btn-outline-secondary" id="btn-service-archive" aria-pressed="false">Archive (<?= esc((string) $archivedServiceCount) ?>)</button>
		</div>
		<span id="service-add-btn-wrap">
			<button class="btn btn-success js-service-modal-open" type="button" data-service-mode="create"><i class="bi bi-plus-lg" aria-hidden="true"></i><span>Add Service or Program</span></button>
		</span>
	</header>

	<form class="searchbar searchbar-single" role="search" data-lookup-search aria-label="Search services and programs">
		<input class="form-control" type="search" data-lookup-search-input placeholder="Search services by category, name, or description" aria-label="Search services and programs">
		<button class="btn btn-success searchbar-action" type="submit"><i class="bi bi-search" aria-hidden="true"></i><span>Search</span></button>
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
					<tr data-row-archived="<?= $isArchived ? '1' : '0' ?>"<?= $isArchived ? ' class="d-none"' : '' ?>>
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
						<td colspan="5" class="sector-empty-state">No service or program records found.</td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

<?= view('Dashboard/sectors-services/service-modal', [
	'serviceCategoryOptions' => $serviceCategoryOptions,
]) ?>
