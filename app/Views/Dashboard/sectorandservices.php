<?php
helper('dashboard_view');
extract(sector_and_services_view_data(get_defined_vars()), EXTR_OVERWRITE);
?>

<div class="form-section family-step-panel" data-step="2">
	<div class="row g-3 mb-3">
		<div class="col-md-4 col-lg-3">
			<label class="form-label" for="sectorCategoryList">Sector</label>
			<div class="border rounded p-2 bg-white" id="sectorCategoryList" role="group" aria-label="Sector categories" data-sector-catalog="<?= esc($sectorCatalogJson, 'attr') ?>">
				<label class="form-check mb-1">
					<input class="form-check-input" type="checkbox" name="sector_categories[]" value="PWD" <?= in_array('PWD', $selectedSectorCategories, true) ? 'checked' : '' ?>>
					<span class="form-check-label">PWD</span>
				</label>
				<label class="form-check mb-1">
					<input class="form-check-input" type="checkbox" name="sector_categories[]" value="SP" <?= in_array('SP', $selectedSectorCategories, true) ? 'checked' : '' ?>>
					<span class="form-check-label">SP</span>
				</label>
				<label class="form-check mb-0">
					<input class="form-check-input" type="checkbox" name="sector_categories[]" value="OSCA" <?= in_array('OSCA', $selectedSectorCategories, true) ? 'checked' : '' ?>>
					<span class="form-check-label">OSCA</span>
				</label>
			</div>
		</div>
		<div class="col-md-8 col-lg-9">
			<label class="form-label" for="sectorNameList">Sector Name</label>
			<input type="hidden" id="sectorID" name="sectorID" required>
			<div class="border rounded p-2 bg-white" id="sectorNameList" role="group" aria-label="Sector names">
				<small class="text-muted">Select one or more sector categories first.</small>
			</div>
			<small class="form-text text-muted">Select one or more sector names.</small>
		</div>
	</div>

	<script type="application/json" id="sectorCatalogData"><?= json_encode($sectorCatalog, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?></script>
	<script type="application/json" id="selectedSectorIdsData"><?= json_encode($selectedSectorIds, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?></script>

	<div class="section-title">
		<span>Services and Programs</span>
	</div>
	<div class="row g-3">
		<?php foreach ($servicesByCategory as $category => $services): ?>
			<div class="col-lg-4">
				<div class="assistance-box">
					<h6 class="mb-2"><?= esc((string) $category) ?></h6>
					<div class="service-check-list">
						<?php foreach ($services as $service): ?>
							<?php $serviceId = (string) ($service['serviceID'] ?? ''); ?>
							<?php $serviceInputId = 'service_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', strtolower((string) $category)) . '_' . $serviceId; ?>
							<label class="form-check" for="<?= esc($serviceInputId) ?>">
								<input class="form-check-input" id="<?= esc($serviceInputId) ?>" type="checkbox" name="service_ids[]" value="<?= esc($serviceId) ?>" <?= in_array((int) $serviceId, $selectedServiceIds, true) ? 'checked' : '' ?>>
								<span class="form-check-label"><?= esc((string) ($service['name'] ?? '')) ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
		<?php endforeach; ?>
		<?php if ($servicesByCategory === []): ?>
			<div class="col-12">
				<p class="text-muted mb-0">No services available.</p>
			</div>
		<?php endif; ?>
	</div>
</div>
