<?php
helper('dashboard_view');
extract(sector_management_view_data(get_defined_vars()), EXTR_OVERWRITE);
$sectorShortcodeOptions = $sectorShortcodeOptions !== []
    ? $sectorShortcodeOptions
    : array_values(array_filter(
        array_keys(\App\Support\FamilyProfilingFormV2::SECTOR_CATEGORIES),
        static fn (string $shortcode): bool => $shortcode !== 'OTHER'
    ));
?>

<div class="panel mb-3" data-sector-management-root>
	<div class="section-title mt-0">
		<span>Sector Management</span>
	</div>

	<form class="management-create-form mb-3" method="post" action="<?= site_url('admin/sectors/create') ?>">
		<?= csrf_field() ?>
		<div>
			<label class="form-label" for="sectorCreateShortcode">Shortcode</label>
			<select class="form-select js-management-other-select" id="sectorCreateShortcode" name="shortcode" data-other-input="#sectorCreateShortcodeOther" required>
				<option value="">Select</option>
				<?php foreach ($sectorShortcodeOptions as $shortcode): ?>
					<option value="<?= esc((string) $shortcode) ?>"><?= esc((string) $shortcode) ?></option>
				<?php endforeach; ?>
				<option value="__other__">Others</option>
			</select>
			<input class="form-control mt-2 d-none" id="sectorCreateShortcodeOther" name="shortcode_other" placeholder="Type new shortcode">
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
					<th class="text-end">Actions</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($sectors as $sector): ?>
					<?php $sectorId = (int) ($sector['sectorID'] ?? 0); ?>
					<?php
					$updateFormId = 'sectorUpdateForm' . $sectorId;
					$currentShortcode = (string) ($sector['shortcode'] ?? '');
					$shortcodeInOptions = in_array($currentShortcode, array_map('strval', $sectorShortcodeOptions), true);
					?>
					<tr data-inline-edit-row>
						<td>
							<select class="form-select form-select-sm js-management-other-select" name="shortcode" form="<?= esc($updateFormId) ?>" data-other-input="#sectorUpdateShortcodeOther<?= esc((string) $sectorId) ?>" data-inline-edit-field required disabled>
								<?php if ($currentShortcode !== '' && ! $shortcodeInOptions): ?>
									<option value="<?= esc($currentShortcode) ?>" selected><?= esc($currentShortcode) ?></option>
								<?php endif; ?>
								<?php foreach ($sectorShortcodeOptions as $shortcode): ?>
									<option value="<?= esc((string) $shortcode) ?>" <?= $currentShortcode === (string) $shortcode ? 'selected' : '' ?>><?= esc((string) $shortcode) ?></option>
								<?php endforeach; ?>
								<option value="__other__">Others</option>
							</select>
							<input class="form-control form-control-sm mt-2 d-none" id="sectorUpdateShortcodeOther<?= esc((string) $sectorId) ?>" name="shortcode_other" form="<?= esc($updateFormId) ?>" placeholder="Type new shortcode" data-inline-edit-field disabled>
						</td>
						<td>
							<input class="form-control form-control-sm" name="name" form="<?= esc($updateFormId) ?>" value="<?= esc((string) ($sector['name'] ?? '')) ?>" required data-inline-edit-field disabled>
						</td>
						<td>
							<input class="form-control form-control-sm" name="description" form="<?= esc($updateFormId) ?>" value="<?= esc((string) ($sector['description'] ?? '')) ?>" data-inline-edit-field disabled>
						</td>
						<td class="text-end">
							<?php $deleteFormId = 'sectorDeleteForm' . $sectorId; ?>
							<form id="<?= esc($updateFormId) ?>" method="post" action="<?= site_url('admin/sectors/update/' . $sectorId) ?>">
								<?= csrf_field() ?>
							</form>
							<form id="<?= esc($deleteFormId) ?>" class="js-management-delete-form" method="post" action="<?= site_url('admin/sectors/delete/' . $sectorId) ?>" data-confirm-message="<?= esc('Delete sector "' . (string) ($sector['name'] ?? '') . '"? This is permanent.', 'attr') ?>">
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
				<?php if ($sectors === []): ?>
					<tr>
						<td colspan="4" class="text-center text-muted">No sector records found.</td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>
