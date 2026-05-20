<?php
$formOptions = $formOptions ?? [];
$sectorOptions = $sectorOptions ?? ($formOptions['sectors'] ?? []);
$sexOptions = $sexOptions ?? ($formOptions['sexes'] ?? []);
$suffixOptions = $suffixOptions ?? ($formOptions['suffixes'] ?? []);
$civilOptions = $civilOptions ?? ($formOptions['civil_statuses'] ?? []);
$relationshipOptions = $relationshipOptions ?? ($formOptions['relationships'] ?? []);
$educationOptions = $educationOptions ?? ($formOptions['education_levels'] ?? []);
$incomeOptions = $incomeOptions ?? ($formOptions['income_ranges'] ?? []);
$servicesByCategory = $servicesByCategory ?? ($formOptions['services_by_category'] ?? []);
?>

<link rel="stylesheet" href="<?= base_url('assets/css/familyform.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/familyform.css') ?>">

<div class="family-wizard-shell">
    <div class="family-wizard-card">
        <div class="family-wizard-header">
            <div class="wizard-header-left">
                <span class="wizard-icon" aria-hidden="true">+</span>
                <div>
                    <strong>Add Family</strong>
                    <small>Step 1 of 3 - Head of the Family</small>
                </div>
            </div>
            <span class="wizard-header-badge" aria-hidden="true"></span>
        </div>

        <div class="family-wizard-steps" aria-hidden="true">
            <div class="wizard-step is-active" data-step-target="1"><span>1</span><small>Head of the Family</small></div>
            <div class="wizard-step" data-step-target="2"><span>2</span><small>Sector &amp; services</small></div>
            <div class="wizard-step" data-step-target="3"><span>3</span><small>Family members</small></div>
        </div>

<form method="post" action="<?= site_url('families') ?>" id="familyForm" class="needs-validation js-family-form" novalidate>
    <?= csrf_field() ?>
    <div id="familyFormAlert" class="mb-3" aria-live="polite"></div>

    <div class="form-section family-step-panel is-visible" data-step="1">
        <div class="section-title">
            <span>Head of Family</span>
        </div>
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label" for="head_firstname">First name</label>
                <input class="form-control" id="head_firstname" name="head_firstname" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="head_middlename">Middle name</label>
                <input class="form-control" id="head_middlename" name="head_middlename" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="head_lastname">Last name</label>
                <input class="form-control" id="head_lastname" name="head_lastname" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="head_suffix">Suffix</label>
                <select class="form-select" id="head_suffix" name="head_suffix">
                    <option value="">Select</option>
                    <?php foreach ($suffixOptions as $suffix): ?>
                        <option value="<?= esc($suffix) ?>"><?= esc($suffix) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="head_birthday">Birthday</label>
                <input type="date" class="form-control" id="head_birthday" name="head_birthday" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="head_sex">Sex</label>
                <select class="form-select" id="head_sex" name="head_sex" required>
                    <option value="">Select</option>
                    <?php foreach ($sexOptions as $sex): ?>
                        <option value="<?= esc($sex) ?>"><?= esc($sex) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="head_civilstatus">Civil status</label>
                <select class="form-select" id="head_civilstatus" name="head_civilstatus">
                    <option value="">Select</option>
                    <?php foreach ($civilOptions as $civilStatus): ?>
                        <option value="<?= esc($civilStatus) ?>"><?= esc($civilStatus) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="head_contactnumber">Contact number</label>
                <input class="form-control" id="head_contactnumber" name="head_contactnumber">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="head_education">Education</label>
                <select class="form-select" id="head_education" name="head_education">
                    <option value="">Select</option>
                    <?php foreach ($educationOptions as $education): ?>
                        <option value="<?= esc($education) ?>"><?= esc($education) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="head_job">Job</label>
                <input class="form-control" id="head_job" name="head_job">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="head_salary">Monthly income</label>
                <select class="form-select" id="head_salary" name="head_salary">
                    <?php foreach ($incomeOptions as $incomeOption): ?>
                        <option value="<?= esc((string) ($incomeOption['value'] ?? '')) ?>"><?= esc((string) ($incomeOption['label'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <?= view('Dashboard/sectorandservices', [
        'servicesByCategory' => $servicesByCategory,
        'sectorOptions' => $sectorOptions,
    ]) ?>

    <div class="form-section family-step-panel" data-step="3">
        <div class="section-title">
            <span>Family Members</span>
        </div>
        <div id="memberRows" class="member-stack"></div>
    </div>

    <div class="d-flex justify-content-end gap-2 family-form-actions">
        <button type="button" class="btn btn-outline-secondary" id="prevStepBtn" style="display:none;">Previous</button>
        <button type="reset" class="btn btn-outline-secondary" id="resetFamilyBtn">Clear</button>
        <button type="button" class="btn btn-primary" id="nextStepBtn">Next</button>
        <button type="submit" class="btn btn-primary" id="submitFamilyBtn" style="display:none;">Save Family Data</button>
    </div>
</form>

    </div>
</div>

<template id="memberTemplate">
    <div class="member-row">
        <div class="member-row-header">
            <strong>Family Member</strong>
            <button type="button" class="btn btn-sm btn-outline-danger remove-member">Remove</button>
        </div>
        <div class="row g-2">
            <div class="col-md-3"><input class="form-control" data-name="firstname" placeholder="First name"></div>
            <div class="col-md-3"><input class="form-control" data-name="middlename" placeholder="Middle name"></div>
            <div class="col-md-3"><input class="form-control" data-name="lastname" placeholder="Last name"></div>
            <div class="col-md-3">
                <select class="form-select" data-name="suffix">
                    <option value="">Suffix</option>
                    <?php foreach ($suffixOptions as $suffix): ?>
                        <option value="<?= esc($suffix) ?>"><?= esc($suffix) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3"><input type="date" class="form-control" data-name="birthday"></div>
            <div class="col-md-3">
                <select class="form-select" data-name="sex">
                    <option value="">Sex</option>
                    <?php foreach ($sexOptions as $sex): ?>
                        <option value="<?= esc($sex) ?>"><?= esc($sex) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" data-name="civilstatus">
                    <option value="">Civil status</option>
                    <?php foreach ($civilOptions as $civilStatus): ?>
                        <option value="<?= esc($civilStatus) ?>"><?= esc($civilStatus) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" data-name="relationship">
                    <option value="">Relationship</option>
                    <?php foreach ($relationshipOptions as $relationship): ?>
                        <option value="<?= esc($relationship) ?>"><?= esc($relationship) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" data-name="sectorID">
                    <option value="">Select</option>
                    <?php foreach ($sectorOptions as $sector): ?>
                        <option value="<?= esc((string) ($sector['sectorID'] ?? '')) ?>"><?= esc((string) ($sector['name'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" data-name="education">
                    <option value="">Education</option>
                    <?php foreach ($educationOptions as $education): ?>
                        <option value="<?= esc($education) ?>"><?= esc($education) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3"><input class="form-control" data-name="job" placeholder="Job"></div>
            <div class="col-md-3">
                <select class="form-select" data-name="salary">
                    <?php foreach ($incomeOptions as $incomeOption): ?>
                        <option value="<?= esc((string) ($incomeOption['value'] ?? '')) ?>"><?= esc((string) ($incomeOption['label'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
</template>

<script src="<?= base_url('assets/js/familyform.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/familyform.js') ?>"></script>
