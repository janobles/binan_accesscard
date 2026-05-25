<?php
helper('dashboard_view');
extract(sector_management_view_data(get_defined_vars()), EXTR_OVERWRITE);
$sectorShortcodeOptions = $sectorShortcodeOptions !== [] ? $sectorShortcodeOptions : [
    'PWD1',
    'PWD2',
    'PWD3',
    'PWD4',
    'PWD5',
    'SP1',
    'SP2',
    'OSCA1',
    'OSCA2',
    'OSCA3',
    'OSCA4',
    'OSCA5',
    'OSCA6',
    'OSCA7',
];
?>

<div class="panel mb-3" data-sector-management-root>
	<div class="section-title mt-0">
		<span>Sector Management</span>
	</div>

	<form class="management-create-form mb-3" method="post" action="<?= site_url('admin/sectors/create') ?>">
		<?= csrf_field() ?>
		<div>
			<label class="form-label" for="sectorCreateShortcode">Shortcode</label>
			<select class="form-select" id="sectorCreateShortcode" name="shortcode" required>
				<option value="">Select</option>
				<?php foreach ($sectorShortcodeOptions as $shortcode): ?>
					<option value="<?= esc((string) $shortcode) ?>"><?= esc((string) $shortcode) ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div>
			<label class="form-label" for="sectorCreateName">Name</label>
			<input class="form-control" id="sectorCreateName" name="name" placeholder="Sector name" required>
		</div>
		<div>
			<label class="form-label" for="sectorCreateDescription">Description</label>
			<input class="form-control" id="sectorCreateDescription" name="description" placeholder="Description">
		</div>
		<div class="management-action">
			<button class="btn btn-primary w-100" type="submit">Add Sector</button>
		</div>
	</form>

	<div class="table-responsive">
		<table class="table table-sm align-middle management-table">
			<thead>
				<tr>
					<th>Shortcode</th>
					<th>Name</th>
					<th>Description</th>
					<th class="text-end">Update</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($sectors as $sector): ?>
					<?php $sectorId = (int) ($sector['sectorID'] ?? 0); ?>
					<?php $updateFormId = 'sectorUpdateForm' . $sectorId; ?>
					<tr>
						<td>
							<select class="form-select form-select-sm" name="shortcode" form="<?= esc($updateFormId) ?>" required>
								<?php foreach ($sectorShortcodeOptions as $shortcode): ?>
									<option value="<?= esc((string) $shortcode) ?>" <?= (string) ($sector['shortcode'] ?? '') === (string) $shortcode ? 'selected' : '' ?>><?= esc((string) $shortcode) ?></option>
								<?php endforeach; ?>
							</select>
						</td>
						<td>
							<input class="form-control form-control-sm" name="name" form="<?= esc($updateFormId) ?>" value="<?= esc((string) ($sector['name'] ?? '')) ?>" required>
						</td>
						<td>
							<input class="form-control form-control-sm" name="description" form="<?= esc($updateFormId) ?>" value="<?= esc((string) ($sector['description'] ?? '')) ?>">
						</td>
						<td class="text-end">
							<form id="<?= esc($updateFormId) ?>" method="post" action="<?= site_url('admin/sectors/update/' . $sectorId) ?>">
								<?= csrf_field() ?>
								<button class="btn btn-outline-primary btn-sm" type="submit">Save</button>
							</form>
						</td>
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
