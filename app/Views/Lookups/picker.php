<?php
use App\Libraries\ViewFormatter;

// Flat sector/service picker (jade design): every sector and service is shown up
// front, grouped by category. Posts `sector_ids[]` / `service_ids[]`, exactly what
// FamilyController::store / ::memberPayload read. No category-filter step.
$servicesByCategory = $servicesByCategory ?? [];
$sectorCatalog      = (array) ($sectorCatalog ?? []);
$selectedSectorIds  = ViewFormatter::integerList($selectedSectorIds ?? [], true);
$selectedServiceIds = ViewFormatter::integerList($selectedServiceIds ?? [], true);
?>

<div class="member-sector-service-block head-sector-service-block">
    <div class="section-title"><span>Sectors and Services</span></div>
    <div class="row g-3 member-choice-grid">
        <div class="col-lg-5">
            <div class="member-choice-section">
                <div class="member-choice-section-title">Sectors</div>
                <div class="member-visible-list" role="group" aria-label="Sectors">
                <?php
                $hasSectors = false;
                foreach ($sectorCatalog as $groupCode => $groupSectors):
                    if (! is_array($groupSectors) || $groupSectors === []) {
                        continue;
                    }
                    $hasSectors = true;
                    $categoryLabel = (string) ($groupSectors[0]['category_label'] ?? $groupCode);
                    $heading = ($categoryLabel === '' || $categoryLabel === $groupCode)
                        ? $groupCode
                        : $groupCode . ' (' . $categoryLabel . ')';
                    ?>
                    <div class="member-visible-group" aria-label="<?= esc($heading, 'attr') ?>">
                        <div class="member-visible-group-title"><?= esc($heading) ?></div>
                        <?php foreach ($groupSectors as $sector): ?>
                            <?php
                            $sectorId = (string) ($sector['sectorID'] ?? '');
                            $shortcode = trim((string) ($sector['shortcode'] ?? ''));
                            $name = trim((string) ($sector['name'] ?? ''));
                            $sectorLabel = trim($shortcode . ' - ' . $name, ' -');
                            $isArchived = ! empty($sector['is_archived']);
                            ?>
                            <label class="family-choice-row<?= $isArchived ? ' family-choice-row--archived' : '' ?>">
                                <input class="form-check-input" type="checkbox" name="sector_ids[]" value="<?= esc($sectorId, 'attr') ?>" data-label="<?= esc($sectorLabel, 'attr') ?>"<?= $isArchived ? ' data-archived="1"' : '' ?> <?= in_array((int) $sectorId, $selectedSectorIds, true) ? 'checked' : '' ?>>
                                <span class="form-check-label"><?= esc($sectorLabel) ?><?php if ($isArchived): ?> <span class="family-choice-badge">Archived</span><?php endif; ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
                <?php if (! $hasSectors): ?>
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
                    <div class="member-visible-group" aria-label="<?= esc((string) $category, 'attr') ?>">
                        <div class="member-visible-group-title"><?= esc((string) $category) ?></div>
                        <?php foreach ($services as $service): ?>
                            <?php
                            $serviceId = (string) ($service['serviceID'] ?? '');
                            $name = trim((string) ($service['name'] ?? ''));
                            $description = trim((string) ($service['description'] ?? ''));
                            $serviceLabel = $description === '' ? $name : $name . ' - ' . $description;
                            $isArchived = ! empty($service['is_archived']);
                            ?>
                            <label class="family-choice-row<?= $isArchived ? ' family-choice-row--archived' : '' ?>">
                                <input class="form-check-input" type="checkbox" name="service_ids[]" value="<?= esc($serviceId, 'attr') ?>" data-label="<?= esc($name, 'attr') ?>"<?= $isArchived ? ' data-archived="1"' : '' ?> <?= in_array((int) $serviceId, $selectedServiceIds, true) ? 'checked' : '' ?>>
                                <span class="form-check-label"><?= esc($serviceLabel) ?><?php if ($isArchived): ?> <span class="family-choice-badge">Archived</span><?php endif; ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
                <?php if ($servicesByCategory === []): ?>
                    <small class="text-muted">No services or programs available.</small>
                <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
