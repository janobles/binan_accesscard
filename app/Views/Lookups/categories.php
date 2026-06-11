<?php
/*
 * "Manage Categories" management page. Lists the sector categories from the
 * `category` table and lets an admin add/rename/archive/restore/delete them via
 * the shared #categoryActionModal (see category-modal.php + categories-modal.js).
 *
 * Official categories (is_official = 1) can be renamed but never archived or
 * deleted — those actions are hidden here and blocked server-side in
 * Lookups\CategoryController. Reuses the sector-* CSS classes (css/sector.css).
 */
$categories = (array) ($categories ?? []);

$existingCodes = array_values(array_unique(array_filter(array_map(
    static fn (array $c): string => strtoupper(trim((string) ($c['code'] ?? ''))),
    $categories
))));

$activeCategoryCount   = count(array_filter($categories, static fn ($c) => trim((string) ($c['dt_deleted'] ?? '')) === ''));
$archivedCategoryCount = count($categories) - $activeCategoryCount;
?>

<div class="sector-management" data-category-management-root>
	<form class="sector-toolbar sector-lookup-toolbar" role="search" data-lookup-search aria-label="Search categories">
		<input class="form-control sector-toolbar-search" type="search" data-lookup-search-input placeholder="Search categories by code or name" aria-label="Search categories">
		<select class="form-select sector-status-select" id="category-status-select" name="status" aria-label="Category view">
			<option value="active">Active (<?= esc((string) $activeCategoryCount) ?>)</option>
			<option value="archived">Archive (<?= esc((string) $archivedCategoryCount) ?>)</option>
		</select>
		<button class="btn btn-success sector-toolbar-action" type="submit"><i class="bi bi-search" aria-hidden="true"></i><span>Search</span></button>
		<span id="category-add-btn-wrap">
			<button class="btn btn-success sector-toolbar-action js-category-modal-open" type="button" data-category-mode="create"><i class="bi bi-plus-lg" aria-hidden="true"></i><span>Add Category</span></button>
		</span>
	</form>

	<div class="table-responsive">
		<table class="table sector-table align-middle management-table">
			<thead>
				<tr>
					<th>Code</th>
					<th>Name</th>
					<th class="text-end">Actions</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($categories as $category): ?>
					<?php
					$categoryId = (int) ($category['categoryID'] ?? 0);
					$isArchived = trim((string) ($category['dt_deleted'] ?? '')) !== '';
					$isOfficial = (int) ($category['is_official'] ?? 0) === 1;
					?>
					<tr data-row-archived="<?= $isArchived ? '1' : '0' ?>"<?= $isArchived ? ' class="d-none"' : '' ?>>
						<td><span class="badge bg-light text-dark border"><?= esc((string) ($category['code'] ?? '')) ?></span></td>
						<td><span class="sector-name"><?= esc((string) ($category['name'] ?? '')) ?></span></td>
						<td class="text-end">
							<div class="dropdown actions-menu">
								<button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false" aria-label="Category actions">
									<i class="bi bi-three-dots" aria-hidden="true"></i>
								</button>
								<div class="dropdown-menu dropdown-menu-end">
									<?php if (! $isArchived): ?>
										<button
											class="dropdown-item js-category-modal-open"
											type="button"
											data-category-mode="update"
											data-category-id="<?= esc((string) $categoryId) ?>"
											data-category-code="<?= esc((string) ($category['code'] ?? ''), 'attr') ?>"
											data-category-name="<?= esc((string) ($category['name'] ?? ''), 'attr') ?>"
											data-category-description="<?= esc((string) ($category['description'] ?? ''), 'attr') ?>"
											data-category-official="<?= $isOfficial ? '1' : '0' ?>">
											<i class="bi bi-pencil-square" aria-hidden="true"></i>Edit
										</button>
										<?php if (! $isOfficial): ?>
											<button
												class="dropdown-item text-danger js-category-modal-open"
												type="button"
												data-category-mode="archive"
												data-category-id="<?= esc((string) $categoryId) ?>"
												data-category-code="<?= esc((string) ($category['code'] ?? ''), 'attr') ?>"
												data-category-name="<?= esc((string) ($category['name'] ?? ''), 'attr') ?>">
												<i class="bi bi-archive" aria-hidden="true"></i>Archive
											</button>
											<button
												class="dropdown-item text-danger js-category-modal-open"
												type="button"
												data-category-mode="delete"
												data-category-id="<?= esc((string) $categoryId) ?>"
												data-category-code="<?= esc((string) ($category['code'] ?? ''), 'attr') ?>"
												data-category-name="<?= esc((string) ($category['name'] ?? ''), 'attr') ?>">
												<i class="bi bi-trash" aria-hidden="true"></i>Delete
											</button>
										<?php endif; ?>
									<?php elseif (! $isOfficial): ?>
										<button
											class="dropdown-item text-success js-category-modal-open"
											type="button"
											data-category-mode="restore"
											data-category-id="<?= esc((string) $categoryId) ?>"
											data-category-name="<?= esc((string) ($category['name'] ?? ''), 'attr') ?>">
											<i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>Restore
										</button>
									<?php endif; ?>
								</div>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
				<?php if ($categories === []): ?>
					<tr>
						<td colspan="3" class="sector-empty-state">No category records found.</td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

<?= view('Lookups/category-modal', [
	'existingCodes' => $existingCodes,
]) ?>
