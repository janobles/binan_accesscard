<?php
helper('dashboard_view');
extract(sector_management_view_data(get_defined_vars()), EXTR_OVERWRITE);
$sectorShortcodeOptions = $sectorShortcodeOptions !== []
    ? $sectorShortcodeOptions
    : array_values(array_filter(
        array_keys(\App\Support\FamilyProfilingFormV2::SECTOR_CATEGORIES),
        static fn (string $shortcode): bool => $shortcode !== 'OTHER'
    ));
?>

<div class="panel mb-3" data-sector-management-root>
	<div class="section-title mt-0">
		<span>Sector Management</span>
		<button class="btn btn-primary btn-sm js-sector-modal-open" type="button" data-sector-mode="create">Add Sector</button>
	</div>

	<div class="table-responsive">
		<table class="table table-sm align-middle management-table">
			<thead>
				<tr>
					<th>Shortcode</th>
					<th>Name</th>
					<th>Description</th>
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
						<td class="text-end">
							<div class="management-row-actions">
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
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
				<?php if ($sectors === []): ?>
					<tr>
						<td colspan="4" class="text-center text-muted">No sector records found.</td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

<?= view('Dashboard/Sectors and Services/sector-modal', [
	'sectorShortcodeOptions' => $sectorShortcodeOptions,
]) ?>
