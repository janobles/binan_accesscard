<?php
$servicesByCategory       = $servicesByCategory ?? [];
$sectorCatalog            = $sectorCatalog ?? [];
$selectedSectorIds        = array_map('intval', array_values(array_filter((array) ($selectedSectorIds ?? []), static fn ($v) => is_numeric($v))));
$selectedSectorCategories = array_values(array_filter(array_map('strval', (array) ($selectedSectorCategories ?? [])), static fn ($v) => trim($v) !== ''));
$selectedServiceIds       = array_map('intval', array_values(array_filter((array) ($selectedServiceIds ?? []), static fn ($v) => is_numeric($v))));
$sectorCatalogJson        = json_encode($sectorCatalog, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?: '{}';
$sectorCategoryLabels     = \App\Support\FamilyProfilingFormV2::SECTOR_CATEGORIES;
$sectorCategoryKeys       = array_values(array_filter(
    array_keys($sectorCatalog),
    static fn (string $key): bool => ($sectorCatalog[$key] ?? []) !== []
));
?>

<div class="form-section family-step-panel" data-step="2">
    <div class="row g-3 mb-3">
        <div class="col-sm-5 col-md-4 col-lg-3">
            <label class="form-label" for="sectorCategoryList">Sector</label>
            <div class="border rounded p-2 bg-white" id="sectorCategoryList" role="group" aria-label="Sector categories" data-sector-catalog="<?= esc($sectorCatalogJson, 'attr') ?>">
                <?php foreach ($sectorCategoryKeys as $index => $categoryKey): ?>
                    <label class="form-check <?= $index === array_key_last($sectorCategoryKeys) ? 'mb-0' : 'mb-1' ?>">
                        <input class="form-check-input" type="checkbox" name="sector_categories[]" value="<?= esc((string) $categoryKey) ?>" <?= in_array((string) $categoryKey, $selectedSectorCategories, true) ? 'checked' : '' ?>>
                        <span class="form-check-label"><?= esc((string) $categoryKey) ?></span>
                    </label>
                <?php endforeach; ?>
                <?php if ($sectorCategoryKeys === []): ?>
                    <small class="text-muted">No sectors available.</small>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-sm-7 col-md-8 col-lg-9">
            <label class="form-label" for="sectorNameList">Sector Name</label>
            <input type="hidden" id="sectorID" name="sectorID" required>
            <div class="border rounded p-2 bg-white" id="sectorNameList" role="group" aria-label="Sector names">
                <small class="text-muted">Select one or more sector categories first.</small>
            </div>
            <small class="form-text text-muted">Select one or more sector names.</small>
        </div>
    </div>

    <div id="sectorCatalogData" class="family-form-hidden" data-json="<?= esc(json_encode($sectorCatalog, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), 'attr') ?>"></div>
    <div id="selectedSectorIdsData" class="family-form-hidden" data-json="<?= esc(json_encode($selectedSectorIds, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), 'attr') ?>"></div>

    <div class="section-title">
        <span>Services and Programs</span>
    </div>
    <div class="row g-3">
        <?php foreach ($servicesByCategory as $category => $services): ?>
            <div class="col-12 col-sm-6 col-lg-4">
                <div class="assistance-box">
                    <h6 class="mb-2"><?= esc((string) $category) ?></h6>
                    <div class="service-check-list">
                        <?php foreach ($services as $service): ?>
                            <?php $serviceId      = (string) ($service['serviceID'] ?? ''); ?>
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
