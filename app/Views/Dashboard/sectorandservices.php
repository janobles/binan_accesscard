<?php
use App\Libraries\ViewFormatter;

helper('dashboard_view');
extract(sector_and_services_view_data(get_defined_vars()), EXTR_OVERWRITE);

/*
 * Step 2 of the family wizard. Sectors are listed directly, grouped by category
 * (the same grouping the member rows use via ViewFormatter::memberSectorGroups),
 * so the head picks sectors straight from the list — no separate category step.
 *
 * The checkboxes are `sector_ids[]` (head) inside #sectorNameList, rendered in the
 * exact .sector-name-section shape that family-form-ui.js::populateSectorsByCategory
 * would otherwise build. With #sectorCategoryList removed, populateSectorsByCategory
 * bails out and leaves this static list untouched, while family-form.js still reads
 * checked #sectorNameList boxes into the head summary. #sectorCatalogData is kept so
 * the JS init takes the (no-op) populate path instead of clearing the selection.
 */
$sectorOptions      = $sectorOptions ?? [];
$sectorCatalog      = (array) ($sectorCatalog ?? []);
$selectedSectorIds  = array_map('intval', (array) ($selectedSectorIds ?? []));
$selectedServiceIds = array_map('intval', (array) ($selectedServiceIds ?? []));
$headSectorGroups   = ViewFormatter::memberSectorGroups(
	$sectorOptions,
	(new \App\Models\Lookups\SectorModel())->categoryLabelMap()
);
?>

<div class="form-section family-step-panel" data-step="2">
	<div class="member-sector-service-block">
		<div class="row g-3 member-choice-grid">
			<div class="col-lg-5">
				<div class="member-choice-section">
					<div class="member-choice-section-title">Sectors</div>
					<input type="hidden" id="sectorID" name="sectorID">
					<div id="sectorNameList" role="group" aria-label="Sectors">
						<?php foreach ($headSectorGroups as $sectorGroup): ?>
							<div class="sector-name-section">
								<div class="sector-name-section-title"><?= esc((string) ($sectorGroup['label'] ?? 'Other Sectors')) ?></div>
								<?php foreach (($sectorGroup['sectors'] ?? []) as $sector): ?>
									<?php
									$sectorId = (string) ($sector['sectorID'] ?? '');
									$sectorLabel = trim((string) ($sector['shortcode'] ?? '')) !== ''
										? (string) ($sector['shortcode'] ?? '') . ' - ' . (string) ($sector['name'] ?? '')
										: (string) ($sector['name'] ?? '');
									?>
									<label class="form-check mb-2 sector-name-option">
										<input class="form-check-input" type="checkbox" name="sector_ids[]" value="<?= esc($sectorId) ?>" data-name="<?= esc($sectorLabel, 'attr') ?>" data-label="<?= esc($sectorLabel, 'attr') ?>" <?= in_array((int) $sectorId, $selectedSectorIds, true) ? 'checked' : '' ?>>
										<span class="form-check-label sector-name-label"><span class="sector-name-text"><?= esc($sectorLabel) ?></span></span>
									</label>
								<?php endforeach; ?>
							</div>
						<?php endforeach; ?>
						<?php if ($headSectorGroups === []): ?>
							<small class="text-muted">No sectors available.</small>
						<?php endif; ?>
					</div>
				</div>
			</div>
			<div class="col-lg-7">
				<div class="member-choice-section">
					<div class="member-choice-section-title">Services and Programs Available</div>
					<div class="member-visible-list member-service-list" role="group" aria-label="Services and programs available">
						<?php foreach ($servicesByCategory as $category => $services): ?>
							<div class="member-visible-group">
								<div class="member-visible-group-title"><?= esc((string) $category) ?></div>
								<?php foreach ($services as $service): ?>
									<?php
									$serviceId = (string) ($service['serviceID'] ?? '');
									$serviceLabel = trim((string) ($service['description'] ?? '')) !== ''
										? (string) ($service['name'] ?? '') . ' - ' . trim((string) ($service['description'] ?? ''))
										: (string) ($service['name'] ?? '');
									?>
									<label class="form-check member-visible-option">
										<input class="form-check-input" type="checkbox" name="service_ids[]" value="<?= esc($serviceId) ?>" data-label="<?= esc((string) ($service['name'] ?? ''), 'attr') ?>" <?= in_array((int) $serviceId, $selectedServiceIds, true) ? 'checked' : '' ?>>
										<span class="form-check-label"><?= esc($serviceLabel) ?></span>
									</label>
								<?php endforeach; ?>
							</div>
						<?php endforeach; ?>
						<?php if ($servicesByCategory === []): ?>
							<small class="text-muted">No services available.</small>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
	</div>

	<?php /* Kept non-empty so family-form.js init takes the (no-op) populate path
	         rather than resetSectorSelection(), which would clear edit pre-checks. */ ?>
	<script type="application/json" id="sectorCatalogData"><?= json_encode($sectorCatalog, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?></script>
	<script type="application/json" id="selectedSectorIdsData"><?= json_encode($selectedSectorIds, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?></script>
</div>
