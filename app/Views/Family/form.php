<?php
use App\Libraries\SectorIds;
use App\Libraries\ViewFormatter;

$defaultFormOptions = [
    'sectors'              => [],
    'sexes'                => [],
    'suffixes'             => [],
    'civil_statuses'       => [],
    'barangays'            => [],
    'relationships'        => [],
    'education_levels'     => [],
    'income_ranges'        => [],
    'services_by_category' => [],
    'family_heads'         => [],
];

$formOptions              = array_merge($defaultFormOptions, (array) ($formOptions ?? []));
$sectorOptions            = $sectorOptions ?? ($formOptions['sectors'] ?? []);
$sectorCatalog            = (array) ($sectorCatalog ?? []);
$sexOptions               = $sexOptions ?? ($formOptions['sexes'] ?? []);
$suffixOptions            = $suffixOptions ?? ($formOptions['suffixes'] ?? []);
$civilOptions             = $civilOptions ?? ($formOptions['civil_statuses'] ?? []);
$barangayOptions          = $barangayOptions ?? ($formOptions['barangays'] ?? []);
$relationshipOptions      = $relationshipOptions ?? ($formOptions['relationships'] ?? []);
$educationOptions         = $educationOptions ?? ($formOptions['education_levels'] ?? []);
$incomeOptions            = $incomeOptions ?? ($formOptions['income_ranges'] ?? []);
$servicesByCategory       = $servicesByCategory ?? ($formOptions['services_by_category'] ?? []);
$familyHeads              = $familyHeads ?? ($formOptions['family_heads'] ?? []);
$formAction               = $formAction ?? site_url('families');
$submitButtonLabel        = $submitButtonLabel ?? 'Save Family Data';
$embeddedInModal          = (bool) ($embeddedInModal ?? false);
$familyRecord             = (array) ($familyRecord ?? []);
$existingMembers          = (array) ($existingMembers ?? []);
$headServiceIds           = array_values(array_map('intval', (array) ($headServiceIds ?? $familyRecord['service_ids'] ?? [])));
$isEditMode               = $familyRecord !== [];
$fieldLabels              = array_merge([
    'firstname'       => 'First name',
    'middlename'      => 'Middle name',
    'lastname'        => 'Last name',
    'suffix'          => 'Suffix',
    'birthday'        => 'Birthday',
    'sex'             => 'Sex',
    'civilstatus'     => 'Civil status',
    'contactnumber'   => 'Contact number',
    'education'       => 'Education',
    'job'             => 'Job',
    'salary'          => 'Monthly income',
    'relationship'    => 'Relationship',
    'sector_ids'      => 'Sectors',
    'service_ids'     => 'Services availed',
], (array) ($fieldLabels ?? []));
$selectedSectorIds        = SectorIds::normalize($familyRecord['sectorID'] ?? null);
$selectedSectorCategories = ViewFormatter::selectedSectorCategories($sectorCatalog, $selectedSectorIds);
$initialFamilyData        = [
    'selectedSectorIds'        => $selectedSectorIds,
    'selectedSectorCategories' => $selectedSectorCategories,
    'headServiceIds'           => $headServiceIds,
    'existingMembers'          => $existingMembers,
];
$fieldViewData            = compact(
    'barangayOptions',
    'civilOptions',
    'educationOptions',
    'familyRecord',
    'fieldLabels',
    'incomeOptions',
    'jobOptions',
    'relationshipOptions',
    'sectorOptions',
    'servicesByCategory',
    'sexOptions',
    'suffixOptions'
);
?>

<link rel="stylesheet" href="<?= base_url('css/familyform.css') ?>?v=<?= filemtime(FCPATH . 'css/familyform.css') ?>">

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
            <div class="wizard-step" data-step-target="2"><span>2</span><small>Sector &amp; Services</small></div>
            <div class="wizard-step" data-step-target="3"><span>3</span><small>Members</small></div>
        </div>

        <!-- data-edit-mode is read by family-form.js to avoid resetting edit forms on AJAX success. -->
        <form method="post" action="<?= esc($formAction, 'attr') ?>" id="familyForm" class="needs-validation js-family-form" data-edit-mode="<?= $isEditMode ? '1' : '0' ?>" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="entry_type" id="entryType" value="head">
            <div class="family-form-body">
                <div id="familyFormAlert" class="mb-3" aria-live="polite"></div>

                <div class="form-section family-step-panel is-visible" data-step="1">
                    <?= view('Family/head-fields', $fieldViewData) ?>
                </div>

                <?= view('Lookups/picker', [
                    'servicesByCategory' => $servicesByCategory,
                    'sectorCatalog' => $sectorCatalog,
                    'sectorOptions' => $sectorOptions,
                    'selectedSectorIds' => $selectedSectorIds,
                    'selectedSectorCategories' => $selectedSectorCategories,
                    'selectedServiceIds' => $headServiceIds,
                ]) ?>

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
