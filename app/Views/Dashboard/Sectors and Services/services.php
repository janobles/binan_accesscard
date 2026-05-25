<?php
helper('dashboard_view');
extract(service_management_view_data(get_defined_vars()), EXTR_OVERWRITE);
$serviceCategoryOptions = array_values(array_unique(array_filter(array_map(
    static fn (array $service): string => trim((string) ($service['category'] ?? '')),
    $services
))));
?>

<div class="panel mb-3" data-service-management-root>
	<div class="section-title mt-0">
		<span>Service Management</span>
	</div>

	<datalist id="serviceCategoryOptions">
		<?php foreach ($serviceCategoryOptions as $category): ?>
			<option value="<?= esc($category) ?>"></option>
		<?php endforeach; ?>
	</datalist>

	<form class="management-create-form mb-3" method="post" action="<?= site_url('admin/services/create') ?>">
		<?= csrf_field() ?>
		<div>
			<label class="form-label" for="serviceCreateCategory">Category</label>
			<input class="form-control" id="serviceCreateCategory" list="serviceCategoryOptions" name="category" placeholder="General" required>
		</div>
		<div>
			<label class="form-label" for="serviceCreateName">Name</label>
			<input class="form-control" id="serviceCreateName" name="name" placeholder="Service name" required>
		</div>
		<div>
			<label class="form-label" for="serviceCreateDescription">Description</label>
			<input class="form-control" id="serviceCreateDescription" name="description" placeholder="Description">
		</div>
		<div class="management-action">
			<button class="btn btn-primary w-100" type="submit">Add Service</button>
		</div>
	</form>

	<div class="table-responsive">
		<table class="table table-sm align-middle management-table">
			<thead>
				<tr>
					<th>Category</th>
					<th>Name</th>
					<th>Description</th>
					<th class="text-end">Update</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($services as $service): ?>
					<?php $serviceId = (int) ($service['serviceID'] ?? 0); ?>
					<?php $updateFormId = 'serviceUpdateForm' . $serviceId; ?>
					<tr>
						<td>
							<input class="form-control form-control-sm" list="serviceCategoryOptions" name="category" form="<?= esc($updateFormId) ?>" value="<?= esc((string) ($service['category'] ?? '')) ?>" required>
						</td>
						<td>
							<input class="form-control form-control-sm" name="name" form="<?= esc($updateFormId) ?>" value="<?= esc((string) ($service['name'] ?? '')) ?>" required>
						</td>
						<td>
							<input class="form-control form-control-sm" name="description" form="<?= esc($updateFormId) ?>" value="<?= esc((string) ($service['description'] ?? '')) ?>">
						</td>
						<td class="text-end">
							<form id="<?= esc($updateFormId) ?>" method="post" action="<?= site_url('admin/services/update/' . $serviceId) ?>">
								<?= csrf_field() ?>
								<button class="btn btn-outline-primary btn-sm" type="submit">Save</button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				<?php if ($services === []): ?>
					<tr>
						<td colspan="4" class="text-center text-muted">No service records found.</td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>
