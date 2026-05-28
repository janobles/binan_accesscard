<div class="modal fade" id="serviceActionModal" tabindex="-1" aria-labelledby="serviceActionModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<form
				method="post"
				data-create-action="<?= site_url('admin/services/create') ?>"
				data-update-action="<?= site_url('admin/services/update') ?>"
				data-archive-action="<?= site_url('admin/services/archive') ?>">
				<?= csrf_field() ?>
				<div class="modal-header">
					<h5 class="modal-title" id="serviceActionModalLabel">Service or Program</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
				</div>
				<div class="modal-body">
					<div class="js-service-form-fields">
						<div class="mb-3">
							<label class="form-label" for="serviceModalCategory">Category</label>
							<select class="form-select js-management-other-select" id="serviceModalCategory" name="category" data-other-input="#serviceModalCategoryOther" required>
								<option value="">Select</option>
								<?php foreach (($serviceCategoryOptions ?? []) as $category): ?>
									<option value="<?= esc((string) $category) ?>"><?= esc((string) $category) ?></option>
								<?php endforeach; ?>
								<option value="__other__">Others</option>
							</select>
							<input class="form-control mt-2 d-none" id="serviceModalCategoryOther" name="category_other" placeholder="Type new category">
						</div>
						<div class="mb-3">
							<label class="form-label" for="serviceModalName">Name</label>
							<input class="form-control" id="serviceModalName" name="name" required>
						</div>
						<div class="mb-0">
							<label class="form-label" for="serviceModalDescription">Description</label>
							<textarea class="form-control" id="serviceModalDescription" name="description" rows="3"></textarea>
						</div>
					</div>
					<div class="alert alert-warning mb-0 d-none js-service-archive-message">
						Archive <strong class="js-service-archive-name">this service or program</strong>? This will be blocked if records are already using it.
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-primary js-service-modal-submit">Save</button>
				</div>
			</form>
		</div>
	</div>
</div>
