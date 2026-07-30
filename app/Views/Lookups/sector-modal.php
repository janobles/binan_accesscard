<?php
/**
 * Shared Add, Edit, Archive and Restore modal for sectors, included once by the
 * sector list page (Admin > Reference Data > Sectors). The values it renders come
 * from that page's scope, built by sector_management_view_data().
 *
 * One form serves all four actions: sectors-modal.js swaps the form action between
 * the four data-*-action URLs. The existing shortcodes ride along in
 * data-existing-codes so the duplicate check runs in the browser before a post,
 * which is a convenience only; Lookups\SectorController re-checks server side.
 */
?>
<div class="modal fade" id="sectorActionModal" tabindex="-1" aria-labelledby="sectorActionModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<form
				method="post"
				data-create-action="<?= site_url('admin/sectors/create') ?>"
				data-update-action="<?= site_url('admin/sectors/update') ?>"
				data-archive-action="<?= site_url('admin/sectors/archive') ?>"
				data-restore-action="<?= site_url('admin/sectors/restore') ?>"
				data-existing-codes="<?= esc(json_encode(array_values($existingShortcodes ?? [])), 'attr') ?>">
				<?= csrf_field() ?>
				<div class="modal-header">
					<h5 class="modal-title" id="sectorActionModalLabel">Sector</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<div class="js-sector-form-fields">
						<div class="mb-3">
							<label class="form-label" for="sectorModalShortcode">Code</label>
							<input class="form-control text-uppercase" id="sectorModalShortcode" name="shortcode" placeholder="e.g. SC, PWD, OTHER" required>
							<div class="invalid-feedback d-block d-none js-sector-code-error">Duplicate code - please enter another code.</div>
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
						Archive <strong class="js-sector-archive-name">this sector</strong>? Every active service and program filed under it will be archived at the same time. Existing family records keep the sector.
					</div>
					<div class="alert alert-info mb-0 d-none js-sector-restore-message">
						Restore <strong class="js-sector-restore-name">this sector</strong>? It becomes active again, and the services and programs that were archived together with it are restored too.
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-success js-sector-modal-submit">Add</button>
				</div>
			</form>
		</div>
	</div>
</div>
