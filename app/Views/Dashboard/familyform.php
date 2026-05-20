<?php
$formOptions = array_merge([
    'sectors' => [],
    'sexes' => [],
    'suffixes' => [],
    'civil_statuses' => [],
    'relationships' => [],
    'education_levels' => [],
    'income_ranges' => [],
    'services_by_category' => [],
], $formOptions ?? []);
$sectorCatalog = $sectorCatalog ?? [];
$formAction = $formAction ?? site_url('families');
$submitButtonLabel = $submitButtonLabel ?? 'Save Family Data';
$familyRecord = $familyRecord ?? [];
$existingMembers = $existingMembers ?? [];
$headServiceIds = $headServiceIds ?? [];

$headSectorId = (int) ($familyRecord['sectorID'] ?? 0);
$selectedSectorIds = $headSectorId > 0 ? [$headSectorId] : [];
$selectedSectorCategories = [];

foreach ($sectorCatalog as $categoryKey => $sectorRows) {
    foreach ((array) $sectorRows as $sectorRow) {
        if ((int) ($sectorRow['sectorID'] ?? 0) === $headSectorId) {
            $selectedSectorCategories[] = (string) $categoryKey;
            break;
        }
    }
}

$initialFamilyData = [
    'selectedSectorIds' => $selectedSectorIds,
    'selectedSectorCategories' => array_values(array_unique($selectedSectorCategories)),
    'headServiceIds' => array_values(array_map(static fn ($id): int => (int) $id, (array) $headServiceIds)),
    'existingMembers' => $existingMembers,
];
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

<form method="post" action="<?= esc($formAction) ?>" id="familyForm" class="needs-validation js-family-form" novalidate>
    <div id="familyFormAlert" class="mb-3" aria-live="polite"></div>

    <div class="form-section family-step-panel is-visible" data-step="1">
        <div class="section-title">
            <span>Head of Family</span>
        </div>
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label" for="head_firstname">First name</label>
                <input class="form-control" id="head_firstname" name="head_firstname" value="<?= esc((string) ($familyRecord['firstname'] ?? '')) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="head_middlename">Middle name</label>
                <input class="form-control" id="head_middlename" name="head_middlename" value="<?= esc((string) ($familyRecord['middlename'] ?? '')) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="head_lastname">Last name</label>
                <input class="form-control" id="head_lastname" name="head_lastname" value="<?= esc((string) ($familyRecord['lastname'] ?? '')) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="head_suffix">Suffix</label>
                <select class="form-select" id="head_suffix" name="head_suffix">
                    <option value="">Select</option>
                    <?php foreach ($formOptions['suffixes'] as $suffix): ?>
                        <option value="<?= esc($suffix) ?>" <?= (string) ($familyRecord['suffix'] ?? '') === (string) $suffix ? 'selected' : '' ?>><?= esc($suffix) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="head_birthday">Birthday</label>
                <input type="date" class="form-control" id="head_birthday" name="head_birthday" value="<?= esc((string) ($familyRecord['birthday'] ?? '')) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="head_sex">Sex</label>
                <select class="form-select" id="head_sex" name="head_sex" required>
                    <option value="">Select</option>
                    <?php foreach ($formOptions['sexes'] as $sex): ?>
                        <option value="<?= esc($sex) ?>" <?= (string) ($familyRecord['sex'] ?? '') === (string) $sex ? 'selected' : '' ?>><?= esc($sex) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="head_civilstatus">Civil status</label>
                <select class="form-select" id="head_civilstatus" name="head_civilstatus">
                    <option value="">Select</option>
                    <?php foreach ($formOptions['civil_statuses'] as $civilStatus): ?>
                        <option value="<?= esc($civilStatus) ?>" <?= (string) ($familyRecord['civilstatus'] ?? '') === (string) $civilStatus ? 'selected' : '' ?>><?= esc($civilStatus) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="head_contactnumber">Contact number</label>
                <input class="form-control" id="head_contactnumber" name="head_contactnumber" value="<?= esc((string) ($familyRecord['contactnumber'] ?? '')) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="head_education">Education</label>
                <select class="form-select" id="head_education" name="head_education">
                    <option value="">Select</option>
                    <?php foreach ($formOptions['education_levels'] as $education): ?>
                        <option value="<?= esc($education) ?>" <?= (string) ($familyRecord['education'] ?? '') === (string) $education ? 'selected' : '' ?>><?= esc($education) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="head_job">Job</label>
                <input class="form-control" id="head_job" name="head_job" value="<?= esc((string) ($familyRecord['job'] ?? '')) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="head_salary">Monthly income</label>
                <select class="form-select" id="head_salary" name="head_salary">
                    <?php foreach ($formOptions['income_ranges'] as $incomeValue => $incomeLabel): ?>
                        <option value="<?= esc((string) $incomeValue) ?>" <?= (string) ($familyRecord['Salary'] ?? '') === (string) $incomeValue ? 'selected' : '' ?>><?= esc((string) $incomeLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <?= view('Dashboard/sectorandservices', [
        'servicesByCategory' => $formOptions['services_by_category'],
        'sectorCatalog' => $sectorCatalog,
        'selectedSectorIds' => $selectedSectorIds,
        'selectedSectorCategories' => $selectedSectorCategories,
        'selectedServiceIds' => $headServiceIds,
    ]) ?>

    <div class="form-section family-step-panel" data-step="3">
        <div class="section-title">
            <span>Family Members</span>
            <button type="button" class="btn btn-sm btn-primary" id="addMemberBtn">Add Member</button>
        </div>
        <div class="member-row mb-3" id="headOfFamilySummary">
            <div class="member-row-header">
                <strong>Current Head of Family</strong>
            </div>
            <div class="row g-2">
                <div class="col-md-4"><small><strong>Name:</strong> <span id="headSummaryName">-</span></small></div>
                <div class="col-md-4"><small><strong>Birthday:</strong> <span id="headSummaryBirthday">-</span></small></div>
                <div class="col-md-4"><small><strong>Sex:</strong> <span id="headSummarySex">-</span></small></div>
                <div class="col-md-4"><small><strong>Civil status:</strong> <span id="headSummaryCivil">-</span></small></div>
                <div class="col-md-4"><small><strong>Contact:</strong> <span id="headSummaryContact">-</span></small></div>
                <div class="col-md-4"><small><strong>Education:</strong> <span id="headSummaryEducation">-</span></small></div>
                <div class="col-md-4"><small><strong>Job:</strong> <span id="headSummaryJob">-</span></small></div>
                <div class="col-md-4"><small><strong>Monthly income:</strong> <span id="headSummaryIncome">-</span></small></div>
                <div class="col-md-6"><small><strong>Sector(s):</strong></small><div id="headSummarySectors" class="head-summary-list">-</div></div>
                <div class="col-md-6"><small><strong>Services availed:</strong></small><div id="headSummaryServices" class="head-summary-list">-</div></div>
            </div>
        </div>
        <div id="memberRows" class="member-stack"></div>
        <p class="text-muted mb-0" id="memberRowsEmpty">No family members added yet. Click Add Member.</p>
    </div>

    <div class="d-flex justify-content-end gap-2 family-form-actions">
        <button type="button" class="btn btn-outline-secondary" id="prevStepBtn" style="display:none;">Previous</button>
        <button type="reset" class="btn btn-outline-secondary" id="resetFamilyBtn">Clear</button>
        <button type="button" class="btn btn-primary" id="nextStepBtn">Next</button>
        <button type="submit" class="btn btn-primary" id="submitFamilyBtn" style="display:none;"><?= esc((string) $submitButtonLabel) ?></button>
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
                    <?php foreach ($formOptions['suffixes'] as $suffix): ?>
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
                    <?php foreach ($formOptions['sexes'] as $sex): ?>
                        <option value="<?= esc($sex) ?>"><?= esc($sex) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Civil status</label>
                <select class="form-select" data-name="civilstatus">
                    <option value="">Civil status</option>
                    <?php foreach ($formOptions['civil_statuses'] as $civilStatus): ?>
                        <option value="<?= esc($civilStatus) ?>"><?= esc($civilStatus) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Relationship</label>
                <select class="form-select" data-name="relationship">
                    <option value="">Relationship</option>
                    <?php foreach ($formOptions['relationships'] as $relationship): ?>
                        <option value="<?= esc($relationship) ?>"><?= esc($relationship) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Sector</label>
                <select class="form-select" data-name="sectorID">
                    <option value="">Select</option>
                    <?php foreach ($formOptions['sectors'] as $sector): ?>
                        <option value="<?= esc((string) ($sector['sectorID'] ?? '')) ?>"><?= esc((string) ($sector['name'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Education</label>
                <select class="form-select" data-name="education">
                    <option value="">Education</option>
                    <?php foreach ($formOptions['education_levels'] as $education): ?>
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
                    <?php foreach ($formOptions['income_ranges'] as $incomeValue => $incomeLabel): ?>
                        <option value="<?= esc((string) $incomeValue) ?>"><?= esc((string) $incomeLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Contact number</label>
                <input class="form-control" data-name="contactnumber" placeholder="Contact number">
            </div>
            <div class="col-md-6">
                <label class="form-label">Services availed</label>
                <select class="form-select" data-name="service_ids[]" multiple size="5" aria-label="Services availed">
                    <?php foreach ($formOptions['services_by_category'] as $category => $services): ?>
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

<script type="application/json" id="initialFamilyData"><?= json_encode($initialFamilyData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?></script>