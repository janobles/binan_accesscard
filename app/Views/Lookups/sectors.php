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

// Counts come from the server bundle (whole table), not the 50-row page below.
$activeSectorCount   = (int) ($activeCount ?? 0);
$archivedSectorCount = (int) ($archivedCount ?? 0);
$allSectorCount      = $activeSectorCount + $archivedSectorCount;
$status              = (string) ($status ?? 'active');
$keyword             = (string) ($keyword ?? '');
$listRoute           = (string) ($listRoute ?? 'admin/sectors');

// Builds a page URL preserving the current database keyword + status filter.
$sectorPageUrl = static function (int $targetPage) use ($listRoute, $keyword, $status): string {
    $params = array_filter([
        'q'      => $keyword,
        'status' => $status === 'active' ? '' : $status,
        'page'   => $targetPage > 1 ? (string) $targetPage : '',
    ], static fn ($value): bool => $value !== '');

    return site_url($listRoute) . ($params === [] ? '' : '?' . http_build_query($params));
};
?>

<?php /* Shared lookup-management styling (sector-* classes kept for compatibility). All melbranch hooks preserved:
         data-sector-management-root, #sector-status-select toggle,
         .js-sector-modal-open + data-sector-* attributes, the sector-modal include. */ ?>
<div class="sector-management" data-sector-management-root>
	<?php /* Bar 1 — local quick filter over the rows currently shown (client-side, no reload) + status + Add. */ ?>
	<form class="sector-toolbar sector-lookup-toolbar" role="search" data-lookup-search aria-label="Filter shown sectors">
		<input class="form-control sector-toolbar-search" type="search" data-lookup-search-input placeholder="Filter the sectors shown below" aria-label="Filter shown sectors">
		<select class="form-select sector-status-select" id="sector-status-select" name="status" data-lookup-status-select aria-label="Sector view">
			<option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active (<?= esc((string) $activeSectorCount) ?>)</option>
			<option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>Archive (<?= esc((string) $archivedSectorCount) ?>)</option>
			<option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All (<?= esc((string) $allSectorCount) ?>)</option>
		</select>
		<span id="sector-add-btn-wrap">
			<button class="btn btn-success sector-toolbar-action js-sector-modal-open" type="button" data-sector-mode="create"><i class="bi bi-plus-lg" aria-hidden="true"></i><span>Add Sector</span></button>
		</span>
	</form>

	<?php /* Bar 2 — database search across the whole sector table (server-side GET + pagination). */ ?>
	<form class="sector-toolbar sector-lookup-toolbar sector-database-search" method="get" action="<?= esc(site_url($listRoute), 'attr') ?>" role="search" aria-label="Search the sector database">
		<?php if ($status !== 'active'): ?><input type="hidden" name="status" value="<?= esc($status, 'attr') ?>"><?php endif; ?>
		<input class="form-control sector-toolbar-search" type="search" name="q" value="<?= esc($keyword, 'attr') ?>" placeholder="Search the whole sector database" aria-label="Search the sector database" autocomplete="off">
		<button class="btn btn-outline-success sector-toolbar-action" type="submit"><i class="bi bi-search" aria-hidden="true"></i><span>Search All</span></button>
		<?php if ($keyword !== ''): ?>
			<a class="btn btn-outline-secondary sector-toolbar-action" href="<?= esc(site_url($listRoute) . ($status === 'active' ? '' : '?status=' . $status), 'attr') ?>"><i class="bi bi-x-lg" aria-hidden="true"></i><span>Clear</span></a>
		<?php endif; ?>
	</form>

	<div class="table-responsive">
		<table class="table sector-table align-middle management-table">
			<thead>
				<tr>
					<th>Shortcode</th>
					<th>Name</th>
					<th>Description</th>
					<th>Status</th>
					<th class="text-end">Actions</th>
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
						<td class="text-end">
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
		<div class="lookup-list-footer d-flex flex-wrap justify-content-between align-items-center gap-2 mt-2">
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

<?= view('Lookups/sector-modal', [
	'sectorCategoryOptions' => $sectorCategoryOptions,
	'sectorNextCodeMap' => $sectorNextCodeMap,
	'existingShortcodes' => $existingShortcodes,
]) ?>
