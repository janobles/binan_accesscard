<?php
helper('dashboard_view');
extract(service_management_view_data(get_defined_vars()), EXTR_OVERWRITE);
?>

<div class="panel mb-3" data-service-management-root>
	<div class="section-title mt-0 d-flex justify-content-between align-items-center gap-2">
		<span>Service Management</span>
		<div class="d-flex gap-2">
			<button type="button" class="btn btn-sm btn-primary" id="serviceAddTrigger">Add</button>
			<button type="button" class="btn btn-sm btn-outline-primary" id="serviceUpdateTrigger">Update</button>
			<button type="button" class="btn btn-sm btn-outline-danger" id="serviceDeleteTrigger">Delete</button>
		</div>
	</div>

	<div class="table-responsive">
		<table class="table table-sm align-middle">
			<thead>
				<tr>
					<th>ID</th>
					<th>Category</th>
					<th>Name</th>
					<th>Description</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($services as $service): ?>
					<?php $serviceId = (int) ($service['serviceID'] ?? 0); ?>
					<tr>
						<td><?= esc((string) $serviceId) ?></td>
						<td><?= esc((string) ($service['category'] ?? '')) ?></td>
						<td><?= esc((string) ($service['name'] ?? '')) ?></td>
						<td><?= esc((string) ($service['description'] ?? '')) ?></td>
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

<div id="serviceAddPopup" class="service-add-popup-backdrop d-none" role="dialog" aria-modal="true" aria-labelledby="serviceAddPopupTitle">
	<div class="service-add-popup-card bg-white border rounded shadow">
		<form method="post" action="<?= site_url('admin/services/create') ?>">
			<div class="d-flex justify-content-between align-items-center p-3 border-bottom">
				<h5 class="mb-0" id="serviceAddPopupTitle">Add Service</h5>
				<button type="button" class="btn-close js-close-service-add-popup" aria-label="Close"></button>
			</div>
			<div class="p-3">
				<div class="mb-2">
					<label class="form-label">Category</label>
					<input type="text" name="category" class="form-control" maxlength="120" required>
				</div>
				<div class="mb-2">
					<label class="form-label">Name</label>
					<input type="text" name="name" class="form-control" maxlength="255" required>
				</div>
				<div>
					<label class="form-label">Description</label>
					<input type="text" name="description" class="form-control" maxlength="500">
				</div>
			</div>
			<div class="d-flex justify-content-end gap-2 p-3 border-top">
				<button type="button" class="btn btn-secondary js-close-service-add-popup">Cancel</button>
				<button type="submit" class="btn btn-primary">Add</button>
			</div>
		</form>
	</div>
</div>

<div id="serviceUpdatePopup" class="management-popup-backdrop d-none" role="dialog" aria-modal="true" aria-labelledby="serviceUpdatePopupTitle">
	<div class="management-popup-card bg-white border rounded shadow">
		<form id="serviceUpdateForm" method="post" action="<?= site_url('admin/services/update/0') ?>">
			<div class="d-flex justify-content-between align-items-center p-3 border-bottom">
				<h5 class="mb-0" id="serviceUpdatePopupTitle">Update Service</h5>
				<button type="button" class="btn-close js-close-service-update-popup" aria-label="Close"></button>
			</div>
			<div class="p-3">
				<div class="mb-2">
					<label class="form-label">Select Service</label>
					<select class="form-select" id="serviceUpdateSelect" required>
						<option value="">Choose...</option>
						<?php foreach ($services as $service): ?>
							<?php $serviceId = (int) ($service['serviceID'] ?? 0); ?>
							<option value="<?= esc((string) $serviceId) ?>"><?= esc((string) $serviceId) ?> - <?= esc((string) ($service['name'] ?? '')) ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="mb-2">
					<label class="form-label">Category</label>
					<input type="text" id="serviceUpdateCategory" name="category" class="form-control" required>
				</div>
				<div class="mb-2">
					<label class="form-label">Name</label>
					<input type="text" id="serviceUpdateName" name="name" class="form-control" required>
				</div>
				<div>
					<label class="form-label">Description</label>
					<input type="text" id="serviceUpdateDescription" name="description" class="form-control">
				</div>
			</div>
			<div class="d-flex justify-content-end gap-2 p-3 border-top">
				<button type="button" class="btn btn-secondary js-close-service-update-popup">Cancel</button>
				<button type="submit" class="btn btn-primary">Update</button>
			</div>
		</form>
	</div>
</div>

<div id="serviceDeletePopup" class="management-popup-backdrop d-none" role="dialog" aria-modal="true" aria-labelledby="serviceDeletePopupTitle">
	<div class="management-popup-card bg-white border rounded shadow">
		<form id="serviceArchiveForm" method="post" action="<?= site_url('admin/services/archive/0') ?>">
			<div class="d-flex justify-content-between align-items-center p-3 border-bottom">
				<h5 class="mb-0" id="serviceDeletePopupTitle">Delete Service</h5>
				<button type="button" class="btn-close js-close-service-delete-popup" aria-label="Close"></button>
			</div>
			<div class="p-3">
				<div class="mb-3">
					<label class="form-label">Select Service</label>
					<select class="form-select" id="serviceArchiveSelect" required>
						<option value="">Choose...</option>
						<?php foreach ($services as $service): ?>
							<?php $serviceId = (int) ($service['serviceID'] ?? 0); ?>
							<option value="<?= esc((string) $serviceId) ?>"><?= esc((string) $serviceId) ?> - <?= esc((string) ($service['name'] ?? '')) ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
			<div class="d-flex justify-content-end gap-2 p-3 border-top">
				<button type="button" class="btn btn-secondary js-close-service-delete-popup">Cancel</button>
				<button type="submit" class="btn btn-danger">Delete</button>
			</div>
		</form>
	</div>
</div>

<script type="application/json" id="serviceListData"><?= json_encode($services, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?></script>
