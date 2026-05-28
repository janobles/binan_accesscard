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
			<select class="form-select js-management-other-select" id="serviceCreateCategory" name="category" data-other-input="#serviceCreateCategoryOther" required>
				<option value="">Select</option>
				<?php foreach ($serviceCategoryOptions as $category): ?>
					<option value="<?= esc((string) $category) ?>"><?= esc((string) $category) ?></option>
				<?php endforeach; ?>
				<option value="__other__">Others</option>
			</select>
			<input class="form-control mt-2 d-none" id="serviceCreateCategoryOther" name="category_other" placeholder="Type new category">
		</div>
		<div>
			<label class="form-label" for="serviceCreateName">Name</label>
			<input class="form-control" id="serviceCreateName" name="name" placeholder="Service or program name" required>
		</div>
		<div>
			<label class="form-label" for="serviceCreateDescription">Description</label>
			<input class="form-control" id="serviceCreateDescription" name="description" placeholder="Description">
		</div>
		<div class="management-action">
			<button class="btn btn-primary w-100" type="submit">Add Service or Program</button>
		</div>
	</form>

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
					<?php $updateFormId = 'serviceUpdateForm' . $serviceId; ?>
					<tr data-inline-edit-row>
						<td>
							<input class="form-control form-control-sm" list="serviceCategoryOptions" name="category" form="<?= esc($updateFormId) ?>" value="<?= esc((string) ($service['category'] ?? '')) ?>" required data-inline-edit-field disabled>
						</td>
						<td>
							<input class="form-control form-control-sm" name="name" form="<?= esc($updateFormId) ?>" value="<?= esc((string) ($service['name'] ?? '')) ?>" required data-inline-edit-field disabled>
						</td>
						<td>
							<input class="form-control form-control-sm" name="description" form="<?= esc($updateFormId) ?>" value="<?= esc((string) ($service['description'] ?? '')) ?>" data-inline-edit-field disabled>
						</td>
						<td class="text-end">
							<?php $deleteFormId = 'serviceDeleteForm' . $serviceId; ?>
							<form id="<?= esc($updateFormId) ?>" method="post" action="<?= site_url('admin/services/update/' . $serviceId) ?>">
								<?= csrf_field() ?>
							</form>
							<form id="<?= esc($deleteFormId) ?>" class="js-management-delete-form" method="post" action="<?= site_url('admin/services/delete/' . $serviceId) ?>" data-confirm-message="<?= esc('Delete service or program "' . (string) ($service['name'] ?? '') . '"? This is permanent.', 'attr') ?>">
								<?= csrf_field() ?>
							</form>
							<div class="management-row-actions">
								<button class="btn btn-outline-primary btn-sm js-inline-edit" type="button">Edit</button>
								<button class="btn btn-primary btn-sm js-inline-save d-none" type="submit" form="<?= esc($updateFormId) ?>">Save</button>
								<button class="btn btn-outline-secondary btn-sm js-inline-cancel d-none" type="button">Cancel</button>
								<button class="btn btn-outline-danger btn-sm" type="submit" form="<?= esc($deleteFormId) ?>">Delete</button>
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
