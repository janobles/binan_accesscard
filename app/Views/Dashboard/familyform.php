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
    'family_heads' => [],
], $formOptions ?? []);
$sectorOptions = $sectorOptions ?? ($formOptions['sectors'] ?? []);
$sectorCatalog = $sectorCatalog ?? [];
$sexOptions = $sexOptions ?? ($formOptions['sexes'] ?? []);
$suffixOptions = $suffixOptions ?? ($formOptions['suffixes'] ?? []);
$civilOptions = $civilOptions ?? ($formOptions['civil_statuses'] ?? []);
$relationshipOptions = $relationshipOptions ?? ($formOptions['relationships'] ?? []);
$educationOptions = $educationOptions ?? ($formOptions['education_levels'] ?? []);
$incomeOptions = $incomeOptions ?? ($formOptions['income_ranges'] ?? []);
$servicesByCategory = $servicesByCategory ?? ($formOptions['services_by_category'] ?? []);
$familyHeads = $familyHeads ?? ($formOptions['family_heads'] ?? []);
$formAction = $formAction ?? site_url('families');
$submitButtonLabel = $submitButtonLabel ?? 'Save Family Data';
$familyRecord = $familyRecord ?? [];
$existingMembers = $existingMembers ?? [];
$headServiceIds = array_values(array_map(static fn ($id): int => (int) $id, (array) ($headServiceIds ?? ($familyRecord['service_ids'] ?? []))));
$isEditMode = $familyRecord !== [];
$selectedSectorIds = \App\Support\SectorIds::normalize($familyRecord['sectorID'] ?? null);
$selectedSectorCategories = [];

foreach ($sectorCatalog as $categoryKey => $sectorRows) {
    foreach ((array) $sectorRows as $sectorRow) {
        if (in_array((int) ($sectorRow['sectorID'] ?? 0), $selectedSectorIds, true)) {
            $selectedSectorCategories[] = (string) $categoryKey;
            break;
        }
    }
}

$initialFamilyData = [
    'selectedSectorIds' => $selectedSectorIds,
    'selectedSectorCategories' => array_values(array_unique($selectedSectorCategories)),
    'headServiceIds' => $headServiceIds,
    'existingMembers' => $existingMembers,
];
$fieldViewData = compact(
    'civilOptions',
    'educationOptions',
    'familyRecord',
    'incomeOptions',
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
        <div class="family-wizard-header">
            <div class="wizard-header-left">
                <span class="wizard-icon" aria-hidden="true">+</span>
                <div>
                    <strong><?= $isEditMode ? 'Edit Family' : 'Add Family' ?></strong>
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

        <form method="post" action="<?= esc($formAction, 'attr') ?>" id="familyForm" class="needs-validation js-family-form" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="entry_type" id="entryType" value="head">
            <div id="familyFormAlert" class="mb-3" aria-live="polite"></div>

            <div class="form-section family-step-panel is-visible" data-step="1">
                <?= view('Dashboard/familyform/head-fields', $fieldViewData) ?>
            </div>

            <?= view('Dashboard/sectorandservices', [
                'servicesByCategory' => $servicesByCategory,
                'sectorCatalog' => $sectorCatalog,
                'selectedSectorIds' => $selectedSectorIds,
                'selectedSectorCategories' => $selectedSectorCategories,
                'selectedServiceIds' => $headServiceIds,
            ]) ?>

            <?= view('Dashboard/familyform/member-summary') ?>

            <script type="application/json" id="initialFamilyData"><?= json_encode($initialFamilyData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?></script>

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

<?= view('Dashboard/familyform/member-template', $fieldViewData) ?>
