<div class="modal fade" id="sectorActionModal" tabindex="-1" aria-labelledby="sectorActionModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<form
				method="post"
				data-create-action="<?= site_url('admin/sectors/create') ?>"
				data-update-action="<?= site_url('admin/sectors/update') ?>"
				data-archive-action="<?= site_url('admin/sectors/archive') ?>">
				<?= csrf_field() ?>
				<div class="modal-header">
					<h5 class="modal-title" id="sectorActionModalLabel">Sector</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<div class="js-sector-form-fields">
						<div class="mb-3">
							<label class="form-label" for="sectorModalShortcode">Shortcode</label>
							<select class="form-select js-management-other-select" id="sectorModalShortcode" name="shortcode" data-other-input="#sectorModalShortcodeOther" required>
								<option value="">Select</option>
								<?php foreach (($sectorShortcodeOptions ?? []) as $shortcode): ?>
									<option value="<?= esc((string) $shortcode) ?>"><?= esc((string) $shortcode) ?></option>
								<?php endforeach; ?>
								<option value="__other__">Others</option>
							</select>
							<input class="form-control mt-2 d-none" id="sectorModalShortcodeOther" name="shortcode_other" placeholder="Type new shortcode">
						</div>
						<div class="mb-3">
							<label class="form-label" for="sectorModalName">Name</label>
							<input class="form-control" id="sectorModalName" name="name" required>
						</div>
						<div class="mb-0">
							<label class="form-label" for="sectorModalDescription">Description</label>
							<textarea class="form-control" id="sectorModalDescription" name="description" rows="3"></textarea>
						</div>
					</div>
					<div class="alert alert-warning mb-0 d-none js-sector-archive-message">
						Archive <strong class="js-sector-archive-name">this sector</strong>? This will be blocked if records are already using it.
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-primary js-sector-modal-submit">Save</button>
				</div>
			</form>
		</div>
	</div>
</div>
