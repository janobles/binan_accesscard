<?php
helper('family_modal');

$sectorSectionLabels = [
    'PWD' => 'PWD (Persons with Disabilities)',
    'SP' => 'SP (Solo Parents)',
    'SC' => 'SC (Senior Citizens)',
    'B' => 'B (Bata)',
    'LGBT' => 'LGBT (Lesbian, Gay, Bisexual, Transgender)',
    'OFW' => 'OFW (Overseas Filipino Workers)',
    'IP' => 'IP (Indigenous Peoples)',
    'IDP' => 'IDP (Internally Displaced Persons)',
    'PDL' => 'PDL (Persons Deprived of Liberty)',
];

$serviceSectionLabels = [
    '4Ps' => '4PS',
    'Children' => 'CHILDREN',
    'Emergency' => 'EMERGENCY',
    'FA(NS)' => 'FINANCIAL ASSISTANCE (NS)',
    'FA(OSCA)' => 'FINANCIAL ASSISTANCE (OSCA)',
];

if (! function_exists('renderPersonFields')) {
    function renderPersonFields(string $idPrefix, string $namePrefix = '', bool $showAddress = true, bool $showRelationship = false, array $values = []): void
    {
        $fieldId = static fn (string $field): string => $idPrefix . '-' . $field;
        $fieldName = static fn (string $field): string => $namePrefix === ''
            ? $field
            : $namePrefix . '[' . $field . ']';
        ?>
        <div class="row g-3 g-xl-4">
            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label" for="<?= esc($fieldId('last-name'), 'attr') ?>">Last Name</label>
                <input class="form-control" type="text" id="<?= esc($fieldId('last-name'), 'attr') ?>" name="<?= esc($fieldName('last_name'), 'attr') ?>" value="<?= esc(familyPersonValue($values, 'last_name'), 'attr') ?>">
            </div>

            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label" for="<?= esc($fieldId('first-name'), 'attr') ?>">First Name</label>
                <input class="form-control" type="text" id="<?= esc($fieldId('first-name'), 'attr') ?>" name="<?= esc($fieldName('first_name'), 'attr') ?>" value="<?= esc(familyPersonValue($values, 'first_name'), 'attr') ?>">
            </div>

            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label" for="<?= esc($fieldId('middle-name'), 'attr') ?>">Middle Name</label>
                <input class="form-control" type="text" id="<?= esc($fieldId('middle-name'), 'attr') ?>" name="<?= esc($fieldName('middle_name'), 'attr') ?>" value="<?= esc(familyPersonValue($values, 'middle_name'), 'attr') ?>">
            </div>

            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label" for="<?= esc($fieldId('suffix'), 'attr') ?>">Suffix</label>
                <select class="form-select" id="<?= esc($fieldId('suffix'), 'attr') ?>" name="<?= esc($fieldName('suffix'), 'attr') ?>">
                    <option<?= familyPersonValue($values, 'suffix') === '' ? ' selected' : '' ?>>Select</option>
                    <?php foreach (familySelectOptions('suffix') as $option): ?>
                        <option value="<?= esc($option, 'attr') ?>"<?= familySelected($values, 'suffix', $option) ?>><?= esc($option) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label" for="<?= esc($fieldId('date-of-birth'), 'attr') ?>">Date of birth</label>
                <input class="form-control" type="date" id="<?= esc($fieldId('date-of-birth'), 'attr') ?>" name="<?= esc($fieldName('date_of_birth'), 'attr') ?>" value="<?= esc(familyPersonValue($values, 'date_of_birth'), 'attr') ?>">
            </div>

            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label" for="<?= esc($fieldId('sex'), 'attr') ?>">Sex</label>
                <select class="form-select" id="<?= esc($fieldId('sex'), 'attr') ?>" name="<?= esc($fieldName('sex'), 'attr') ?>">
                    <option<?= familyPersonValue($values, 'sex') === '' ? ' selected' : '' ?>>Select</option>
                    <?php foreach (familySelectOptions('sex') as $option): ?>
                        <option value="<?= esc($option, 'attr') ?>"<?= familySelected($values, 'sex', $option) ?>><?= esc($option) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label" for="<?= esc($fieldId('civil-status'), 'attr') ?>">Civil status</label>
                <select class="form-select" id="<?= esc($fieldId('civil-status'), 'attr') ?>" name="<?= esc($fieldName('civil_status'), 'attr') ?>">
                    <option<?= familyPersonValue($values, 'civil_status') === '' ? ' selected' : '' ?>>Select</option>
                    <?php foreach (familySelectOptions('civil_status') as $option): ?>
                        <option value="<?= esc($option, 'attr') ?>"<?= familySelected($values, 'civil_status', $option) ?>><?= esc($option) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label" for="<?= esc($fieldId('contact-number'), 'attr') ?>">Contact number</label>
                <input class="form-control" type="tel" id="<?= esc($fieldId('contact-number'), 'attr') ?>" name="<?= esc($fieldName('contact_number'), 'attr') ?>" value="<?= esc(familyPersonValue($values, 'contact_number'), 'attr') ?>">
            </div>

            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label" for="<?= esc($fieldId('religion'), 'attr') ?>">Religion</label>
                <input
                    class="form-control"
                    type="text"
                    id="<?= esc($fieldId('religion'), 'attr') ?>"
                    name="<?= esc($fieldName('religion'), 'attr') ?>"
                    value="<?= esc(familyPersonValue($values, 'religion'), 'attr') ?>"
                    list="<?= esc($fieldId('religion-options'), 'attr') ?>"
                    placeholder="Select"
                    autocomplete="off"
                    data-family-search-picker
                >
                <datalist id="<?= esc($fieldId('religion-options'), 'attr') ?>">
                    <?php foreach (familySelectOptions('religion') as $option): ?>
                        <option value="<?= esc($option, 'attr') ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </div>

            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label" for="<?= esc($fieldId('education'), 'attr') ?>">Education</label>
                <input
                    class="form-control"
                    type="text"
                    id="<?= esc($fieldId('education'), 'attr') ?>"
                    name="<?= esc($fieldName('education'), 'attr') ?>"
                    value="<?= esc(familyPersonValue($values, 'education'), 'attr') ?>"
                    list="<?= esc($fieldId('education-options'), 'attr') ?>"
                    placeholder="Select"
                    autocomplete="off"
                    data-family-search-picker
                >
                <datalist id="<?= esc($fieldId('education-options'), 'attr') ?>">
                    <?php foreach (familySelectOptions('education') as $option): ?>
                        <option value="<?= esc($option, 'attr') ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </div>

            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label" for="<?= esc($fieldId('job'), 'attr') ?>">Job</label>
                <input
                    class="form-control"
                    type="text"
                    id="<?= esc($fieldId('job'), 'attr') ?>"
                    name="<?= esc($fieldName('job'), 'attr') ?>"
                    value="<?= esc(familyPersonValue($values, 'job'), 'attr') ?>"
                    list="<?= esc($fieldId('job-options'), 'attr') ?>"
                    placeholder="Select"
                    autocomplete="off"
                    data-family-search-picker
                >
                <datalist id="<?= esc($fieldId('job-options'), 'attr') ?>">
                    <?php foreach (familySelectOptions('job') as $option): ?>
                        <option value="<?= esc($option, 'attr') ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </div>

            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label" for="<?= esc($fieldId('monthly-income'), 'attr') ?>">Monthly income</label>
                <select class="form-select" id="<?= esc($fieldId('monthly-income'), 'attr') ?>" name="<?= esc($fieldName('monthly_income'), 'attr') ?>">
                    <option<?= familyPersonValue($values, 'monthly_income') === '' ? ' selected' : '' ?>>Select</option>
                    <?php foreach (familySelectOptions('monthly_income') as $option): ?>
                        <option value="<?= esc($option, 'attr') ?>"<?= familySelected($values, 'monthly_income', $option) ?>><?= esc($option) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($showRelationship): ?>
                <div class="col-12 col-md-6 col-xl-3">
                    <label class="form-label" for="<?= esc($fieldId('relationship'), 'attr') ?>">Relationship</label>
                    <select class="form-select" id="<?= esc($fieldId('relationship'), 'attr') ?>" name="<?= esc($fieldName('relationship'), 'attr') ?>">
                        <option<?= familyPersonValue($values, 'relationship') === '' ? ' selected' : '' ?>>Select</option>
                        <?php foreach (familySelectOptions('relationship') as $option): ?>
                            <option value="<?= esc($option, 'attr') ?>"<?= familySelected($values, 'relationship', $option) ?>><?= esc($option) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <?php if ($showAddress): ?>
                <div class="col-12 col-xl-9">
                    <label class="form-label" for="<?= esc($fieldId('address'), 'attr') ?>">Address</label>
                    <input
                        class="form-control"
                        type="text"
                        id="<?= esc($fieldId('address'), 'attr') ?>"
                        name="<?= esc($fieldName('address'), 'attr') ?>"
                        placeholder=""
                        value="<?= esc(familyPersonValue($values, 'address'), 'attr') ?>"
                    >
                </div>

                <div class="col-12 col-md-6 col-xl-3">
                    <label class="form-label" for="<?= esc($fieldId('barangay'), 'attr') ?>">Barangay</label>
                    <input
                        class="form-control"
                        type="text"
                        id="<?= esc($fieldId('barangay'), 'attr') ?>"
                        name="<?= esc($fieldName('barangay'), 'attr') ?>"
                        value="<?= esc(familyPersonValue($values, 'barangay'), 'attr') ?>"
                        list="<?= esc($fieldId('barangay-options'), 'attr') ?>"
                        placeholder="Select"
                        autocomplete="off"
                        data-family-search-picker
                    >
                    <datalist id="<?= esc($fieldId('barangay-options'), 'attr') ?>">
                        <?php foreach (familySelectOptions('barangay') as $barangay): ?>
                            <option value="<?= esc($barangay, 'attr') ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}

if (! function_exists('renderSectorServices')) {
    function renderSectorServices(array $sectorsByCode, array $servicesByCategory, array $sectorSectionLabels, array $serviceSectionLabels, array $selectedSectorIds = [], array $selectedServiceIds = [], string $namePrefix = ''): void
    {
        $sectorName = $namePrefix === '' ? 'sector_ids[]' : $namePrefix . '[sector_ids][]';
        $serviceName = $namePrefix === '' ? 'service_ids[]' : $namePrefix . '[service_ids][]';
        ?>
        <div class="family-step-two-grid">
            <div class="family-choice-group">
                <h2 id="sector-services-title" class="family-form-title">Sectors</h2>

                <div class="family-choice-box" role="group" aria-label="Sectors">
                    <?php if ($sectorsByCode === []): ?>
                        <p class="family-choice-empty">No sectors available.</p>
                    <?php else: ?>
                        <?php foreach ($sectorsByCode as $code => $sectors): ?>
                            <section class="family-choice-section" aria-label="<?= esc($sectorSectionLabels[$code] ?? (string) $code, 'attr') ?>">
                                <h3><?= esc($sectorSectionLabels[$code] ?? (string) $code) ?></h3>

                                <?php foreach ($sectors as $sector): ?>
                                    <?php
                                    $sectorId = (string) ($sector['sectorID'] ?? '');
                                    $sectorCode = trim((string) ($sector['shortcode'] ?? ''));
                                    $sectorName = trim((string) ($sector['name'] ?? ''));
                                    $sectorLabel = trim($sectorCode . ' - ' . $sectorName, ' -');
                                    ?>
                                    <label class="family-choice-row">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            name="<?= esc($sectorName, 'attr') ?>"
                                            value="<?= esc($sectorId, 'attr') ?>"
                                            <?= familyChecked($selectedSectorIds, $sectorId) ?>
                                        >
                                        <span><?= esc($sectorLabel) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </section>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="family-choice-group">
                <h2 class="family-form-title">Services and Programs Available</h2>

                <div class="family-choice-box" role="group" aria-label="Services and programs available">
                    <?php if ($servicesByCategory === []): ?>
                        <p class="family-choice-empty">No services or programs available.</p>
                    <?php else: ?>
                        <?php foreach ($servicesByCategory as $category => $services): ?>
                            <section class="family-choice-section" aria-label="<?= esc($serviceSectionLabels[$category] ?? (string) $category, 'attr') ?>">
                                <h3><?= esc($serviceSectionLabels[$category] ?? (string) $category) ?></h3>

                                <?php foreach ($services as $service): ?>
                                    <?php
                                    $serviceId = (string) ($service['serviceID'] ?? '');
                                    $serviceName = trim((string) ($service['name'] ?? ''));
                                    $serviceDescription = trim((string) ($service['description'] ?? ''));
                                    $serviceLabel = $serviceDescription === ''
                                        ? $serviceName
                                        : $serviceName . ' - ' . $serviceDescription;
                                    ?>
                                    <label class="family-choice-row">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            name="<?= esc($serviceName, 'attr') ?>"
                                            value="<?= esc($serviceId, 'attr') ?>"
                                            <?= familyChecked($selectedServiceIds, $serviceId) ?>
                                        >
                                        <span><?= esc($serviceLabel) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </section>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
}
?>

<?php
$mode = (string) ($mode ?? 'new');
$windowTitle = (string) ($windowTitle ?? 'New Family Record');
$submitLabel = (string) ($submitLabel ?? 'Save');
$recordHead = is_array($recordHead ?? null) ? $recordHead : [];
$familyMembers = is_array($familyMembers ?? null) ? $familyMembers : [];
$memberServiceIds = is_array($memberServiceIds ?? null) ? $memberServiceIds : [];
$recordHeadId = (int) ($recordHead['memberID'] ?? 0);
$recordHeadSectorIds = \App\Libraries\SectorIds::normalize($recordHead['sectorID'] ?? []);
$recordHeadServiceIds = $memberServiceIds[$recordHeadId] ?? [];
$recordHeadName = trim(implode(' ', array_filter([
    familyPersonValue($recordHead, 'first_name'),
    familyPersonValue($recordHead, 'middle_name'),
    familyPersonValue($recordHead, 'last_name'),
])));
$recordHeadAddress = trim(implode(', ', array_filter([
    familyPersonValue($recordHead, 'address'),
    familyPersonValue($recordHead, 'barangay'),
])));
?>

<?= family_modal_styles() ?>

<div class="family-window-overlay" data-family-window-backdrop>
    <section
        class="family-window"
        data-family-window
        role="dialog"
        aria-modal="true"
        aria-labelledby="family-window-title"
        tabindex="-1"
    >
        <header class="family-window-header">
            <div>
                <p class="family-window-kicker">Manage Records</p>
                <h2 id="family-window-title"><?= esc($windowTitle) ?></h2>
            </div>

            <button class="family-window-close" type="button" data-family-window-close aria-label="Close <?= esc($windowTitle, 'attr') ?> window">
                <span aria-hidden="true">&times;</span>
            </button>
        </header>

        <div class="family-window-body">
            <section class="family-record-wizard" aria-label="Family record form">
                <nav class="family-wizard-steps" aria-label="Record form steps">
                    <button class="family-wizard-step is-active" type="button" data-step="1" aria-current="step">
                        <span class="family-step-number">1</span>
                        <span class="family-step-label">Head of Family</span>
                    </button>

                    <button class="family-wizard-step" type="button" data-step="2">
                        <span class="family-step-number">2</span>
                        <span class="family-step-label">Sector &amp; Services</span>
                    </button>

                    <button class="family-wizard-step" type="button" data-step="3">
                        <span class="family-step-number">3</span>
                        <span class="family-step-label">Members</span>
                    </button>
                </nav>

                <form class="family-record-form" action="#" method="post">
                    <section class="family-form-panel" data-step-panel="1" aria-labelledby="record-head-title">
                        <div class="family-form-divider" aria-hidden="true"></div>

                        <h2 id="record-head-title" class="family-form-title">Record Head</h2>

                        <?php renderPersonFields('family', '', true, false, $recordHead); ?>
                    </section>

                    <section class="family-form-panel family-form-panel-hidden" data-step-panel="2" aria-labelledby="sector-services-title" hidden>
                        <div class="family-form-divider" aria-hidden="true"></div>
                        <?php renderSectorServices(
                            $sectorsByCode ?? [],
                            $servicesByCategory ?? [],
                            $sectorSectionLabels,
                            $serviceSectionLabels,
                            $recordHeadSectorIds,
                            $recordHeadServiceIds
                        ); ?>
                    </section>

                    <section class="family-form-panel family-form-panel-hidden" data-step-panel="3" aria-labelledby="members-title" hidden>
                        <div class="family-form-divider" aria-hidden="true"></div>

                        <article class="family-summary-card">
                            <h2 id="members-title" class="family-summary-title">Current Record Head</h2>

                            <div class="family-summary-grid">
                                <p class="family-summary-item">
                                    <strong>Name:</strong>
                                    <span><?= esc($recordHeadName === '' ? '-' : $recordHeadName) ?></span>
                                </p>

                                <p class="family-summary-item">
                                    <strong>Date of birth:</strong>
                                    <span><?= esc(familyPersonValue($recordHead, 'date_of_birth') ?: '-') ?></span>
                                </p>

                                <p class="family-summary-item">
                                    <strong>Sex:</strong>
                                    <span><?= esc(familyPersonValue($recordHead, 'sex') ?: '-') ?></span>
                                </p>

                                <p class="family-summary-item">
                                    <strong>Civil status:</strong>
                                    <span><?= esc(familyPersonValue($recordHead, 'civil_status') ?: '-') ?></span>
                                </p>

                                <p class="family-summary-item">
                                    <strong>Contact:</strong>
                                    <span><?= esc(familyPersonValue($recordHead, 'contact_number') ?: '-') ?></span>
                                </p>

                                <p class="family-summary-item">
                                    <strong>Religion:</strong>
                                    <span><?= esc(familyPersonValue($recordHead, 'religion') ?: '-') ?></span>
                                </p>

                                <p class="family-summary-item">
                                    <strong>Education:</strong>
                                    <span><?= esc(familyPersonValue($recordHead, 'education') ?: '-') ?></span>
                                </p>

                                <p class="family-summary-item">
                                    <strong>Job:</strong>
                                    <span><?= esc(familyPersonValue($recordHead, 'job') ?: '-') ?></span>
                                </p>

                                <p class="family-summary-item">
                                    <strong>Monthly income:</strong>
                                    <span><?= esc(familyPersonValue($recordHead, 'monthly_income') ?: '-') ?></span>
                                </p>

                                <p class="family-summary-item family-summary-wide">
                                    <strong>Address:</strong>
                                    <span><?= esc($recordHeadAddress === '' ? '-' : $recordHeadAddress) ?></span>
                                </p>
                            </div>

                            <div class="family-summary-lists">
                                <div class="family-summary-list">
                                    <strong>Sector(s):</strong>
                                    <p>-</p>
                                </div>

                                <div class="family-summary-list">
                                    <strong>Services and programs availed:</strong>
                                    <p>-</p>
                                </div>
                            </div>
                        </article>

                        <div class="family-members-list" data-members-list>
                            <?php foreach ($familyMembers as $memberIndex => $familyMember): ?>
                                <?php
                                $memberId = (int) ($familyMember['memberID'] ?? 0);
                                $memberNamePrefix = 'members[' . $memberIndex . ']';
                                ?>
                                <section class="family-member-card">
                                    <div class="family-member-card-actions">
                                        <button class="btn btn-danger" type="button" data-remove-member>
                                            Remove
                                        </button>
                                    </div>

                                    <?php renderPersonFields('family-member-' . $memberIndex, $memberNamePrefix, false, true, $familyMember); ?>
                                    <?php renderSectorServices(
                                        $sectorsByCode ?? [],
                                        $servicesByCategory ?? [],
                                        $sectorSectionLabels,
                                        $serviceSectionLabels,
                                        \App\Libraries\SectorIds::normalize($familyMember['sectorID'] ?? []),
                                        $memberServiceIds[$memberId] ?? [],
                                        $memberNamePrefix
                                    ); ?>
                                </section>
                            <?php endforeach; ?>
                        </div>

                        <div class="family-member-actions family-member-actions-bottom">
                            <button class="btn btn-success" type="button" data-add-member>
                                Add Member
                            </button>
                        </div>

                        <template id="family-member-template">
                            <section class="family-member-card">
                                <div class="family-member-card-actions">
                                    <button class="btn btn-danger" type="button" data-remove-member>
                                        Remove
                                    </button>
                                </div>

                                <?php renderPersonFields('family-member-__INDEX__', 'members[__INDEX__]', false, true); ?>
                                <?php renderSectorServices(
                                    $sectorsByCode ?? [],
                                    $servicesByCategory ?? [],
                                    $sectorSectionLabels,
                                    $serviceSectionLabels,
                                    [],
                                    [],
                                    'members[__INDEX__]'
                                    ); ?>
                            </section>
                        </template>
                    </section>
                </form>
            </section>
        </div>

        <footer class="family-window-footer">
            <button class="family-window-footer-button" type="button">Clear</button>
            <button class="family-window-footer-button" type="button">Previous</button>
            <button class="family-window-footer-button" type="button">Next</button>
            <button class="family-window-footer-button" type="button"><?= esc($submitLabel) ?></button>
        </footer>
    </section>
</div>
