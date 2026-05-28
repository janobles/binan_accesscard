<?php
helper('family_form');
extract(family_form_view_data(get_defined_vars()), EXTR_OVERWRITE);
?>

<link rel="stylesheet" href="<?= base_url('assets/css/familyform.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/familyform.css') ?>">

<div class="family-wizard-shell">
    <div class="family-wizard-card">
        <div class="family-wizard-header">
            <div class="wizard-header-left">
                <span class="wizard-icon" aria-hidden="true">+</span>
                <div>
                    <strong><?= $isEditMode ? 'Edit Record' : 'Add Record' ?></strong>
                    <small>Step 1 of 3 - Record Head</small>
                </div>
            </div>
        </div>

        <div class="family-wizard-steps" aria-hidden="true">
            <div class="wizard-step is-active" data-step-target="1"><span>1</span><small>Record Head</small></div>
            <div class="wizard-step" data-step-target="2"><span>2</span><small>Sectors, services &amp; programs</small></div>
            <div class="wizard-step" data-step-target="3"><span>3</span><small>Members</small></div>
        </div>

        <form method="post" action="<?= esc($formAction, 'attr') ?>" id="familyForm" class="needs-validation js-family-form" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="entry_type" id="entryType" value="head">
            <div id="familyFormAlert" class="mb-3" aria-live="polite"></div>

            <div class="form-section family-step-panel is-visible" data-step="1">
                <?= view('Dashboard/familyform/head-fields', $fieldViewData) ?>
            </div>

            <?= view('Dashboard/sectorandservices', [
                'serviceGroups' => $serviceGroups,
                'sectorGroups' => $sectorGroups,
                'selectedSectorIds' => $selectedSectorIds,
                'selectedServiceIds' => $headServiceIds,
            ]) ?>

            <?= view('Member/member-summary') ?>

            <input type="hidden" id="initialFamilyData" value="<?= esc(json_encode($initialFamilyData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?: '{}', 'attr') ?>">

            <div class="d-flex justify-content-end gap-2 family-form-actions">
                <button type="button" class="btn btn-outline-secondary family-form-hidden" id="prevStepBtn">Previous</button>
                <?php if (! $isEditMode): ?>
                    <button type="reset" class="btn btn-outline-secondary" id="resetFamilyBtn">Clear</button>
                <?php endif; ?>
                <button type="button" class="btn btn-primary" id="nextStepBtn">Next</button>
                <button type="submit" class="btn btn-primary family-form-hidden" id="submitFamilyBtn"><?= esc((string) $submitButtonLabel) ?></button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="familyChoiceModal" tabindex="-1" aria-labelledby="familyChoiceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="familyChoiceModalLabel">Select options</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="familyChoiceModalBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Done</button>
            </div>
        </div>
    </div>
</div>

<?= view('Member/member-template', $fieldViewData) ?>
