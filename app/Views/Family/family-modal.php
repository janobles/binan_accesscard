<?php
/**
 * Bootstrap Add Family Record modal fragment.
 *
 * Expected option variables come from FamilyFormOptionsModel::getViewData().
 * This view is intentionally Bootstrap-first so the Add/Edit frontend can be
 * rebuilt cleanly without reviving the deleted custom form engine.
 */
$action = (string) ($action ?? site_url('families'));
$fieldPrefix = (string) ($fieldPrefix ?? 'family-add');
$sectorOptions = (array) ($sectorOptions ?? []);
$suffixOptions = (array) ($suffixOptions ?? []);
$sexOptions = (array) ($sexOptions ?? ['Male', 'Female']);
$civilOptions = (array) ($civilOptions ?? []);
$barangayOptions = (array) ($barangayOptions ?? []);
$relationshipOptions = (array) ($relationshipOptions ?? []);
$educationOptions = (array) ($educationOptions ?? []);
$jobOptions = (array) ($jobOptions ?? []);
$religionOptions = (array) ($religionOptions ?? []);
$incomeOptions = (array) ($incomeOptions ?? []);
$servicesByCategory = (array) ($servicesByCategory ?? []);
$saveDisabled = (bool) ($saveDisabled ?? false);
$saveDisabledMessage = trim((string) ($saveDisabledMessage ?? ''));

$oldArray = static function (string $key): array {
    $value = old($key);

    return is_array($value) ? array_map('strval', $value) : [];
};

$selectedSectorIds = $oldArray('sector_ids');
$selectedServiceIds = $oldArray('service_ids');

$oldValue = static function (string $key, string $default = ''): string {
    return (string) old($key, $default);
};

$optionValue = static function (mixed $option): string {
    if (is_array($option)) {
        return (string) ($option['value'] ?? $option['id'] ?? $option['sectorID'] ?? $option['serviceID'] ?? $option['label'] ?? $option['name'] ?? '');
    }

    return (string) $option;
};

$optionLabel = static function (mixed $option): string {
    if (is_array($option)) {
        return (string) ($option['label'] ?? $option['name'] ?? $option['sector_name'] ?? $option['service_name'] ?? $option['value'] ?? '');
    }

    return (string) $option;
};

$sectorLabel = static function (array $sector): string {
    $shortcode = trim((string) ($sector['shortcode'] ?? $sector['code'] ?? ''));
    $name = trim((string) ($sector['sector_name'] ?? $sector['name'] ?? $sector['label'] ?? ''));

    if ($shortcode !== '' && $name !== '') {
        return mb_strtoupper($shortcode, 'UTF-8') . ' - ' . $name;
    }

    return $shortcode !== '' ? mb_strtoupper($shortcode, 'UTF-8') : $name;
};

$serviceLabel = static function (array $service): string {
    $code = trim((string) ($service['code'] ?? $service['shortcode'] ?? ''));
    $name = trim((string) ($service['service_name'] ?? $service['name'] ?? $service['label'] ?? ''));

    if ($code !== '' && $name !== '') {
        return mb_strtoupper($code, 'UTF-8') . ' - ' . $name;
    }

    return $code !== '' ? mb_strtoupper($code, 'UTF-8') : $name;
};

$selectOptions = static function (array $options, string $selected = '', string $placeholder = 'Select') use ($optionValue, $optionLabel): string {
    $html = '<option value="">' . esc($placeholder) . '</option>';

    foreach ($options as $option) {
        $value = $optionValue($option);
        $label = $optionLabel($option);
        $hasExplicitValue = is_array($option) && array_key_exists('value', $option);

        if ($value === '' && $label === '') {
            continue;
        }

        if ($hasExplicitValue && $value === '' && strcasecmp($label, $placeholder) === 0) {
            continue;
        }

        $value = $value !== '' || $hasExplicitValue ? $value : $label;
        $label = $label !== '' ? $label : $value;
        $html .= '<option value="' . esc($value, 'attr') . '"' . ($selected === $value ? ' selected' : '') . '>' . esc($label) . '</option>';
    }

    return $html;
};
?>

<div class="container-fluid px-0 family-entry-form">
    <div class="family-entry-header d-flex flex-wrap align-items-start justify-content-between gap-3">
        <div>
            <p class="family-entry-kicker mb-1">Manage Records</p>
            <h2 class="family-entry-title mb-0">New Family Record</h2>
        </div>
    </div>

    <?php if ($saveDisabled && $saveDisabledMessage !== ''): ?>
        <div class="alert alert-warning small" role="alert">
            <?= esc($saveDisabledMessage) ?>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= esc($action, 'attr') ?>" autocomplete="off">
        <?= csrf_field() ?>
        <input type="hidden" name="entry_type" value="head">

        <div class="btn-toolbar family-entry-step-toolbar" role="toolbar" aria-label="Family record steps">
            <div class="btn-group w-100" id="<?= esc($fieldPrefix, 'attr') ?>Tabs" role="tablist" aria-label="Family record steps">
                <button class="btn btn-outline-primary active" id="<?= esc($fieldPrefix, 'attr') ?>HeadTab" data-bs-toggle="pill" data-bs-target="#<?= esc($fieldPrefix, 'attr') ?>HeadPane" type="button" role="tab" aria-controls="<?= esc($fieldPrefix, 'attr') ?>HeadPane" aria-selected="true">
                    <span class="badge rounded-pill text-bg-success me-2">1</span>Head of Family
                </button>
                <button class="btn btn-outline-primary" id="<?= esc($fieldPrefix, 'attr') ?>MemberTab" data-bs-toggle="pill" data-bs-target="#<?= esc($fieldPrefix, 'attr') ?>MemberPane" type="button" role="tab" aria-controls="<?= esc($fieldPrefix, 'attr') ?>MemberPane" aria-selected="false">
                    <span class="badge rounded-pill text-bg-secondary me-2">2</span>Members
                </button>
            </div>
        </div>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="<?= esc($fieldPrefix, 'attr') ?>HeadPane" role="tabpanel" aria-labelledby="<?= esc($fieldPrefix, 'attr') ?>HeadTab" tabindex="0">
                <section class="family-entry-section">
                    <div class="family-entry-section-header">
                        <h3>Personal Information</h3>
                    </div>
                    <div class="family-entry-section-body">
                        <div class="row g-3">
                            <div class="col-12 col-md-6 col-xl-3">
                                <label class="form-label fw-semibold" for="<?= esc($fieldPrefix, 'attr') ?>HeadLastname">Last Name <span class="text-danger">*</span></label>
                                <input class="form-control" id="<?= esc($fieldPrefix, 'attr') ?>HeadLastname" name="head_lastname" type="text" value="<?= esc($oldValue('head_lastname'), 'attr') ?>" required maxlength="100">
                            </div>
                            <div class="col-12 col-md-6 col-xl-3">
                                <label class="form-label fw-semibold" for="<?= esc($fieldPrefix, 'attr') ?>HeadFirstname">First Name <span class="text-danger">*</span></label>
                                <input class="form-control" id="<?= esc($fieldPrefix, 'attr') ?>HeadFirstname" name="head_firstname" type="text" value="<?= esc($oldValue('head_firstname'), 'attr') ?>" required maxlength="100">
                            </div>
                            <div class="col-12 col-md-6 col-xl-3">
                                <label class="form-label fw-semibold" for="<?= esc($fieldPrefix, 'attr') ?>HeadMiddlename">Middle Name</label>
                                <input class="form-control" id="<?= esc($fieldPrefix, 'attr') ?>HeadMiddlename" name="head_middlename" type="text" value="<?= esc($oldValue('head_middlename'), 'attr') ?>" maxlength="50">
                            </div>
                            <div class="col-12 col-md-6 col-xl-3">
                                <label class="form-label fw-semibold" for="<?= esc($fieldPrefix, 'attr') ?>HeadSuffix">Suffix</label>
                                <select class="form-select" id="<?= esc($fieldPrefix, 'attr') ?>HeadSuffix" name="head_suffix">
                                    <?= $selectOptions($suffixOptions, $oldValue('head_suffix'), 'Select') ?>
                                </select>
                            </div>

                            <div class="col-12 col-md-6 col-xl-3">
                                <label class="form-label fw-semibold" for="<?= esc($fieldPrefix, 'attr') ?>HeadBirthday">Date of Birth <span class="text-danger">*</span></label>
                                <input class="form-control" id="<?= esc($fieldPrefix, 'attr') ?>HeadBirthday" name="head_birthday" type="date" value="<?= esc($oldValue('head_birthday'), 'attr') ?>" required>
                            </div>
                            <div class="col-12 col-md-6 col-xl-3">
                                <label class="form-label fw-semibold" for="<?= esc($fieldPrefix, 'attr') ?>HeadSex">Sex <span class="text-danger">*</span></label>
                                <select class="form-select" id="<?= esc($fieldPrefix, 'attr') ?>HeadSex" name="head_sex" required>
                                    <?= $selectOptions($sexOptions, $oldValue('head_sex'), 'Select') ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-6 col-xl-3">
                                <label class="form-label fw-semibold" for="<?= esc($fieldPrefix, 'attr') ?>HeadCivilStatus">Civil Status <span class="text-danger">*</span></label>
                                <select class="form-select" id="<?= esc($fieldPrefix, 'attr') ?>HeadCivilStatus" name="head_civilstatus" required>
                                    <?= $selectOptions($civilOptions, $oldValue('head_civilstatus'), 'Select') ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-6 col-xl-3">
                                <label class="form-label fw-semibold" for="<?= esc($fieldPrefix, 'attr') ?>HeadContact">Contact Number</label>
                                <input class="form-control" id="<?= esc($fieldPrefix, 'attr') ?>HeadContact" name="head_contactnumber" type="tel" value="<?= esc($oldValue('head_contactnumber'), 'attr') ?>" maxlength="30">
                            </div>

                            <div class="col-12 col-md-6 col-xl-3">
                                <label class="form-label fw-semibold" for="<?= esc($fieldPrefix, 'attr') ?>HeadReligion">Religion</label>
                                <select class="form-select" id="<?= esc($fieldPrefix, 'attr') ?>HeadReligion" name="head_religion">
                                    <?= $selectOptions($religionOptions, $oldValue('head_religion'), 'Select') ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-6 col-xl-3">
                                <label class="form-label fw-semibold" for="<?= esc($fieldPrefix, 'attr') ?>HeadEducation">Education <span class="text-danger">*</span></label>
                                <select class="form-select" id="<?= esc($fieldPrefix, 'attr') ?>HeadEducation" name="head_education" required>
                                    <?= $selectOptions($educationOptions, $oldValue('head_education'), 'Select') ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-6 col-xl-3">
                                <label class="form-label fw-semibold" for="<?= esc($fieldPrefix, 'attr') ?>HeadJob">Job <span class="text-danger">*</span></label>
                                <select class="form-select" id="<?= esc($fieldPrefix, 'attr') ?>HeadJob" name="head_job" required>
                                    <?= $selectOptions($jobOptions, $oldValue('head_job'), 'Select') ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-6 col-xl-3">
                                <label class="form-label fw-semibold" for="<?= esc($fieldPrefix, 'attr') ?>HeadSalary">Monthly Income <span class="text-danger">*</span></label>
                                <select class="form-select" id="<?= esc($fieldPrefix, 'attr') ?>HeadSalary" name="head_salary" required>
                                    <?= $selectOptions($incomeOptions, $oldValue('head_salary'), 'Select') ?>
                                </select>
                            </div>

                            <div class="col-12 col-xl-9">
                                <label class="form-label fw-semibold" for="<?= esc($fieldPrefix, 'attr') ?>HeadAddress">Address <span class="text-danger">*</span></label>
                                <input class="form-control" id="<?= esc($fieldPrefix, 'attr') ?>HeadAddress" name="head_address" type="text" value="<?= esc($oldValue('head_address'), 'attr') ?>" required maxlength="255">
                            </div>
                            <div class="col-12 col-xl-3">
                                <label class="form-label fw-semibold" for="<?= esc($fieldPrefix, 'attr') ?>HeadBarangay">Barangay <span class="text-danger">*</span></label>
                                <select class="form-select" id="<?= esc($fieldPrefix, 'attr') ?>HeadBarangay" name="head_barangay" required>
                                    <?= $selectOptions($barangayOptions, $oldValue('head_barangay'), 'Barangay') ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="family-entry-section">
                    <div class="family-entry-section-header">
                        <h3>Sectors and Services</h3>
                    </div>
                    <div class="family-entry-section-body">
                        <div class="row g-4">
                            <div class="col-12 col-lg-5">
                                <h4 class="family-entry-column-title">Sectors</h4>
                                <div class="family-entry-option-panel">
                                    <?php if ($sectorOptions === []): ?>
                                        <p class="text-muted mb-0">No sector options available.</p>
                                    <?php endif; ?>
                                    <?php foreach ($sectorOptions as $sector): ?>
                                        <?php
                                        $sector = (array) $sector;
                                        $sectorId = (string) ($sector['sectorID'] ?? $sector['id'] ?? '');
                                        $label = $sectorLabel($sector);
                                        ?>
                                        <?php if ($sectorId !== '' && $label !== ''): ?>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="checkbox" id="<?= esc($fieldPrefix . 'Sector' . $sectorId, 'attr') ?>" name="sector_ids[]" value="<?= esc($sectorId, 'attr') ?>" <?= in_array($sectorId, $selectedSectorIds, true) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="<?= esc($fieldPrefix . 'Sector' . $sectorId, 'attr') ?>"><?= esc($label) ?></label>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="col-12 col-lg-7">
                                <h4 class="family-entry-column-title">Services and Programs Available</h4>
                                <div class="accordion" id="<?= esc($fieldPrefix, 'attr') ?>ServicesAccordion">
                                    <?php if ($servicesByCategory === []): ?>
                                        <div class="border rounded-3 p-3 text-muted">No services or programs available.</div>
                                    <?php endif; ?>
                                    <?php $serviceGroupIndex = 0; ?>
                                    <?php foreach ($servicesByCategory as $category => $services): ?>
                                        <?php
                                        $serviceGroupIndex++;
                                        $collapseId = $fieldPrefix . 'Services' . $serviceGroupIndex;
                                        ?>
                                        <div class="accordion-item">
                                            <h5 class="accordion-header">
                                                <button class="accordion-button <?= $serviceGroupIndex === 1 ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?= esc($collapseId, 'attr') ?>" aria-expanded="<?= $serviceGroupIndex === 1 ? 'true' : 'false' ?>" aria-controls="<?= esc($collapseId, 'attr') ?>">
                                                    <?= esc((string) $category) ?>
                                                </button>
                                            </h5>
                                            <div id="<?= esc($collapseId, 'attr') ?>" class="accordion-collapse collapse <?= $serviceGroupIndex === 1 ? 'show' : '' ?>" data-bs-parent="#<?= esc($fieldPrefix, 'attr') ?>ServicesAccordion">
                                                <div class="accordion-body">
                                                    <?php foreach ((array) $services as $service): ?>
                                                        <?php
                                                        $service = (array) $service;
                                                        $serviceId = (string) ($service['serviceID'] ?? $service['id'] ?? '');
                                                        $label = $serviceLabel($service);
                                                        ?>
                                                        <?php if ($serviceId !== '' && $label !== ''): ?>
                                                            <div class="form-check mb-2">
                                                                <input class="form-check-input" type="checkbox" id="<?= esc($fieldPrefix . 'Service' . $serviceId, 'attr') ?>" name="service_ids[]" value="<?= esc($serviceId, 'attr') ?>" <?= in_array($serviceId, $selectedServiceIds, true) ? 'checked' : '' ?>>
                                                                <label class="form-check-label" for="<?= esc($fieldPrefix . 'Service' . $serviceId, 'attr') ?>"><?= esc($label) ?></label>
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <div class="tab-pane fade" id="<?= esc($fieldPrefix, 'attr') ?>MemberPane" role="tabpanel" aria-labelledby="<?= esc($fieldPrefix, 'attr') ?>MemberTab" tabindex="0">
                <section class="family-entry-section">
                    <div class="family-entry-section-header">
                        <h3>Family Members</h3>
                    </div>
                    <div class="family-entry-section-body">
                        <div class="row g-3">
                            <div class="col-12 col-md-6 col-xl-3">
                                <label class="form-label fw-semibold" for="<?= esc($fieldPrefix, 'attr') ?>MemberLastname">Last Name</label>
                                <input class="form-control" id="<?= esc($fieldPrefix, 'attr') ?>MemberLastname" name="members[0][lastname]" type="text" maxlength="100">
                            </div>
                            <div class="col-12 col-md-6 col-xl-3">
                                <label class="form-label fw-semibold" for="<?= esc($fieldPrefix, 'attr') ?>MemberFirstname">First Name</label>
                                <input class="form-control" id="<?= esc($fieldPrefix, 'attr') ?>MemberFirstname" name="members[0][firstname]" type="text" maxlength="100">
                            </div>
                            <div class="col-12 col-md-6 col-xl-3">
                                <label class="form-label fw-semibold" for="<?= esc($fieldPrefix, 'attr') ?>MemberMiddlename">Middle Name</label>
                                <input class="form-control" id="<?= esc($fieldPrefix, 'attr') ?>MemberMiddlename" name="members[0][middlename]" type="text" maxlength="50">
                            </div>
                            <div class="col-12 col-md-6 col-xl-3">
                                <label class="form-label fw-semibold" for="<?= esc($fieldPrefix, 'attr') ?>MemberRelationship">Relationship</label>
                                <select class="form-select" id="<?= esc($fieldPrefix, 'attr') ?>MemberRelationship" name="members[0][relationship]">
                                    <?= $selectOptions($relationshipOptions, '', 'Select') ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-6 col-xl-3">
                                <label class="form-label fw-semibold" for="<?= esc($fieldPrefix, 'attr') ?>MemberBirthday">Date of Birth</label>
                                <input class="form-control" id="<?= esc($fieldPrefix, 'attr') ?>MemberBirthday" name="members[0][birthday]" type="date">
                            </div>
                            <div class="col-12 col-md-6 col-xl-3">
                                <label class="form-label fw-semibold" for="<?= esc($fieldPrefix, 'attr') ?>MemberSex">Sex</label>
                                <select class="form-select" id="<?= esc($fieldPrefix, 'attr') ?>MemberSex" name="members[0][sex]">
                                    <?= $selectOptions($sexOptions, '', 'Select') ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-6 col-xl-3">
                                <label class="form-label fw-semibold" for="<?= esc($fieldPrefix, 'attr') ?>MemberCivilStatus">Civil Status</label>
                                <select class="form-select" id="<?= esc($fieldPrefix, 'attr') ?>MemberCivilStatus" name="members[0][civilstatus]">
                                    <?= $selectOptions($civilOptions, '', 'Select') ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-6 col-xl-3">
                                <label class="form-label fw-semibold" for="<?= esc($fieldPrefix, 'attr') ?>MemberContact">Contact Number</label>
                                <input class="form-control" id="<?= esc($fieldPrefix, 'attr') ?>MemberContact" name="members[0][contactnumber]" type="tel" maxlength="30">
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <div class="btn-toolbar family-entry-actions" role="toolbar" aria-label="Family form actions">
            <div class="btn-group" role="group" aria-label="Form actions">
                <button class="btn btn-danger" type="reset">Clear</button>
                <button class="btn btn-success" type="button" data-bs-toggle="pill" data-bs-target="#<?= esc($fieldPrefix, 'attr') ?>MemberPane" aria-controls="<?= esc($fieldPrefix, 'attr') ?>MemberPane">Next</button>
                <button class="btn btn-primary" type="submit" <?= $saveDisabled ? 'disabled aria-disabled="true"' : '' ?>>Save Family Record</button>
            </div>
        </div>
    </form>
</div>
