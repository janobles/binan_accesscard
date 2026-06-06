<?php
helper('dashboard_view');
extract(sector_management_view_data(get_defined_vars()), EXTR_OVERWRITE);

// Modal data: category PREFIX dropdown (no numbers) + the next suggested code
// per prefix + every existing code for the inline duplicate check.
$sectorModel = new \App\Models\Lookups\SectorModel();
$sectorPrefixOptions = [];
foreach ($sectorModel->sectorPrefixOptions() as $prefix => $label) {
    // Official prefixes show "CODE - Label"; custom prefixes (label === code)
    // show just the bare code so the dropdown reads cleanly (e.g. "TEST").
    $sectorPrefixOptions[$prefix] = $label === $prefix ? $prefix : $prefix . ' - ' . $label;
}
$sectorNextCodeMap = $sectorModel->nextShortcodeMap();
$existingShortcodes = $sectorModel->existingShortcodes();
$sectorCategories = $sectorModel->customCategories();

$activeSectorCount   = count(array_filter($sectors, static fn ($s) => trim((string) ($s['dt_deleted'] ?? '')) === ''));
$archivedSectorCount = count($sectors) - $activeSectorCount;
?>

<?php /* Jade-style reskin (sector-* classes). All melbranch hooks preserved:
         data-sector-management-root, #btn-sector-active/#btn-sector-archive toggle,
         .js-sector-modal-open + data-sector-* attributes, the sector-modal include. */ ?>
<div class="sector-management" data-sector-management-root>
	<header class="sector-toolbar">
		<div class="sector-status-tabs btn-group" role="group" aria-label="Sector view toggle">
			<button type="button" class="btn btn-success active" id="btn-sector-active" aria-pressed="true">Active (<?= esc((string) $activeSectorCount) ?>)</button>
			<button type="button" class="btn btn-outline-secondary" id="btn-sector-archive" aria-pressed="false">Archive (<?= esc((string) $archivedSectorCount) ?>)</button>
		</div>
		<div class="sector-toolbar-actions d-flex gap-2">
			<button class="btn btn-outline-success" type="button" data-bs-toggle="modal" data-bs-target="#sectorCategoryModal"><i class="bi bi-tags" aria-hidden="true"></i><span>Manage Categories</span></button>
			<span id="sector-add-btn-wrap">
				<button class="btn btn-success js-sector-modal-open" type="button" data-sector-mode="create"><i class="bi bi-plus-lg" aria-hidden="true"></i><span>Add Sector</span></button>
			</span>
		</div>
	</header>

	<form class="searchbar searchbar-single" role="search" data-lookup-search aria-label="Search sectors">
		<input class="form-control" type="search" data-lookup-search-input placeholder="Search sectors by name, code, or description" aria-label="Search sectors">
		<button class="btn btn-success searchbar-action" type="submit"><i class="bi bi-search" aria-hidden="true"></i><span>Search</span></button>
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
					<tr data-row-archived="<?= $isArchived ? '1' : '0' ?>"<?= $isArchived ? ' class="d-none"' : '' ?>>
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
						<td colspan="5" class="sector-empty-state">No sector records found.</td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

<?= view('Lookups/sector-modal', [
	'sectorPrefixOptions' => $sectorPrefixOptions,
	'sectorNextCodeMap' => $sectorNextCodeMap,
	'existingShortcodes' => $existingShortcodes,
]) ?>
<?= view('Lookups/sector-category-modal', [
	'sectorCategories' => $sectorCategories,
]) ?>
