<?php
$memberSectorGroups = [
    'SC' => ['label' => 'SC', 'sectors' => []],
    'PWD' => ['label' => 'PWD', 'sectors' => []],
    'SP' => ['label' => 'SP', 'sectors' => []],
    'B' => ['label' => 'B', 'sectors' => []],
    'OTHER' => ['label' => 'Others', 'sectors' => []],
];

foreach ($sectorOptions as $sector) {
    $shortcode = strtoupper(trim((string) ($sector['shortcode'] ?? '')));

    if (str_starts_with($shortcode, 'PWD')) {
        $groupKey = 'PWD';
    } elseif (str_starts_with($shortcode, 'SC') || str_starts_with($shortcode, 'OSCA') || str_starts_with($shortcode, 'OSWA')) {
        $groupKey = 'SC';
    } elseif (str_starts_with($shortcode, 'SP')) {
        $groupKey = 'SP';
    } elseif (str_starts_with($shortcode, 'B')) {
        $groupKey = 'B';
    } else {
        $groupKey = 'OTHER';
    }

    $memberSectorGroups[$groupKey]['sectors'][] = $sector;
}

$memberSectorGroups = array_filter(
    $memberSectorGroups,
    static fn (array $group): bool => ($group['sectors'] ?? []) !== []
);
?>
<template id="memberTemplate">
    <div class="member-row">
        <div class="member-row-header">
            <strong>Member</strong>
            <button type="button" class="btn btn-sm btn-outline-danger remove-member">Remove</button>
        </div>
        <div class="row g-2">
            <div class="col-md-3">
                <label class="form-label">First name</label>
                <input class="form-control" data-name="firstname">
            </div>
            <div class="col-md-3">
                <label class="form-label">Middle name</label>
                <input class="form-control" data-name="middlename">
            </div>
            <div class="col-md-3">
                <label class="form-label">Last name</label>
                <input class="form-control" data-name="lastname">
            </div>
            <div class="col-md-3">
                <label class="form-label">Suffix</label>
                <select class="form-select" data-name="suffix">
                    <option value="">Select</option>
                    <?php foreach ($suffixOptions as $suffix): ?>
                        <option value="<?= esc($suffix) ?>"><?= esc($suffix) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Date of birth</label>
                <input type="date" class="form-control" data-name="birthday">
            </div>
            <div class="col-md-3">
                <label class="form-label">Gender</label>
                <select class="form-select" data-name="sex">
                    <option value="">Select</option>
                    <?php foreach ($sexOptions as $sex): ?>
                        <option value="<?= esc($sex) ?>"><?= esc($sex) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Civil status</label>
                <select class="form-select js-other-select" data-name="civilstatus" data-other-field="civilstatus">
                    <option value="">Select</option>
                    <?php foreach ($civilOptions as $civilStatus): ?>
                        <option value="<?= esc($civilStatus) ?>"><?= esc($civilStatus) ?></option>
                    <?php endforeach; ?>
                </select>
                <input class="form-control mt-2 js-other-input family-form-hidden" data-other-for="civilstatus" placeholder="Enter civil status">
            </div>
            <div class="col-md-3">
                <label class="form-label">Contact number</label>
                <input class="form-control" data-name="contactnumber">
            </div>
            <div class="col-md-3">
                <label class="form-label">Religion</label>
                <input class="form-control" data-name="religion">
            </div>
            <div class="col-md-3">
                <label class="form-label">Education</label>
                <select class="form-select js-other-select" data-name="education" data-other-field="education">
                    <option value="">Select</option>
                    <?php foreach ($educationOptions as $education): ?>
                        <option value="<?= esc($education) ?>"><?= esc($education) ?></option>
                    <?php endforeach; ?>
                </select>
                <input class="form-control mt-2 js-other-input family-form-hidden" data-other-for="education" placeholder="Enter education">
            </div>
            <div class="col-md-3">
                <label class="form-label">Job</label>
                <select class="form-select js-other-select" data-name="job" data-other-field="job">
                    <option value="">Select</option>
                    <?php foreach ($jobOptions as $jobOption): ?>
                        <option value="<?= esc((string) $jobOption) ?>"><?= esc((string) $jobOption) ?></option>
                    <?php endforeach; ?>
                </select>
                <input class="form-control mt-2 js-other-input family-form-hidden" data-other-for="job" placeholder="Enter job">
            </div>
            <div class="col-md-3">
                <label class="form-label">Monthly income</label>
                <select class="form-select" data-name="salary">
                    <?php foreach ($incomeOptions as $incomeOption): ?>
                        <option value="<?= esc((string) ($incomeOption['value'] ?? '')) ?>"><?= esc((string) ($incomeOption['label'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Relationship</label>
                <select class="form-select js-other-select" data-name="relationship" data-other-field="relationship">
                    <option value="">Select</option>
                    <?php foreach ($relationshipOptions as $relationship): ?>
                        <option value="<?= esc($relationship) ?>"><?= esc($relationship) ?></option>
                    <?php endforeach; ?>
                </select>
                <input class="form-control mt-2 js-other-input family-form-hidden" data-other-for="relationship" placeholder="Enter relationship">
            </div>
            <div class="col-md-12">
                <div class="member-sector-service-block">
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <div class="member-choice-section">
                                <div class="member-choice-section-title">Sectors</div>
                                <div class="member-visible-list" role="group" aria-label="Member sectors">
                                    <?php foreach ($memberSectorGroups as $sectorGroup): ?>
                                        <div class="member-visible-group">
                                            <div class="member-visible-group-title"><?= esc((string) ($sectorGroup['label'] ?? 'Other Sectors')) ?></div>
                                            <?php foreach (($sectorGroup['sectors'] ?? []) as $sector): ?>
                                                <?php $sectorLabel = trim((string) ($sector['shortcode'] ?? '')) !== '' ? (string) ($sector['shortcode'] ?? '') . ' - ' . (string) ($sector['name'] ?? '') : (string) ($sector['name'] ?? ''); ?>
                                                <label class="form-check member-visible-option">
                                                    <input class="form-check-input" type="checkbox" data-name="sector_ids[]" value="<?= esc((string) ($sector['sectorID'] ?? '')) ?>" data-label="<?= esc($sectorLabel, 'attr') ?>">
                                                    <span class="form-check-label"><?= esc($sectorLabel) ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if ($memberSectorGroups === []): ?>
                                        <small class="text-muted">No sectors available.</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="member-choice-section">
                                <div class="member-choice-section-title">Services and Programs Available</div>
                                <div class="member-visible-list member-service-list" role="group" aria-label="Member services and programs">
                                    <?php foreach ($servicesByCategory as $category => $services): ?>
                                        <div class="member-visible-group">
                                            <div class="member-visible-group-title"><?= esc((string) $category) ?></div>
                                            <?php foreach ($services as $service): ?>
                                                <?php $serviceLabel = trim((string) ($service['description'] ?? '')) !== '' ? (string) ($service['name'] ?? '') . ' - ' . trim((string) ($service['description'] ?? '')) : (string) ($service['name'] ?? ''); ?>
                                                <label class="form-check member-visible-option">
                                                    <input class="form-check-input" type="checkbox" data-name="service_ids[]" value="<?= esc((string) ($service['serviceID'] ?? '')) ?>" data-label="<?= esc((string) ($service['name'] ?? ''), 'attr') ?>">
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
            </div>
        </div>
    </div>
</template>
