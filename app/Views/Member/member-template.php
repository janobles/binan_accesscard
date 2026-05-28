<template id="memberTemplate">
    <div class="member-row">
        <div class="member-row-header">
            <strong>Member</strong>
            <button type="button" class="btn btn-sm btn-outline-danger remove-member">Remove</button>
        </div>
        <div class="row g-2">
            <div class="col-md-3">
                <label class="form-label">First name</label>
                <input class="form-control" data-name="firstname" placeholder="First name">
            </div>
            <div class="col-md-3">
                <label class="form-label">Middle name</label>
                <input class="form-control" data-name="middlename" placeholder="Middle name">
            </div>
            <div class="col-md-3">
                <label class="form-label">Last name</label>
                <input class="form-control" data-name="lastname" placeholder="Last name">
            </div>
            <div class="col-md-3">
                <label class="form-label">Suffix</label>
                <select class="form-select" data-name="suffix">
                    <option value="">Suffix</option>
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
                    <option value="">Gender</option>
                    <?php foreach ($sexOptions as $sex): ?>
                        <option value="<?= esc($sex) ?>"><?= esc($sex) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Civil status</label>
                <select class="form-select js-other-select" data-name="civilstatus" data-other-field="civilstatus">
                    <option value="">Civil status</option>
                    <?php foreach ($civilOptions as $civilStatus): ?>
                        <option value="<?= esc($civilStatus) ?>"><?= esc($civilStatus) ?></option>
                    <?php endforeach; ?>
                </select>
                <input class="form-control mt-2 js-other-input family-form-hidden" data-other-for="civilstatus" placeholder="Enter civil status">
            </div>
            <div class="col-md-3">
                <label class="form-label">Relationship</label>
                <select class="form-select js-other-select" data-name="relationship" data-other-field="relationship">
                    <option value="">Relationship</option>
                    <?php foreach ($relationshipOptions as $relationship): ?>
                        <option value="<?= esc($relationship) ?>"><?= esc($relationship) ?></option>
                    <?php endforeach; ?>
                </select>
                <input class="form-control mt-2 js-other-input family-form-hidden" data-other-for="relationship" placeholder="Enter relationship">
            </div>
            <div class="col-md-6">
                <label class="form-label">Sectors</label>
                <div class="dropdown-checklist js-dropdown-checklist" data-placeholder="Select sectors">
                    <button type="button" class="dropdown-checklist-toggle" data-dropdown-checklist-toggle>
                        <span data-dropdown-checklist-label>Select sectors</span>
                        <span class="dropdown-checklist-caret" aria-hidden="true"></span>
                    </button>
                    <div class="dropdown-checklist-menu">
                        <?php foreach ($sectorOptions as $sector): ?>
                            <?php $sectorLabel = trim((string) ($sector['shortcode'] ?? '')) !== '' ? (string) ($sector['shortcode'] ?? '') . ' - ' . (string) ($sector['name'] ?? '') : (string) ($sector['name'] ?? ''); ?>
                            <label class="dropdown-checklist-option">
                                <input class="form-check-input" type="checkbox" data-name="sector_ids[]" value="<?= esc((string) ($sector['sectorID'] ?? '')) ?>" data-label="<?= esc($sectorLabel, 'attr') ?>">
                                <span><?= esc($sectorLabel) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label">Education</label>
                <select class="form-select js-other-select" data-name="education" data-other-field="education">
                    <option value="">Education</option>
                    <?php foreach ($educationOptions as $education): ?>
                        <option value="<?= esc($education) ?>"><?= esc($education) ?></option>
                    <?php endforeach; ?>
                </select>
                <input class="form-control mt-2 js-other-input family-form-hidden" data-other-for="education" placeholder="Enter education">
            </div>
            <div class="col-md-3">
                <label class="form-label">Job</label>
                <select class="form-select js-other-select" data-name="job" data-other-field="job">
                    <option value="">Job</option>
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
                <label class="form-label">Contact number</label>
                <input class="form-control" data-name="contactnumber" placeholder="Contact number">
            </div>
            <div class="col-md-3">
                <label class="form-label">Religion</label>
                <input class="form-control" data-name="religion" placeholder="Religion">
            </div>
            <div class="col-md-6">
                <label class="form-label">Services and programs availed</label>
                <div class="dropdown-checklist js-dropdown-checklist" data-placeholder="Select services and programs">
                    <button type="button" class="dropdown-checklist-toggle" data-dropdown-checklist-toggle>
                        <span data-dropdown-checklist-label>Select services and programs</span>
                        <span class="dropdown-checklist-caret" aria-hidden="true"></span>
                    </button>
                    <div class="dropdown-checklist-menu">
                        <?php foreach ($servicesByCategory as $category => $services): ?>
                            <div class="dropdown-checklist-group">
                                <div class="dropdown-checklist-group-title"><?= esc((string) $category) ?></div>
                                <?php foreach ($services as $service): ?>
                                    <?php $serviceLabel = trim((string) ($service['description'] ?? '')) !== '' ? (string) ($service['name'] ?? '') . ' - ' . trim((string) ($service['description'] ?? '')) : (string) ($service['name'] ?? ''); ?>
                                    <label class="dropdown-checklist-option">
                                        <input class="form-check-input" type="checkbox" data-name="service_ids[]" value="<?= esc((string) ($service['serviceID'] ?? '')) ?>" data-label="<?= esc((string) ($service['name'] ?? ''), 'attr') ?>">
                                        <span><?= esc($serviceLabel) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
