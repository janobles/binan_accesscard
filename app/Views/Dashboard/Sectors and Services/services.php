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
		<button class="btn btn-primary btn-sm js-service-modal-open" type="button" data-service-mode="create">Add Service or Program</button>
	</div>

	<div class="table-responsive">
		<table class="table table-sm align-middle management-table">
			<thead>
				<tr>
					<th>Category</th>
					<th>Name</th>
					<th>Description</th>
					<th class="text-end">Actions</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($services as $service): ?>
					<?php $serviceId = (int) ($service['serviceID'] ?? 0); ?>
					<tr>
						<td><?= esc((string) ($service['category'] ?? '')) ?></td>
						<td><?= esc((string) ($service['name'] ?? '')) ?></td>
						<td><?= esc((string) ($service['description'] ?? '')) ?></td>
						<td class="text-end">
							<div class="management-row-actions">
								<button
									class="btn btn-outline-primary btn-sm js-service-modal-open"
									type="button"
									data-service-mode="update"
									data-service-id="<?= esc((string) $serviceId) ?>"
									data-service-category="<?= esc((string) ($service['category'] ?? ''), 'attr') ?>"
									data-service-name="<?= esc((string) ($service['name'] ?? ''), 'attr') ?>"
									data-service-description="<?= esc((string) ($service['description'] ?? ''), 'attr') ?>">
									Edit
								</button>
								<button
									class="btn btn-outline-danger btn-sm js-service-modal-open"
									type="button"
									data-service-mode="archive"
									data-service-id="<?= esc((string) $serviceId) ?>"
									data-service-category="<?= esc((string) ($service['category'] ?? ''), 'attr') ?>"
									data-service-name="<?= esc((string) ($service['name'] ?? ''), 'attr') ?>"
									data-service-description="<?= esc((string) ($service['description'] ?? ''), 'attr') ?>">
									Archive
								</button>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
				<?php if ($services === []): ?>
					<tr>
						<td colspan="4" class="text-center text-muted">No service or program records found.</td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

<?= view('Dashboard/Sectors and Services/service-modal', [
	'serviceCategoryOptions' => $serviceCategoryOptions,
]) ?>
