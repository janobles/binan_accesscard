<?php
// Template cloned by the family form script for each new member row.
?>
<template id="memberTemplate">
    <div class="member-row">
        <div class="member-row-header">
            <strong>Family Member</strong>
            <button type="button" class="btn btn-sm btn-outline-danger remove-member">Remove</button>
        </div>
        <div class="row g-2">
            <div class="col-md-3">
                <label class="form-label"><?= esc($fieldLabels['firstname'] ?? 'First name') ?></label>
                <input class="form-control" data-name="firstname" placeholder="<?= esc($fieldLabels['firstname'] ?? 'First name', 'attr') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= esc($fieldLabels['middlename'] ?? 'Middle name') ?></label>
                <input class="form-control" data-name="middlename" placeholder="<?= esc($fieldLabels['middlename'] ?? 'Middle name', 'attr') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= esc($fieldLabels['lastname'] ?? 'Last name') ?></label>
                <input class="form-control" data-name="lastname" placeholder="<?= esc($fieldLabels['lastname'] ?? 'Last name', 'attr') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= esc($fieldLabels['suffix'] ?? 'Suffix') ?></label>
                <select class="form-select" data-name="suffix">
                    <option value=""><?= esc($fieldLabels['suffix'] ?? 'Suffix') ?></option>
                    <?php foreach ($suffixOptions as $suffix): ?>
                        <option value="<?= esc($suffix) ?>"><?= esc($suffix) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= esc($fieldLabels['birthday'] ?? 'Birthday') ?></label>
                <input type="date" class="form-control" data-name="birthday">
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= esc($fieldLabels['sex'] ?? 'Sex') ?></label>
                <select class="form-select" data-name="sex">
                    <option value=""><?= esc($fieldLabels['sex'] ?? 'Sex') ?></option>
                    <?php foreach ($sexOptions as $sex): ?>
                        <option value="<?= esc($sex) ?>"><?= esc($sex) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= esc($fieldLabels['civilstatus'] ?? 'Civil status') ?></label>
                <select class="form-select" data-name="civilstatus">
                    <option value=""><?= esc($fieldLabels['civilstatus'] ?? 'Civil status') ?></option>
                    <?php foreach ($civilOptions as $civilStatus): ?>
                        <option value="<?= esc($civilStatus) ?>"><?= esc($civilStatus) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= esc($fieldLabels['relationship'] ?? 'Relationship') ?></label>
                <select class="form-select" data-name="relationship">
                    <option value=""><?= esc($fieldLabels['relationship'] ?? 'Relationship') ?></option>
                    <?php foreach ($relationshipOptions as $relationship): ?>
                        <option value="<?= esc($relationship) ?>"><?= esc($relationship) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= esc($fieldLabels['sector_ids'] ?? 'Sectors') ?></label>
                <select class="form-select" data-name="sector_ids[]" multiple size="5">
                    <?php foreach ($sectorOptions as $sector): ?>
                        <option value="<?= esc((string) ($sector['sectorID'] ?? '')) ?>"><?= esc((string) ($sector['name'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= esc($fieldLabels['education'] ?? 'Education') ?></label>
                <select class="form-select" data-name="education">
                    <option value=""><?= esc($fieldLabels['education'] ?? 'Education') ?></option>
                    <?php foreach ($educationOptions as $education): ?>
                        <option value="<?= esc($education) ?>"><?= esc($education) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= esc($fieldLabels['job'] ?? 'Job') ?></label>
                <input class="form-control" data-name="job" placeholder="<?= esc($fieldLabels['job'] ?? 'Job', 'attr') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= esc($fieldLabels['salary'] ?? 'Monthly income') ?></label>
                <select class="form-select" data-name="salary">
                    <?php foreach ($incomeOptions as $incomeOption): ?>
                        <option value="<?= esc((string) ($incomeOption['value'] ?? '')) ?>"><?= esc((string) ($incomeOption['label'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?= esc($fieldLabels['contactnumber'] ?? 'Contact number') ?></label>
                <input class="form-control" data-name="contactnumber" placeholder="<?= esc($fieldLabels['contactnumber'] ?? 'Contact number', 'attr') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label"><?= esc($fieldLabels['service_ids'] ?? 'Services availed') ?></label>
                <select class="form-select" data-name="service_ids[]" multiple size="5" aria-label="<?= esc($fieldLabels['service_ids'] ?? 'Services availed', 'attr') ?>">
                    <?php foreach ($servicesByCategory as $category => $services): ?>
                        <optgroup label="<?= esc((string) $category) ?>">
                            <?php foreach ($services as $service): ?>
                                <option value="<?= esc((string) ($service['serviceID'] ?? '')) ?>"><?= esc((string) ($service['name'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
</template>
