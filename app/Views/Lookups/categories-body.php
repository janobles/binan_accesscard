<?php
/**
 * Manage Categories body: search/toolbar rows + lookup table.
 * Rendered inside components/card by Lookups/categories.php (bodyData is that
 * view's get_defined_vars(), matching its existing extract() convention).
 */
?>
<div class="records-search-panel">
		<form class="records-search-row records-lookup-search" method="get" action="<?= esc(site_url($listRoute), 'attr') ?>" role="search" aria-label="Search the category database" data-records-filter-form data-records-pills="categoryFilterPills">
			<input class="form-control" type="search" name="q" value="<?= esc($keyword, 'attr') ?>" placeholder="Search entire database..." aria-label="Search the category database" autocomplete="off">
			<div class="dropdown" data-records-panel>
				<button class="<?= btn('filter') ?> dropdown-toggle w-100" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
					<i class="bi bi-funnel" aria-hidden="true"></i> Filters
				</button>
				<div class="dropdown-menu records-filter-panel p-3">
					<div data-records-filter="status" data-records-group-label="Status">
						<div class="fw-semibold small text-uppercase text-muted mb-1">Status</div>
						<label class="form-check d-flex align-items-center gap-2 py-1" data-records-option>
							<input class="form-check-input m-0" type="radio" name="status" value="active" data-records-default <?= $status === 'active' ? 'checked' : '' ?>>
							<span class="form-check-label small">Active (<?= esc((string) $activeCategoryCount) ?>)</span>
						</label>
						<label class="form-check d-flex align-items-center gap-2 py-1" data-records-option>
							<input class="form-check-input m-0" type="radio" name="status" value="archived" data-records-pill-label="Archived" <?= $status === 'archived' ? 'checked' : '' ?>>
							<span class="form-check-label small">Archived (<?= esc((string) $archivedCategoryCount) ?>)</span>
						</label>
						<label class="form-check d-flex align-items-center gap-2 py-1" data-records-option>
							<input class="form-check-input m-0" type="radio" name="status" value="all" data-records-pill-label="All" <?= $status === 'all' ? 'checked' : '' ?>>
							<span class="form-check-label small">All (<?= esc((string) $allCategoryCount) ?>)</span>
						</label>
					</div>
				</div>
			</div>
			<?php if ($perPage !== 50): ?><input type="hidden" name="per_page" value="<?= esc((string) $perPage, 'attr') ?>"><?php endif; ?>
			<button class="<?= btn('search') ?> records-search-action" type="submit"><i class="bi bi-search" aria-hidden="true"></i><span>Search</span></button>
			<a class="<?= btn('clear') ?> records-search-action" href="<?= esc($categoryClearUrl(), 'attr') ?>"><i class="bi bi-x-lg" aria-hidden="true"></i><span>Clear</span></a>
			<button class="<?= btn('add') ?> records-search-action js-category-modal-open" type="button" data-category-mode="create"><i class="bi bi-plus-lg" aria-hidden="true"></i><span>Add Category</span></button>
		</form>
		<?= view('components/filter_pills', ['id' => 'categoryFilterPills']) ?>
	</div>

	<?php /* Controls row: page size (server) + local "Search:" live filter (client-side, no reload). */ ?>
	<div class="table-meta">
		<div class="records-table-controls">
			<form class="records-page-size-form" method="get" action="<?= esc(site_url($listRoute), 'attr') ?>">
				<?php if ($keyword !== ''): ?><input type="hidden" name="q" value="<?= esc($keyword, 'attr') ?>"><?php endif; ?>
				<?php if ($status !== 'active'): ?><input type="hidden" name="status" value="<?= esc($status, 'attr') ?>"><?php endif; ?>
				<label for="categoryPerPage">Show</label>
				<select class="form-select form-select-sm" id="categoryPerPage" name="per_page" onchange="this.form.submit()">
					<?php foreach ($perPageOptions as $option): ?>
						<option value="<?= esc((string) $option, 'attr') ?>" <?= $perPage === (int) $option ? 'selected' : '' ?>><?= esc((string) $option) ?></option>
					<?php endforeach; ?>
				</select>
				<span>entries</span>
			</form>
			<form class="records-table-search-form" role="search" data-lookup-search aria-label="Filter shown categories">
				<label for="categoryLocalSearch">Search:</label>
				<input class="form-control form-control-sm" type="search" id="categoryLocalSearch" data-lookup-search-input placeholder="Filter loaded results..." autocomplete="off" aria-label="Filter shown categories">
			</form>
		</div>
	</div>

	<div class="table-responsive">
		<table class="table manage-record-table align-middle lookup-management-table lookup-management-table--categories">
			<thead>
				<tr>
					<th class="lookup-col-name">Name</th>
					<th class="lookup-col-code">Code</th>
					<th class="lookup-col-description">Description</th>
					<th class="lookup-col-actions text-end">Actions</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($categories as $category): ?>
					<?php
					$categoryId = (int) ($category['categoryID'] ?? 0);
					$isArchived = trim((string) ($category['dt_deleted'] ?? '')) !== '';
					?>
					<tr data-row-archived="<?= $isArchived ? '1' : '0' ?>">
						<td><span class="sector-name"><?= esc((string) ($category['name'] ?? '')) ?></span></td>
						<td><span class="badge bg-light text-dark border"><?= esc((string) ($category['code'] ?? '')) ?></span></td>
						<td><span class="text-trim d-inline-block"><?= esc((string) ($category['description'] ?? '')) ?></span></td>
						<td class="text-end">
							<div class="dropdown actions-menu">
								<button class="btn btn-outline-secondary btn-sm actions-menu-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false" aria-label="Category actions">
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
											data-category-description="<?= esc((string) ($category['description'] ?? ''), 'attr') ?>">
											<i class="bi bi-pencil-square" aria-hidden="true"></i>Edit
										</button>
										<button
											class="dropdown-item text-danger js-category-modal-open"
											type="button"
											data-category-mode="archive"
											data-category-id="<?= esc((string) $categoryId) ?>"
											data-category-code="<?= esc((string) ($category['code'] ?? ''), 'attr') ?>"
											data-category-name="<?= esc((string) ($category['name'] ?? ''), 'attr') ?>"
											data-category-description="<?= esc((string) ($category['description'] ?? ''), 'attr') ?>">
											<i class="bi bi-archive" aria-hidden="true"></i>Archive
										</button>
									<?php else: ?>
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
						<td colspan="4" class="sector-empty-state"><?= $keyword !== '' ? 'No categories match your search.' : 'No category records found.' ?></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
