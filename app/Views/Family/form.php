<?php
use App\Support\FamilyFormViewData;

// Single source of truth for every family-form variable (create + edit, embedded or
// not). All three callers land here — the dashboard family-entry tab (via
// Family/entry), the add-family modal partial, and the edit controller — so the prep
// lives in FamilyFormViewData::prepare() instead of being duplicated in this view.
extract(FamilyFormViewData::prepare(get_defined_vars()), EXTR_OVERWRITE);
?>

<link rel="stylesheet" href="<?= esc(asset_url('css/familyform.css'), 'attr') ?>">

<div class="family-wizard-shell">
    <div class="family-wizard-card">
        <header class="family-window-header">
            <div class="family-window-heading">
                <p class="family-window-kicker">Manage Records</p>
                <h2 class="family-window-title"><?= $isEditMode ? 'Edit Family Record' : 'New Family Record' ?></h2>
            </div>
            <?php if ($embeddedInModal): ?>
                <button type="button" class="family-window-close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            <?php endif; ?>
        </header>

        <div class="family-wizard-steps" aria-hidden="true">
            <div class="wizard-step is-active" data-step-target="1" aria-current="step"><span>1</span><small>Head of Family</small></div>
            <div class="wizard-step" data-step-target="2"><span>2</span><small>Members</small></div>
        </div>

        <!-- data-edit-mode is read by family-form.js to avoid resetting edit forms on AJAX success. -->
        <form method="post" action="<?= esc($formAction, 'attr') ?>" id="familyForm" class="needs-validation js-family-form" data-edit-mode="<?= $isEditMode ? '1' : '0' ?>" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="entry_type" id="entryType" value="head">
            <div class="family-form-body">
                <div id="familyFormAlert" class="mb-3" aria-live="polite"></div>

                <div class="form-section family-step-panel is-visible" data-step="1">
                    <?= view('Family/head-fields', $fieldViewData) ?>

                    <?= view('Lookups/picker', [
                        'servicesByCategory' => $servicesByCategory,
                        'sectorCatalog' => $sectorCatalog,
                        'sectorOptions' => $sectorOptions,
                        'selectedSectorIds' => $selectedSectorIds,
                        'selectedSectorCategories' => $selectedSectorCategories,
                        'selectedServiceIds' => $headServiceIds,
                    ]) ?>
                </div>

                <?= view('Family/member-summary') ?>

                <div id="initialFamilyData" class="family-form-hidden" data-json="<?= esc(json_encode($initialFamilyData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), 'attr') ?>"></div>
            </div>

            <div class="d-flex justify-content-end gap-2 family-form-actions">
                <?php if (! $isEditMode): ?>
                    <button type="reset" class="btn btn-success" id="resetFamilyBtn">Clear</button>
                <?php endif; ?>
                <button type="button" class="btn btn-success family-form-hidden" id="prevStepBtn">Previous</button>
                <button type="button" class="btn btn-success" id="nextStepBtn">Next</button>
                <button type="submit" class="btn btn-success family-form-hidden" id="submitFamilyBtn"><?= esc((string) $submitButtonLabel) ?></button>
            </div>
        </form>
    </div>
</div>

<?= view('Family/member-fields', $fieldViewData) ?>
