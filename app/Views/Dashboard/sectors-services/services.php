<?php
helper('dashboard_view');
extract(service_management_view_data(get_defined_vars()), EXTR_OVERWRITE);
$defaultServiceCategoryOptions = \App\Support\FamilyProfilingFormV2::SERVICE_CATEGORIES;
$serviceCategoryOptions = array_values(array_unique(array_filter(array_map(
    static fn (array $service): string => trim((string) ($service['category'] ?? '')),
    $services
))));
$serviceCategoryOptions = array_values(array_unique(array_merge($defaultServiceCategoryOptions, $serviceCategoryOptions)));
?>

<div class="panel mb-3" data-service-management-root>
	<div class="section-title mt-0">
		<span>Services and Programs Management</span>
		<button class="btn btn-primary btn-sm js-service-modal-open" type="button" data-service-mode="create"><i class="bi bi-plus-lg" aria-hidden="true"></i>Add Service or Program</button>
	</div>

	<div class="table-responsive">
		<table class="table table-sm align-middle management-table">
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
					<tr>
						<td><span class="status-pill is-muted"><?= esc((string) ($service['category'] ?? '')) ?></span></td>
						<td><span class="entity-title"><?= esc((string) ($service['name'] ?? '')) ?></span></td>
						<td><span class="text-trim d-inline-block"><?= esc((string) ($service['description'] ?? '')) ?></span></td>
						<td><span class="status-pill <?= $isArchived ? 'is-danger' : 'is-active' ?>"><?= $isArchived ? 'Archived' : 'Active' ?></span></td>
						<td class="text-end">
							<div class="dropdown actions-menu">
								<button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false" aria-label="Service actions">
									<i class="bi bi-three-dots" aria-hidden="true"></i>
								</button>
								<div class="dropdown-menu dropdown-menu-end">
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
									<?php if (! $isArchived): ?>
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
										<span class="dropdown-item text-muted"><i class="bi bi-lock" aria-hidden="true"></i>Archived</span>
									<?php endif; ?>
								</div>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
				<?php if ($services === []): ?>
					<tr>
						<td colspan="5" class="text-center text-muted">No service or program records found.</td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

<?= view('Dashboard/Sectors and Services/service-modal', [
	'serviceCategoryOptions' => $serviceCategoryOptions,
]) ?>
