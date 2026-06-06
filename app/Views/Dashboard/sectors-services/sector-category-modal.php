<?php
/*
 * "Manage Categories" modal for the sectors page. Lets an admin give custom
 * sector categories (shortcode prefixes outside the official CSWD form) a
 * display name, or rename/remove one. Names are stored in a JSON file via
 * App\Libraries\SectorCategoryStore (no database table). Official categories
 * keep their fixed names and are not listed here.
 *
 * Each row is a plain form that posts to admin/sector-categories/save|delete and
 * reloads the page (same redirect-and-reload pattern as the Add Sector modal),
 * so no extra JavaScript is needed — the button opens this modal via Bootstrap's
 * data-bs-toggle.
 */
$sectorCategories = (array) ($sectorCategories ?? []);
?>
<div class="modal fade" id="sectorCategoryModal" tabindex="-1" aria-labelledby="sectorCategoryModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="sectorCategoryModalLabel">Manage Categories</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<p class="text-muted small mb-3">
					Give your custom categories a display name so they read clearly in the dropdown
					(e.g. <strong>NEW &ndash; Displaced Families</strong>). Official categories such as
					SC, PWD and SP keep their fixed names and are not listed here.
				</p>

				<?php if ($sectorCategories === []): ?>
					<p class="text-muted">No custom categories yet. Add one below, or create a sector with a custom code first.</p>
				<?php else: ?>
					<div class="sector-category-list mb-3">
						<?php foreach ($sectorCategories as $category): ?>
							<?php
							$prefix = (string) ($category['prefix'] ?? '');
							$name = (string) ($category['name'] ?? '');
							$sectorCount = (int) ($category['sectorCount'] ?? 0);
							?>
							<div class="sector-category-row d-flex align-items-center gap-2 mb-2">
								<form method="post" action="<?= site_url('admin/sector-categories/save') ?>" class="d-flex align-items-center gap-2 flex-grow-1">
									<?= csrf_field() ?>
									<input type="hidden" name="prefix" value="<?= esc($prefix, 'attr') ?>">
									<span class="badge bg-light text-dark border" style="min-width:3.5rem"><?= esc($prefix) ?></span>
									<input
										type="text"
										class="form-control form-control-sm"
										name="name"
										value="<?= esc($name, 'attr') ?>"
										placeholder="Category name"
										maxlength="150"
										required>
									<small class="text-muted text-nowrap"><?= esc((string) $sectorCount) ?> sector<?= $sectorCount === 1 ? '' : 's' ?></small>
									<button type="submit" class="btn btn-sm btn-primary">Save</button>
								</form>
								<?php if ($name !== ''): ?>
									<form method="post" action="<?= site_url('admin/sector-categories/delete') ?>">
										<?= csrf_field() ?>
										<input type="hidden" name="prefix" value="<?= esc($prefix, 'attr') ?>">
										<button type="submit" class="btn btn-sm btn-outline-danger" title="Remove the custom name">Remove</button>
									</form>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<hr>

				<form method="post" action="<?= site_url('admin/sector-categories/save') ?>" class="d-flex align-items-end gap-2 flex-wrap">
					<?= csrf_field() ?>
					<div>
						<label class="form-label small mb-1" for="sectorCategoryNewPrefix">Code</label>
						<input
							type="text"
							class="form-control form-control-sm text-uppercase"
							id="sectorCategoryNewPrefix"
							name="prefix"
							placeholder="e.g. NEW"
							pattern="[A-Za-z]+"
							title="Letters only"
							maxlength="30"
							style="max-width:7rem"
							required>
					</div>
					<div class="flex-grow-1">
						<label class="form-label small mb-1" for="sectorCategoryNewName">Category name</label>
						<input
							type="text"
							class="form-control form-control-sm"
							id="sectorCategoryNewName"
							name="name"
							placeholder="e.g. Displaced Families"
							maxlength="150"
							required>
					</div>
					<button type="submit" class="btn btn-sm btn-success">Add</button>
				</form>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
			</div>
		</div>
	</div>
</div>
