<?php
helper('dashboard_view');
extract(sector_management_view_data(get_defined_vars()), EXTR_OVERWRITE);

$status = (string) ($status ?? 'active') === 'archived' ? 'archived' : 'active';
$canRestore = (bool) ($canRestore ?? false);
$isArchivedView = $status === 'archived';

// Modal data: category PREFIX dropdown (no numbers) + the next suggested code
// per prefix + every existing code for the inline duplicate check.
$sectorModel = new \App\Models\SectorModel();
$sectorPrefixOptions = [];
foreach (\App\Support\FamilyProfilingFormV2::SECTOR_CATEGORIES as $prefix => $label) {
    if ($prefix === 'OTHER') {
        continue;
    }
    $sectorPrefixOptions[$prefix] = $prefix . ' — ' . $label;
}
$sectorNextCodeMap = $sectorModel->nextShortcodeMap();
$existingShortcodes = $sectorModel->existingShortcodes();
?>

<div class="panel mb-3" data-sector-management-root>
	<div class="section-title mt-0">
		<span><?= $isArchivedView ? 'Archived Sectors' : 'Sector Management' ?></span>
		<?php if (! $isArchivedView): ?>
			<button class="btn btn-primary btn-sm js-sector-modal-open" type="button" data-sector-mode="create">Add Sector</button>
		<?php endif; ?>
	</div>

	<?php if ($canRestore): ?>
		<div class="d-flex gap-2 mb-3">
			<a class="btn btn-sm <?= $status === 'active' ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= esc(site_url('admin/sectors'), 'attr') ?>">Active</a>
			<a class="btn btn-sm <?= $status === 'archived' ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= esc(site_url('admin/sectors?status=archived'), 'attr') ?>">Archived</a>
		</div>
	<?php endif; ?>

	<div class="table-responsive">
		<table class="table table-sm align-middle management-table">
			<thead>
				<tr>
					<th>Shortcode</th>
					<th>Name</th>
					<th>Description</th>
					<?php if ($isArchivedView): ?>
						<th>Archived On</th>
					<?php endif; ?>
					<th class="text-end">Actions</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($sectors as $sector): ?>
					<?php $sectorId = (int) ($sector['sectorID'] ?? 0); ?>
					<tr>
						<td><?= esc((string) ($sector['shortcode'] ?? '')) ?></td>
						<td><?= esc((string) ($sector['name'] ?? '')) ?></td>
						<td><?= esc((string) ($sector['description'] ?? '')) ?></td>
						<?php if ($isArchivedView): ?>
							<?php $archivedOn = (string) ($sector['dt_deleted'] ?? ''); ?>
							<td class="text-muted small"><?= $archivedOn !== '' ? esc(date('M j, Y g:i A', strtotime($archivedOn))) : '—' ?></td>
						<?php endif; ?>
						<td class="text-end">
							<div class="management-row-actions">
								<?php if ($isArchivedView): ?>
									<form class="d-inline js-management-delete-form" method="post" action="<?= site_url('admin/sectors/restore/' . $sectorId) ?>" data-confirm-message="Restore &quot;<?= esc((string) ($sector['name'] ?? 'this sector'), 'attr') ?>&quot; to the active list?">
										<?= csrf_field() ?>
										<button class="btn btn-outline-success btn-sm" type="submit">Restore</button>
									</form>
								<?php else: ?>
									<button
										class="btn btn-outline-primary btn-sm js-sector-modal-open"
										type="button"
										data-sector-mode="update"
										data-sector-id="<?= esc((string) $sectorId) ?>"
										data-sector-shortcode="<?= esc((string) ($sector['shortcode'] ?? ''), 'attr') ?>"
										data-sector-name="<?= esc((string) ($sector['name'] ?? ''), 'attr') ?>"
										data-sector-description="<?= esc((string) ($sector['description'] ?? ''), 'attr') ?>">
										Edit
									</button>
									<button
										class="btn btn-outline-danger btn-sm js-sector-modal-open"
										type="button"
										data-sector-mode="archive"
										data-sector-id="<?= esc((string) $sectorId) ?>"
										data-sector-shortcode="<?= esc((string) ($sector['shortcode'] ?? ''), 'attr') ?>"
										data-sector-name="<?= esc((string) ($sector['name'] ?? ''), 'attr') ?>"
										data-sector-description="<?= esc((string) ($sector['description'] ?? ''), 'attr') ?>">
										Archive
									</button>
								<?php endif; ?>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
				<?php if ($sectors === []): ?>
					<tr>
						<td colspan="<?= $isArchivedView ? 5 : 4 ?>" class="text-center text-muted"><?= $isArchivedView ? 'No archived sectors found.' : 'No sector records found.' ?></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

<?php if (! $isArchivedView): ?>
	<?= view('Dashboard/Sectors and Services/sector-modal', [
		'sectorPrefixOptions' => $sectorPrefixOptions,
		'sectorNextCodeMap' => $sectorNextCodeMap,
		'existingShortcodes' => $existingShortcodes,
	]) ?>
<?php endif; ?>
