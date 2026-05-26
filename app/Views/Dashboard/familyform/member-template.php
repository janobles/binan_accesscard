<template id="memberTemplate">
    <div class="member-row">
        <div class="member-row-header">
            <strong>Family Member</strong>
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
                <label class="form-label">Birthday</label>
                <input type="date" class="form-control" data-name="birthday">
            </div>
            <div class="col-md-3">
                <label class="form-label">Sex</label>
                <select class="form-select" data-name="sex">
                    <option value="">Sex</option>
                    <?php foreach ($sexOptions as $sex): ?>
                        <option value="<?= esc($sex) ?>"><?= esc($sex) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Civil status</label>
                <select class="form-select" data-name="civilstatus">
                    <option value="">Civil status</option>
                    <?php foreach ($civilOptions as $civilStatus): ?>
                        <option value="<?= esc($civilStatus) ?>"><?= esc($civilStatus) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Relationship</label>
                <select class="form-select" data-name="relationship">
                    <option value="">Relationship</option>
                    <?php foreach ($relationshipOptions as $relationship): ?>
                        <option value="<?= esc($relationship) ?>"><?= esc($relationship) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Sectors</label>
                <div class="border rounded p-2 bg-white">
                    <?php foreach ($sectorGroups as $groupLabel => $sectors): ?>
                        <div class="fw-semibold small text-muted mb-1"><?= esc((string) $groupLabel) ?></div>
                        <?php foreach ($sectors as $sector): ?>
                            <?php
                            $sectorId = (int) ($sector['sectorID'] ?? 0);
                            $shortcode = (string) ($sector['shortcode'] ?? '');
                            $label = trim($shortcode . ' ' . (string) ($sector['name'] ?? ''));
                            ?>
                            <label class="form-check mb-1">
                                <input class="form-check-input" type="checkbox" data-name="sectors[]" value="<?= esc((string) $sectorId) ?>">
                                <span class="form-check-label"><?= esc($label) ?></span>
                            </label>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                    <?php if ($sectorGroups === []): ?>
                        <small class="text-muted">No sectors available.</small>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label">Education</label>
                <select class="form-select" data-name="education">
                    <option value="">Education</option>
                    <?php foreach ($educationOptions as $education): ?>
                        <option value="<?= esc($education) ?>"><?= esc($education) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Job</label>
                <input class="form-control" data-name="job" placeholder="Job">
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
            <div class="col-md-6">
                <label class="form-label">Services availed</label>
                <div class="border rounded p-2 bg-white" aria-label="Services availed">
                    <?php foreach ($serviceGroups as $category => $services): ?>
                        <div class="fw-semibold small text-muted mb-1"><?= esc((string) $category) ?></div>
                        <?php foreach ($services as $service): ?>
                            <?php $serviceId = (int) ($service['serviceID'] ?? 0); ?>
                            <label class="form-check mb-1">
                                <input class="form-check-input" type="checkbox" data-name="services[]" value="<?= esc((string) $serviceId) ?>">
                                <span class="form-check-label"><?= esc((string) ($service['name'] ?? '')) ?></span>
                            </label>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                    <?php if ($serviceGroups === []): ?>
                        <small class="text-muted">No services available.</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</template>
