<?php
helper('dashboard_view');
extract(sector_and_services_view_data(get_defined_vars()), EXTR_OVERWRITE);
$sectorCategoryLabels = \App\Support\FamilyProfilingFormV2::SECTOR_CATEGORIES;
$sectorCategoryKeys = array_values(array_unique(array_merge(
	array_keys($sectorCategoryLabels),
	array_keys($sectorCatalog),
	$selectedSectorCategories
)));
?>

<div class="form-section family-step-panel" data-step="2">
	<div class="row g-3 mb-3">
		<div class="col-12">
			<div class="section-title mt-0">
				<span>Sectors</span>
			</div>
			<div class="family-form-hidden" id="sectorCategoryList" data-sector-catalog="<?= esc($sectorCatalogJson, 'attr') ?>" data-auto-select-all="1">
				<?php foreach ($sectorCategoryKeys as $categoryKey): ?>
					<?php $categoryKey = (string) $categoryKey; ?>
					<?php if ($categoryKey === '') { continue; } ?>
					<input type="checkbox" name="sector_categories[]" value="<?= esc($categoryKey) ?>" checked>
				<?php endforeach; ?>
			</div>
			<label class="form-label" for="sectorNameList">Sector checklist</label>
			<input type="hidden" id="sectorID" name="sectorID" required>
			<div class="border rounded p-2 bg-white" id="sectorNameList" role="group" aria-label="Sector names">
				<small class="text-muted">Loading sectors...</small>
			</div>
			<small class="form-text text-muted">Select one or more sectors for the record head.</small>
		</div>
	</div>

	<script type="application/json" id="sectorCatalogData"><?= json_encode($sectorCatalog, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?></script>
	<script type="application/json" id="selectedSectorIdsData"><?= json_encode($selectedSectorIds, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?></script>

	<div class="section-title">
		<span>Services and Programs Available</span>
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
							<?php $serviceDescription = trim((string) ($service['description'] ?? '')); ?>
							<label class="form-check" for="<?= esc($serviceInputId) ?>">
								<input class="form-check-input" id="<?= esc($serviceInputId) ?>" type="checkbox" name="service_ids[]" value="<?= esc($serviceId) ?>" <?= in_array((int) $serviceId, $selectedServiceIds, true) ? 'checked' : '' ?>>
								<span class="form-check-label">
									<?= esc((string) ($service['name'] ?? '')) ?>
									<?php if ($serviceDescription !== ''): ?>
										<small class="d-block text-muted"><?= esc($serviceDescription) ?></small>
									<?php endif; ?>
								</span>
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
