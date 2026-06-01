<?php
use App\Libraries\SectorIds;

$defaultFormOptions = [
    'sectors'              => [],
    'sexes'                => [],
    'suffixes'             => [],
    'civil_statuses'       => [],
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
$selectedSectorCategories = (static function () use ($sectorCatalog, $selectedSectorIds): array {
    $cats = [];

    foreach ($sectorCatalog as $key => $rows) {
        foreach ((array) $rows as $row) {
            if (in_array((int) ($row['sectorID'] ?? 0), $selectedSectorIds, true)) {
                $cats[] = (string) $key;
                break;
            }
        }
    }

    return array_values(array_unique($cats));
})();
$initialFamilyData        = [
    'selectedSectorIds'        => $selectedSectorIds,
    'selectedSectorCategories' => $selectedSectorCategories,
    'headServiceIds'           => $headServiceIds,
    'existingMembers'          => $existingMembers,
];
$fieldViewData            = compact(
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

<link rel="stylesheet" href="<?= base_url('assets/css/familyform.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/familyform.css') ?>">

<div class="family-wizard-shell">
    <div class="family-wizard-card">
        <?php if (! $embeddedInModal): ?>
            <div class="family-wizard-header">
                <div class="wizard-header-left">
                    <span class="wizard-icon" aria-hidden="true">+</span>
                    <div>
                        <strong><?= $isEditMode ? 'Edit Record' : 'Add Record' ?></strong>
                        <small>Step 1 of 3 - Head of Family</small>
                    </div>
                </div>
                <span class="wizard-header-badge" aria-hidden="true"></span>
            </div>
        <?php endif; ?>

        <div class="family-wizard-steps" aria-hidden="true">
            <div class="wizard-step is-active" data-step-target="1" aria-current="step"><span>1</span><small>Head of Family</small></div>
            <div class="wizard-step" data-step-target="2"><span>2</span><small>Sector &amp; Services</small></div>
            <div class="wizard-step" data-step-target="3"><span>3</span><small>Members</small></div>
        </div>

        <!-- data-edit-mode is read by family-form.js to avoid resetting edit forms on AJAX success. -->
        <form method="post" action="<?= esc($formAction, 'attr') ?>" id="familyForm" class="needs-validation js-family-form" data-edit-mode="<?= $isEditMode ? '1' : '0' ?>" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="entry_type" id="entryType" value="head">
            <div id="familyFormAlert" class="mb-3" aria-live="polite"></div>

            <div class="form-section family-step-panel is-visible" data-step="1">
                <?= view('Dashboard/familyform/head-fields', $fieldViewData) ?>
            </div>

            <?= view('Dashboard/Sectors and Services/sectorandservices', [
                'servicesByCategory' => $servicesByCategory,
                'sectorCatalog' => $sectorCatalog,
                'selectedSectorIds' => $selectedSectorIds,
                'selectedSectorCategories' => $selectedSectorCategories,
                'selectedServiceIds' => $headServiceIds,
            ]) ?>

            <?= view('Member/member-summary') ?>

            <div id="initialFamilyData" class="family-form-hidden" data-json="<?= esc(json_encode($initialFamilyData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), 'attr') ?>"></div>

            <div class="d-flex justify-content-end gap-2 family-form-actions">
                <button type="button" class="btn btn-outline-secondary family-form-hidden" id="prevStepBtn"><i class="bi bi-arrow-left" aria-hidden="true"></i>Previous</button>
                <?php if (! $isEditMode): ?>
                    <button type="reset" class="btn btn-outline-secondary" id="resetFamilyBtn"><i class="bi bi-x-lg" aria-hidden="true"></i>Clear</button>
                <?php endif; ?>
                <button type="button" class="btn btn-primary" id="nextStepBtn">Next<i class="bi bi-arrow-right ms-1" aria-hidden="true"></i></button>
                <button type="submit" class="btn btn-primary family-form-hidden" id="submitFamilyBtn"><i class="bi bi-check2" aria-hidden="true"></i><?= esc((string) $submitButtonLabel) ?></button>
            </div>
        </form>
    </div>
</div>

<?= view('Member/member-template', $fieldViewData) ?>
