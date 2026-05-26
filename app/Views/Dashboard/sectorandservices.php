<?php
helper('dashboard_view');
extract(sector_and_services_view_data(get_defined_vars()), EXTR_OVERWRITE);
?>

<div class="form-section family-step-panel" data-step="2">
	<div class="row g-3 mb-3">
		<div class="col-12">
			<label class="form-label" for="sectorNameList">Sectors</label>
			<div class="border rounded p-3 bg-white" id="sectorNameList" role="group" aria-label="Sector names">
				<?php foreach ($sectorGroups as $groupLabel => $sectors): ?>
					<div class="fw-semibold small text-muted mb-2"><?= esc((string) $groupLabel) ?></div>
					<?php foreach ($sectors as $sector): ?>
						<?php
						$sectorId = (int) ($sector['sectorID'] ?? 0);
						$shortcode = (string) ($sector['shortcode'] ?? '');
						$label = trim($shortcode . ' ' . (string) ($sector['name'] ?? ''));
						?>
						<label class="form-check mb-1">
							<input class="form-check-input" type="checkbox" name="sectors[]" value="<?= esc((string) $sectorId) ?>" data-name="<?= esc($label) ?>" <?= in_array($sectorId, $selectedSectorIds, true) ? 'checked' : '' ?>>
							<span class="form-check-label"><?= esc($label) ?></span>
						</label>
					<?php endforeach; ?>
				<?php endforeach; ?>
				<?php if ($sectorGroups === []): ?>
					<small class="text-muted">No sectors available.</small>
				<?php endif; ?>
			</div>
			<small class="form-text text-muted">Select one or more sector names.</small>
		</div>
	</div>

	<div class="section-title">
		<span>Services and Programs</span>
	</div>
	<div class="row g-3">
		<?php foreach ($serviceGroups as $category => $services): ?>
			<div class="col-lg-4">
				<div class="assistance-box">
					<h6 class="mb-2"><?= esc((string) $category) ?></h6>
					<div class="service-check-list">
						<?php foreach ($services as $service): ?>
							<?php $serviceId = (string) ($service['serviceID'] ?? ''); ?>
							<?php $serviceInputId = 'service_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', strtolower((string) $category)) . '_' . $serviceId; ?>
							<label class="form-check" for="<?= esc($serviceInputId) ?>">
								<input class="form-check-input" id="<?= esc($serviceInputId) ?>" type="checkbox" name="services[]" value="<?= esc($serviceId) ?>" <?= in_array((int) $serviceId, $selectedServiceIds, true) ? 'checked' : '' ?>>
								<span class="form-check-label"><?= esc((string) ($service['name'] ?? '')) ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
		<?php endforeach; ?>
		<?php if ($serviceGroups === []): ?>
			<div class="col-12">
				<p class="text-muted mb-0">No services available.</p>
			</div>
		<?php endif; ?>
	</div>
</div>
