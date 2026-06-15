<div class="modal fade" id="categoryActionModal" tabindex="-1" aria-labelledby="categoryActionModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<form
				method="post"
				data-create-action="<?= site_url('admin/categories/create') ?>"
				data-update-action="<?= site_url('admin/categories/update') ?>"
				data-archive-action="<?= site_url('admin/categories/archive') ?>"
				data-restore-action="<?= site_url('admin/categories/restore') ?>"
				data-delete-action="<?= site_url('admin/categories/delete') ?>"
				data-existing-codes="<?= esc(json_encode(array_values($existingCodes ?? [])), 'attr') ?>">
				<?= csrf_field() ?>
				<div class="modal-header">
					<h5 class="modal-title" id="categoryActionModalLabel">Category</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<div class="js-category-form-fields">
						<div class="mb-3">
							<label class="form-label" for="categoryModalCode">Code</label>
							<input class="form-control text-uppercase" id="categoryModalCode" name="code" placeholder="e.g. NEW" pattern="[A-Za-z]+" title="Letters only" maxlength="30" required>
							<div class="invalid-feedback d-block d-none js-category-code-error">Duplicate code - please enter another code.</div>
						</div>
						<div class="mb-0">
							<label class="form-label" for="categoryModalName">Name</label>
							<input class="form-control" id="categoryModalName" name="name" maxlength="150" required>
						</div>
					</div>
					<div class="alert alert-warning mb-0 d-none js-category-archive-message">
						Archive <strong class="js-category-archive-name">this category</strong>? This will be blocked if any sectors still use it.
					</div>
					<div class="alert alert-danger mb-0 d-none js-category-delete-message">
						Permanently delete <strong class="js-category-delete-name">this category</strong>? This cannot be undone and will be blocked if any sectors still use it.
					</div>
					<div class="alert alert-info mb-0 d-none js-category-restore-message">
						Restore <strong class="js-category-restore-name">this category</strong>? It will become active again and available for sectors.
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-primary js-category-modal-submit">Save</button>
				</div>
			</form>
		</div>
	</div>
</div>
