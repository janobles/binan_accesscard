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

<div class="form-section family-step-panel" data-step="2">
    <div class="family-step-two-grid">
        <div class="family-choice-group">
            <div class="section-title"><span>Sectors</span></div>
            <div class="family-choice-box" role="group" aria-label="Sectors">
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
                    <section class="family-choice-section" aria-label="<?= esc($heading, 'attr') ?>">
                        <h3><?= esc($heading) ?></h3>
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
                    </section>
                <?php endforeach; ?>
                <?php if (! $hasSectors): ?>
                    <p class="family-choice-empty">No sectors available.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="family-choice-group">
            <div class="section-title"><span>Services and Programs Available</span></div>
            <div class="family-choice-box" role="group" aria-label="Services and programs available">
                <?php foreach ($servicesByCategory as $category => $services): ?>
                    <section class="family-choice-section" aria-label="<?= esc((string) $category, 'attr') ?>">
                        <h3><?= esc((string) $category) ?></h3>
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
                    </section>
                <?php endforeach; ?>
                <?php if ($servicesByCategory === []): ?>
                    <p class="family-choice-empty">No services or programs available.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
