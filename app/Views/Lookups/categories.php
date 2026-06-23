<?php
/*
 * "Manage Categories" management page. Lists the sector categories from the
 * `category` table and lets an admin add/rename/archive/restore/delete them via
 * the shared #categoryActionModal (see category-modal.php + categories-modal.js).
 *
 * Every category is fully editable, archivable, and deletable; the only
 * server-side guard (in Lookups\CategoryController) blocks archiving/deleting a
 * category still linked to sectors. Reuses the Manage Records .records-* layout
 * (managerecord.css) plus the shared lookup badge/action styles (lookupmanagement.css).
 */
helper('dashboard_view');
extract(category_management_view_data(get_defined_vars()), EXTR_OVERWRITE);

// All codes (incl. archived, across every page) for the modal's duplicate check —
// sourced from the model, not the current page below, so the check stays complete.
$existingCodes = array_values(array_unique(array_filter(array_map(
    static fn (array $c): string => strtoupper(trim((string) ($c['code'] ?? ''))),
    (new \App\Models\Lookups\CategoryModel())->getAllIncluding()
))));

// Counts come from the server bundle (whole table), not the current page below.
$activeCategoryCount   = (int) ($activeCount ?? 0);
$archivedCategoryCount = (int) ($archivedCount ?? 0);
$allCategoryCount      = $activeCategoryCount + $archivedCategoryCount;
$status                = (string) ($status ?? 'active');
$keyword               = (string) ($keyword ?? '');
$listRoute             = (string) ($listRoute ?? 'admin/categories');
$perPage               = (int) ($perPage ?? 50);
$perPageOptions        = ($perPageOptions ?? []) ?: [10, 25, 50, 100];

// Builds a page URL preserving the current database keyword + status + page size.
$categoryPageUrl = static function (int $targetPage) use ($listRoute, $keyword, $status, $perPage): string {
    $params = array_filter([
        'q'        => $keyword,
        'status'   => $status === 'active' ? '' : $status,
        'per_page' => $perPage !== 50 ? (string) $perPage : '',
        'page'     => $targetPage > 1 ? (string) $targetPage : '',
    ], static fn ($value): bool => $value !== '');

    return site_url($listRoute) . ($params === [] ? '' : '?' . http_build_query($params));
};

// "Clear" drops the keyword (and resets to page 1) but keeps status + page size.
$categoryClearUrl = static function () use ($listRoute, $status, $perPage): string {
    $params = array_filter([
        'status'   => $status === 'active' ? '' : $status,
        'per_page' => $perPage !== 50 ? (string) $perPage : '',
    ], static fn ($value): bool => $value !== '');

    return site_url($listRoute) . ($params === [] ? '' : '?' . http_build_query($params));
};
?>

<div class="sector-management records-scroll-panel" data-category-management-root>
	<?php /* Database search across the whole category table (server-side GET) + status + Add. */ ?>
	<div class="records-search-panel">
		<form class="records-search-row records-lookup-search" method="get" action="<?= esc(site_url($listRoute), 'attr') ?>" role="search" aria-label="Search the category database">
			<input class="form-control" type="search" name="q" value="<?= esc($keyword, 'attr') ?>" placeholder="Search the whole category database" aria-label="Search the category database" autocomplete="off">
			<select class="form-select records-status-select" id="category-status-select" name="status" data-lookup-status-select aria-label="Category view">
				<option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active (<?= esc((string) $activeCategoryCount) ?>)</option>
				<option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>Archive (<?= esc((string) $archivedCategoryCount) ?>)</option>
				<option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All (<?= esc((string) $allCategoryCount) ?>)</option>
			</select>
			<?php if ($perPage !== 50): ?><input type="hidden" name="per_page" value="<?= esc((string) $perPage, 'attr') ?>"><?php endif; ?>
			<a class="btn btn-outline-secondary records-search-action" href="<?= esc($categoryClearUrl(), 'attr') ?>"><i class="bi bi-x-lg" aria-hidden="true"></i><span>Clear</span></a>
			<button class="btn btn-outline-success records-search-action" type="submit"><i class="bi bi-search" aria-hidden="true"></i><span>Search All</span></button>
			<button class="btn btn-primary records-search-action js-category-modal-open" type="button" data-category-mode="create"><i class="bi bi-plus-lg" aria-hidden="true"></i><span>Add Category</span></button>
		</form>
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
				<input class="form-control form-control-sm" type="search" id="categoryLocalSearch" data-lookup-search-input placeholder="Type to filter..." autocomplete="off" aria-label="Filter shown categories">
			</form>
		</div>
	</div>

	<div class="table-responsive">
		<table class="table table-sm manage-record-table align-middle">
			<thead>
				<tr>
					<th>Name</th>
					<th>Code</th>
					<th class="text-end">Actions</th>
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
											data-category-name="<?= esc((string) ($category['name'] ?? ''), 'attr') ?>">
											<i class="bi bi-pencil-square" aria-hidden="true"></i>Edit
										</button>
										<button
											class="dropdown-item text-danger js-category-modal-open"
											type="button"
											data-category-mode="archive"
											data-category-id="<?= esc((string) $categoryId) ?>"
											data-category-code="<?= esc((string) ($category['code'] ?? ''), 'attr') ?>"
											data-category-name="<?= esc((string) ($category['name'] ?? ''), 'attr') ?>">
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
						<td colspan="3" class="sector-empty-state"><?= $keyword !== '' ? 'No categories match your search.' : 'No category records found.' ?></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

	<?php if (($totalRows ?? 0) > 0): ?>
		<div class="lookup-list-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
			<span class="text-muted small">Showing <?= esc((string) $fromRecord) ?>–<?= esc((string) $toRecord) ?> of <?= esc((string) $totalRows) ?></span>
			<?php if (($totalPages ?? 1) > 1): ?>
				<div class="d-flex gap-2">
					<a class="btn btn-outline-secondary btn-sm<?= $page <= 1 ? ' disabled' : '' ?>" href="<?= esc($categoryPageUrl(max(1, $page - 1)), 'attr') ?>" aria-disabled="<?= $page <= 1 ? 'true' : 'false' ?>">Previous</a>
					<span class="btn btn-sm disabled">Page <?= esc((string) $page) ?> of <?= esc((string) $totalPages) ?></span>
					<a class="btn btn-outline-secondary btn-sm<?= $page >= $totalPages ? ' disabled' : '' ?>" href="<?= esc($categoryPageUrl(min($totalPages, $page + 1)), 'attr') ?>" aria-disabled="<?= $page >= $totalPages ? 'true' : 'false' ?>">Next</a>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>

<?= view('Lookups/category-modal', [
	'existingCodes' => $existingCodes,
]) ?>
