<?php
helper('dashboard_view');
extract(sector_management_view_data(get_defined_vars()), EXTR_OVERWRITE);
?>

<div class="panel mb-3" data-sector-management-root>
	<div class="section-title mt-0 d-flex justify-content-between align-items-center gap-2">
		<span>Sector Management</span>
		<div class="d-flex gap-2">
			<button type="button" class="btn btn-sm btn-primary" id="sectorAddTrigger">Add</button>
			<button type="button" class="btn btn-sm btn-outline-primary" id="sectorUpdateTrigger">Update</button>
			<button type="button" class="btn btn-sm btn-outline-danger" id="sectorDeleteTrigger">Delete</button>
		</div>
	</div>

	<div class="table-responsive">
		<table class="table table-sm align-middle">
			<thead>
				<tr>
					<th>ID</th>
					<th>Shortcode</th>
					<th>Name</th>
					<th>Description</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($sectors as $sector): ?>
					<?php $sectorId = (int) ($sector['sectorID'] ?? 0); ?>
					<tr>
						<td><?= esc((string) $sectorId) ?></td>
						<td><?= esc((string) ($sector['shortcode'] ?? '')) ?></td>
						<td><?= esc((string) ($sector['name'] ?? '')) ?></td>
						<td><?= esc((string) ($sector['description'] ?? '')) ?></td>
					</tr>
				<?php endforeach; ?>
				<?php if ($sectors === []): ?>
					<tr>
						<td colspan="4" class="text-center text-muted">No sector records found.</td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

<div id="sectorAddPopup" class="management-popup-backdrop d-none" role="dialog" aria-modal="true" aria-labelledby="sectorAddPopupTitle">
	<div class="management-popup-card bg-white border rounded shadow">
		<form method="post" action="<?= site_url('admin/sectors/create') ?>">
			<div class="d-flex justify-content-between align-items-center p-3 border-bottom">
				<h5 class="mb-0" id="sectorAddPopupTitle">Add Sector</h5>
				<button type="button" class="btn-close js-close-sector-add-popup" aria-label="Close"></button>
			</div>
			<div class="p-3">
				<div class="mb-2">
					<label class="form-label">Shortcode</label>
					<input type="text" name="shortcode" class="form-control" maxlength="30" required>
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
				<button type="button" class="btn btn-secondary js-close-sector-add-popup">Cancel</button>
				<button type="submit" class="btn btn-primary">Add</button>
			</div>
		</form>
	</div>
</div>

<div id="sectorUpdatePopup" class="management-popup-backdrop d-none" role="dialog" aria-modal="true" aria-labelledby="sectorUpdatePopupTitle">
	<div class="management-popup-card bg-white border rounded shadow">
		<form id="sectorUpdateForm" method="post" action="<?= site_url('admin/sectors/update/0') ?>">
			<div class="d-flex justify-content-between align-items-center p-3 border-bottom">
				<h5 class="mb-0" id="sectorUpdatePopupTitle">Update Sector</h5>
				<button type="button" class="btn-close js-close-sector-update-popup" aria-label="Close"></button>
			</div>
			<div class="p-3">
				<div class="mb-2">
					<label class="form-label">Select Sector</label>
					<select class="form-select" id="sectorUpdateSelect" required>
						<option value="">Choose...</option>
						<?php foreach ($sectors as $sector): ?>
							<?php $sectorId = (int) ($sector['sectorID'] ?? 0); ?>
							<option value="<?= esc((string) $sectorId) ?>"><?= esc((string) $sectorId) ?> - <?= esc((string) ($sector['name'] ?? '')) ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="mb-2">
					<label class="form-label">Shortcode</label>
					<input type="text" id="sectorUpdateShortcode" name="shortcode" class="form-control" required>
				</div>
				<div class="mb-2">
					<label class="form-label">Name</label>
					<input type="text" id="sectorUpdateName" name="name" class="form-control" required>
				</div>
				<div>
					<label class="form-label">Description</label>
					<input type="text" id="sectorUpdateDescription" name="description" class="form-control">
				</div>
			</div>
			<div class="d-flex justify-content-end gap-2 p-3 border-top">
				<button type="button" class="btn btn-secondary js-close-sector-update-popup">Cancel</button>
				<button type="submit" class="btn btn-primary">Update</button>
			</div>
		</form>
	</div>
</div>

<div id="sectorDeletePopup" class="management-popup-backdrop d-none" role="dialog" aria-modal="true" aria-labelledby="sectorDeletePopupTitle">
	<div class="management-popup-card bg-white border rounded shadow">
		<form id="sectorArchiveForm" method="post" action="<?= site_url('admin/sectors/archive/0') ?>">
			<div class="d-flex justify-content-between align-items-center p-3 border-bottom">
				<h5 class="mb-0" id="sectorDeletePopupTitle">Delete Sector</h5>
				<button type="button" class="btn-close js-close-sector-delete-popup" aria-label="Close"></button>
			</div>
			<div class="p-3">
				<div class="mb-3">
					<label class="form-label">Select Sector</label>
					<select class="form-select" id="sectorArchiveSelect" required>
						<option value="">Choose...</option>
						<?php foreach ($sectors as $sector): ?>
							<?php $sectorId = (int) ($sector['sectorID'] ?? 0); ?>
							<option value="<?= esc((string) $sectorId) ?>"><?= esc((string) $sectorId) ?> - <?= esc((string) ($sector['name'] ?? '')) ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
			<div class="d-flex justify-content-end gap-2 p-3 border-top">
				<button type="button" class="btn btn-secondary js-close-sector-delete-popup">Cancel</button>
				<button type="submit" class="btn btn-danger">Delete</button>
			</div>
		</form>
	</div>
</div>

<script type="application/json" id="sectorListData"><?= json_encode($sectors, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?></script>
