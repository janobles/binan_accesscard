<?php
/*
 * "Manage Categories" management page. Lists the standalone SERVICE categories from
 * the `category` table (FA/SWPS/EDA — the ones with no matching sector; a sector acts
 * as its own service category) and lets an admin add/rename/archive/restore them via
 * the shared #categoryActionModal (see category-modal.php + categories-modal.js).
 *
 * Server-side guards (Lookups\CategoryController): a category may not duplicate a sector
 * (code or name), and one still used by an active service cannot be archived. Reuses the
 * Manage Records .records-* layout (managerecord.css) plus the shared lookup badge/action
 * styles (lookupmanagement.css).
 */
helper('dashboard_view');
// category_management_view_data() also supplies $existingCodes (all codes incl.
// archived, for the modal's duplicate check) so this view stays model-free.
extract(category_management_view_data(get_defined_vars()), EXTR_OVERWRITE);

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

<<<<<<< HEAD
<div class="sector-management records-scroll-panel" data-category-management-root>
	<?php /* Database search across the whole category table (server-side GET) + status + Add. */ ?>
	<div class="records-search-panel">
		<form class="records-search-row records-lookup-search" method="get" action="<?= esc(site_url($listRoute), 'attr') ?>" role="search" aria-label="Search the category database">
			<input class="form-control" type="search" name="q" value="<?= esc($keyword, 'attr') ?>" placeholder="Search the whole category database" aria-label="Search the category database" autocomplete="off">
			<select class="form-select records-status-select" id="category-status-select" name="status" data-lookup-status-select aria-label="Category view">
				<option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Select Status</option>
				<option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active (<?= esc((string) $activeCategoryCount) ?>)</option>
				<option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>Archive (<?= esc((string) $archivedCategoryCount) ?>)</option>
			</select>
			<?php if ($perPage !== 50): ?><input type="hidden" name="per_page" value="<?= esc((string) $perPage, 'attr') ?>"><?php endif; ?>
			<div class="btn-toolbar" role="toolbar" aria-label="Category actions">
				<div class="btn-group" role="group" aria-label="Search and category actions">
					<a class="btn btn-outline-secondary records-search-action" href="<?= esc($categoryClearUrl(), 'attr') ?>"><span>Clear</span></a>
					<button class="btn btn-outline-success records-search-action" type="submit"><span>Search</span></button>
					<button class="btn btn-primary records-search-action js-category-modal-open" type="button" data-category-mode="create"><span>Add Category</span></button>
				</div>
			</div>
		</form>
	</div>

	<?php /* Controls row: page size (server) + local "Search:" live filter (client-side, no reload). */ ?>
	<div class="table-meta">
		<div class="records-table-controls">
			<form class="records-page-size-form" method="get" action="<?= esc(site_url($listRoute), 'attr') ?>">
				<?php if ($keyword !== ''): ?><input type="hidden" name="q" value="<?= esc($keyword, 'attr') ?>"><?php endif; ?>
				<?php if ($status !== 'all'): ?><input type="hidden" name="status" value="<?= esc($status, 'attr') ?>"><?php endif; ?>
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
									Actions
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
											Edit
										</button>
										<button
											class="dropdown-item text-danger js-category-modal-open"
											type="button"
											data-category-mode="archive"
											data-category-id="<?= esc((string) $categoryId) ?>"
											data-category-code="<?= esc((string) ($category['code'] ?? ''), 'attr') ?>"
											data-category-name="<?= esc((string) ($category['name'] ?? ''), 'attr') ?>">
											Archive
										</button>
									<?php else: ?>
										<button
											class="dropdown-item text-success js-category-modal-open"
											type="button"
											data-category-mode="restore"
											data-category-id="<?= esc((string) $categoryId) ?>"
											data-category-name="<?= esc((string) ($category['name'] ?? ''), 'attr') ?>">
											Restore
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
=======
<?php
$categoryFooter = ($totalRows ?? 0) > 0 ? view('components/table_footer', [
    'fromRecord' => $fromRecord,
    'toRecord' => $toRecord,
    'totalRows' => $totalRows,
    'page' => $page,
    'totalPages' => $totalPages,
    'prevUrl' => $categoryPageUrl(max(1, $page - 1)),
    'nextUrl' => $categoryPageUrl(min($totalPages, $page + 1)),
]) : null;
?>
<?= view('components/card', [
    'icon' => 'tags-fill',
    'title' => 'Manage Categories',
    'cardClass' => 'sector-management records-scroll-panel',
    'attrs' => 'data-category-management-root',
    'bodyView' => 'Lookups/categories-body',
    'bodyData' => get_defined_vars(),
    'footer' => $categoryFooter,
]) ?>
>>>>>>> 37b227b891c97c89790df56f4936d5278dde408a

<?= view('Lookups/category-modal', [
	'existingCodes' => $existingCodes,
]) ?>
