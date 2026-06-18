<?php
helper('dashboard_view');
extract(sector_management_view_data(get_defined_vars()), EXTR_OVERWRITE);

// Add Sector modal data: category dropdown (categoryID => "CODE - Name") from the
// `category` table, the next suggested sector code per category, and every existing
// code for the inline duplicate check.
$sectorModel = new \App\Models\Lookups\SectorModel();
$categoryModel = new \App\Models\Lookups\CategoryModel();
$sectorCategoryOptions = [];
$sectorNextCodeMap = [];
foreach ($categoryModel->getActive() as $category) {
    $categoryId = (int) ($category['categoryID'] ?? 0);
    $code = (string) ($category['code'] ?? '');
    $name = (string) ($category['name'] ?? '');
    $sectorCategoryOptions[$categoryId] = ($name === '' || $name === $code) ? $code : $code . ' - ' . $name;
    $sectorNextCodeMap[$categoryId] = $categoryModel->nextSectorCodeFor($code);
}
$existingShortcodes = $sectorModel->existingShortcodes();

// Counts come from the server bundle (whole table), not the current page below.
$activeSectorCount   = (int) ($activeCount ?? 0);
$archivedSectorCount = (int) ($archivedCount ?? 0);
$allSectorCount      = $activeSectorCount + $archivedSectorCount;
$status              = (string) ($status ?? 'all');
$keyword             = (string) ($keyword ?? '');
$listRoute           = (string) ($listRoute ?? 'admin/sectors');
$perPage             = (int) ($perPage ?? 50);
$perPageOptions      = ($perPageOptions ?? []) ?: [10, 25, 50, 100];
// Read-only roles (Viewer) see the list without Add / Edit / Archive / Restore.
// Defaults true so the admin/developer sector page is unaffected.
$canManage           = (bool) ($canManage ?? true);

// Builds a page URL preserving the current database keyword + status + page size.
$sectorPageUrl = static function (int $targetPage) use ($listRoute, $keyword, $status, $perPage): string {
    $params = array_filter([
        'q'        => $keyword,
        'status'   => $status === 'all' ? '' : $status,
        'per_page' => $perPage !== 50 ? (string) $perPage : '',
        'page'     => $targetPage > 1 ? (string) $targetPage : '',
    ], static fn ($value): bool => $value !== '');

    return site_url($listRoute) . ($params === [] ? '' : '?' . http_build_query($params));
};

// "Clear" drops the keyword (and resets to page 1) but keeps status + page size.
$sectorClearUrl = static function () use ($listRoute, $status, $perPage): string {
    $params = array_filter([
        'status'   => $status === 'all' ? '' : $status,
        'per_page' => $perPage !== 50 ? (string) $perPage : '',
    ], static fn ($value): bool => $value !== '');

    return site_url($listRoute) . ($params === [] ? '' : '?' . http_build_query($params));
};
?>

<?php /* Reuses the Manage Records .records-* layout (managerecord.css). All melbranch hooks preserved:
         data-sector-management-root, #sector-status-select (data-lookup-status-select),
         data-lookup-search local filter, .js-sector-modal-open + data-sector-* attributes, the sector-modal include. */ ?>
<div class="sector-management records-scroll-panel" data-sector-management-root>
	<?php /* Database search across the whole sector table (server-side GET) + status + Add. */ ?>
	<div class="records-search-panel">
		<form class="records-search-row records-lookup-search" method="get" action="<?= esc(site_url($listRoute), 'attr') ?>" role="search" aria-label="Search the sector database">
			<input class="form-control" type="search" name="q" value="<?= esc($keyword, 'attr') ?>" placeholder="Search the whole sector database" aria-label="Search the sector database" autocomplete="off">
			<select class="form-select records-status-select" id="sector-status-select" name="status" data-lookup-status-select aria-label="Sector view">
				<option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Select Status</option>
				<option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active (<?= esc((string) $activeSectorCount) ?>)</option>
				<option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>Archive (<?= esc((string) $archivedSectorCount) ?>)</option>
			</select>
			<?php if ($perPage !== 50): ?><input type="hidden" name="per_page" value="<?= esc((string) $perPage, 'attr') ?>"><?php endif; ?>
			<a class="btn btn-outline-secondary records-search-action" href="<?= esc($sectorClearUrl(), 'attr') ?>"><i class="bi bi-x-lg" aria-hidden="true"></i><span>Clear</span></a>
			<button class="btn btn-outline-success records-search-action" type="submit"><i class="bi bi-search" aria-hidden="true"></i><span>Search All</span></button>
			<?php if ($canManage): ?>
			<button class="btn btn-primary records-search-action js-sector-modal-open" type="button" data-sector-mode="create"><i class="bi bi-plus-lg" aria-hidden="true"></i><span>Add Sector</span></button>
			<?php endif; ?>
		</form>
	</div>

	<?php /* Controls row: page size (server) + local "Search:" live filter (client-side, no reload). */ ?>
	<div class="table-meta">
		<div class="records-table-controls">
			<form class="records-page-size-form" method="get" action="<?= esc(site_url($listRoute), 'attr') ?>">
				<?php if ($keyword !== ''): ?><input type="hidden" name="q" value="<?= esc($keyword, 'attr') ?>"><?php endif; ?>
				<?php if ($status !== 'all'): ?><input type="hidden" name="status" value="<?= esc($status, 'attr') ?>"><?php endif; ?>
				<label for="sectorPerPage">Show</label>
				<select class="form-select form-select-sm" id="sectorPerPage" name="per_page" onchange="this.form.submit()">
					<?php foreach ($perPageOptions as $option): ?>
						<option value="<?= esc((string) $option, 'attr') ?>" <?= $perPage === (int) $option ? 'selected' : '' ?>><?= esc((string) $option) ?></option>
					<?php endforeach; ?>
				</select>
				<span>entries</span>
			</form>
			<form class="records-table-search-form" role="search" data-lookup-search aria-label="Filter shown sectors">
				<label for="sectorLocalSearch">Search:</label>
				<input class="form-control form-control-sm" type="search" id="sectorLocalSearch" data-lookup-search-input placeholder="Type to filter..." autocomplete="off" aria-label="Filter shown sectors">
			</form>
		</div>
	</div>

	<div class="table-responsive">
		<table class="table table-sm manage-record-table align-middle">
			<thead>
				<tr>
					<th>Shortcode</th>
					<th>Name</th>
					<th>Description</th>
					<th>Status</th>
					<?php if ($canManage): ?><th class="text-end">Actions</th><?php endif; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($sectors as $sector): ?>
					<?php $sectorId = (int) ($sector['sectorID'] ?? 0); ?>
					<?php $isArchived = trim((string) ($sector['dt_deleted'] ?? '')) !== ''; ?>
					<tr data-row-archived="<?= $isArchived ? '1' : '0' ?>">
						<td><span class="badge bg-light text-dark border"><?= esc((string) ($sector['shortcode'] ?? '')) ?></span></td>
						<td><span class="sector-name"><?= esc((string) ($sector['name'] ?? '')) ?></span></td>
						<td><span class="text-trim d-inline-block"><?= esc((string) ($sector['description'] ?? '')) ?></span></td>
						<td><span class="sector-status-badge <?= $isArchived ? 'sector-status-archived' : 'sector-status-active' ?>"><?= $isArchived ? 'Archived' : 'Active' ?></span></td>
						<?php if ($canManage): ?><td class="text-end">
							<div class="dropdown actions-menu">
								<button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false" aria-label="Sector actions">
									<i class="bi bi-three-dots" aria-hidden="true"></i>
								</button>
								<div class="dropdown-menu dropdown-menu-end">
									<?php if (! $isArchived): ?>
										<button
											class="dropdown-item js-sector-modal-open"
											type="button"
											data-sector-mode="update"
											data-sector-id="<?= esc((string) $sectorId) ?>"
											data-sector-category-id="<?= esc((string) ($sector['categoryID'] ?? ''), 'attr') ?>"
											data-sector-shortcode="<?= esc((string) ($sector['shortcode'] ?? ''), 'attr') ?>"
											data-sector-name="<?= esc((string) ($sector['name'] ?? ''), 'attr') ?>"
											data-sector-description="<?= esc((string) ($sector['description'] ?? ''), 'attr') ?>">
											<i class="bi bi-pencil-square" aria-hidden="true"></i>Edit
										</button>
										<button
											class="dropdown-item text-danger js-sector-modal-open"
											type="button"
											data-sector-mode="archive"
											data-sector-id="<?= esc((string) $sectorId) ?>"
											data-sector-shortcode="<?= esc((string) ($sector['shortcode'] ?? ''), 'attr') ?>"
											data-sector-name="<?= esc((string) ($sector['name'] ?? ''), 'attr') ?>"
											data-sector-description="<?= esc((string) ($sector['description'] ?? ''), 'attr') ?>">
											<i class="bi bi-archive" aria-hidden="true"></i>Archive
										</button>
									<?php else: ?>
										<button
											class="dropdown-item text-success js-sector-modal-open"
											type="button"
											data-sector-mode="restore"
											data-sector-id="<?= esc((string) $sectorId) ?>"
											data-sector-name="<?= esc((string) ($sector['name'] ?? ''), 'attr') ?>">
											<i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>Restore
										</button>
									<?php endif; ?>
								</div>
							</div>
						</td>
						<?php endif; ?>
					</tr>
				<?php endforeach; ?>
				<?php if ($sectors === []): ?>
					<tr>
						<td colspan="5" class="sector-empty-state"><?= $keyword !== '' ? 'No sectors match your search.' : 'No sector records found.' ?></td>
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
					<a class="btn btn-outline-secondary btn-sm<?= $page <= 1 ? ' disabled' : '' ?>" href="<?= esc($sectorPageUrl(max(1, $page - 1)), 'attr') ?>" aria-disabled="<?= $page <= 1 ? 'true' : 'false' ?>">Previous</a>
					<span class="btn btn-sm disabled">Page <?= esc((string) $page) ?> of <?= esc((string) $totalPages) ?></span>
					<a class="btn btn-outline-secondary btn-sm<?= $page >= $totalPages ? ' disabled' : '' ?>" href="<?= esc($sectorPageUrl(min($totalPages, $page + 1)), 'attr') ?>" aria-disabled="<?= $page >= $totalPages ? 'true' : 'false' ?>">Next</a>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>

<?php if ($canManage): ?>
<?= view('Lookups/sector-modal', [
	'sectorCategoryOptions' => $sectorCategoryOptions,
	'sectorNextCodeMap' => $sectorNextCodeMap,
	'existingShortcodes' => $existingShortcodes,
]) ?>
<?php endif; ?>
