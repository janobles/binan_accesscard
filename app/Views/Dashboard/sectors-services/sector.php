<?php
helper('dashboard_view');
extract(sector_management_view_data(get_defined_vars()), EXTR_OVERWRITE);

// Modal data: category PREFIX dropdown (no numbers) + the next suggested code
// per prefix + every existing code for the inline duplicate check.
$sectorModel = new \App\Models\Lookups\SectorModel();
$sectorPrefixOptions = [];
foreach (\App\Support\FamilyProfilingFormV2::SECTOR_CATEGORIES as $prefix => $label) {
    if ($prefix === 'OTHER') {
        continue;
    }
    $sectorPrefixOptions[$prefix] = $prefix . ' - ' . $label;
}
$sectorNextCodeMap = $sectorModel->nextShortcodeMap();
$existingShortcodes = $sectorModel->existingShortcodes();

$activeSectorCount   = count(array_filter($sectors, static fn ($s) => trim((string) ($s['dt_deleted'] ?? '')) === ''));
$archivedSectorCount = count($sectors) - $activeSectorCount;
?>

<div class="panel mb-3" data-sector-management-root>
	<div class="section-title mt-0">
		<span>Sector Management</span>
		<div class="d-flex align-items-center gap-2 flex-wrap">
			<div class="btn-group btn-group-sm" role="group" aria-label="Sector view toggle">
				<button type="button" class="btn btn-outline-primary active" id="btn-sector-active" aria-pressed="true">Active (<?= esc((string) $activeSectorCount) ?>)</button>
				<button type="button" class="btn btn-outline-primary" id="btn-sector-archive" aria-pressed="false">Archive (<?= esc((string) $archivedSectorCount) ?>)</button>
			</div>
			<span id="sector-add-btn-wrap">
				<button class="btn btn-primary btn-sm js-sector-modal-open" type="button" data-sector-mode="create"><i class="bi bi-plus-lg" aria-hidden="true"></i>Add Sector</button>
			</span>
		</div>
	</div>

	<div class="table-responsive">
		<table class="table table-sm align-middle management-table">
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
						<td><span class="status-pill is-muted"><?= esc((string) ($sector['shortcode'] ?? '')) ?></span></td>
						<td><span class="entity-title"><?= esc((string) ($sector['name'] ?? '')) ?></span></td>
						<td><span class="text-trim d-inline-block"><?= esc((string) ($sector['description'] ?? '')) ?></span></td>
						<td><span class="status-pill <?= $isArchived ? 'is-danger' : 'is-active' ?>"><?= $isArchived ? 'Archived' : 'Active' ?></span></td>
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
						<td colspan="5" class="text-center text-muted">No sector records found.</td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

<?= view('Dashboard/sectors-services/sector-modal', [
	'sectorPrefixOptions' => $sectorPrefixOptions,
	'sectorNextCodeMap' => $sectorNextCodeMap,
	'existingShortcodes' => $existingShortcodes,
]) ?>
