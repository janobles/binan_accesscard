<?php
helper('family_modal');
extract(family_modal_prepare(get_defined_vars()), EXTR_OVERWRITE);

// Members already on the record (Update mode); empty for a new record. Rendered
// server-side so an edit re-posts them — FamilyController::update() rebuilds the
// member list from the submission, so omitting them would drop existing members.
$existingMembers = (array) ($existingMembers ?? []);

/**
 * Renders one repeatable family-member row. $index is an int for a pre-filled
 * existing member, or the literal '__INDEX__' placeholder in the <template>;
 * manage-family-modal.js swaps the placeholder for the next counter on Add.
 * Field names post as members[$index][...] to match FamilyController::store()/update().
 */
$renderMemberRow = static function ($index, array $m = []) use (
    $selectOptions,
    $sectorLabel,
    $serviceLabel,
    $suffixOptions,
    $sexOptions,
    $civilOptions,
    $religionOptions,
    $educationOptions,
    $jobOptions,
    $incomeOptions,
    $relationshipOptions,
    $sectorCatalog,
    $servicesByCategory
): string {
    $i = (string) $index;
    $field = static fn (string $name): string => 'members[' . $i . '][' . $name . ']';
    $val = static fn (string $key): string => (string) ($m[$key] ?? '');
    $selectedSectors = array_map('strval', (array) ($m['sector_ids'] ?? []));
    $selectedServices = array_map('strval', (array) ($m['service_ids'] ?? []));

    ob_start();
    ?>
    <div class="family-member-card" data-family-member-row>
        <div class="family-member-card-header">
            <strong class="family-member-card-title">Member</strong>
            <button type="button" class="btn btn-sm btn-outline-danger" data-family-member-remove>Remove</button>
        </div>
        <div class="row g-3">
            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label">Last Name</label>
                <input class="form-control" type="text" name="<?= esc($field('lastname'), 'attr') ?>" value="<?= esc($val('lastname'), 'attr') ?>">
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label">First Name</label>
                <input class="form-control" type="text" name="<?= esc($field('firstname'), 'attr') ?>" value="<?= esc($val('firstname'), 'attr') ?>">
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label">Middle Name</label>
                <input class="form-control" type="text" name="<?= esc($field('middlename'), 'attr') ?>" value="<?= esc($val('middlename'), 'attr') ?>">
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label">Suffix</label>
                <select class="form-select" name="<?= esc($field('suffix'), 'attr') ?>"><?= $selectOptions($suffixOptions, $val('suffix'), 'Select') ?></select>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label">Date of birth</label>
                <input class="form-control" type="date" name="<?= esc($field('birthday'), 'attr') ?>" value="<?= esc($val('birthday'), 'attr') ?>">
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label">Sex</label>
                <select class="form-select" name="<?= esc($field('sex'), 'attr') ?>"><?= $selectOptions($sexOptions, $val('sex'), 'Select') ?></select>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label">Civil status</label>
                <select class="form-select js-other-select" data-other-field="civilstatus" data-initial-value="<?= esc($val('civilstatus'), 'attr') ?>" name="<?= esc($field('civilstatus'), 'attr') ?>"><?= $selectOptions($civilOptions, $val('civilstatus'), 'Select') ?></select>
                <input class="form-control mt-2 js-other-input family-form-hidden" data-other-for="civilstatus" placeholder="Enter civil status">
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label">Contact number</label>
                <input class="form-control" type="tel" name="<?= esc($field('contactnumber'), 'attr') ?>" value="<?= esc($val('contactnumber'), 'attr') ?>" maxlength="30">
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label">Religion</label>
                <select class="form-select js-other-select" data-other-field="religion" data-initial-value="<?= esc($val('religion'), 'attr') ?>" name="<?= esc($field('religion'), 'attr') ?>"><?= $selectOptions($religionOptions, $val('religion'), 'Select') ?></select>
                <input class="form-control mt-2 js-other-input family-form-hidden" data-other-for="religion" placeholder="Enter religion">
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label">Education</label>
                <select class="form-select js-other-select" data-other-field="education" data-initial-value="<?= esc($val('education'), 'attr') ?>" name="<?= esc($field('education'), 'attr') ?>"><?= $selectOptions($educationOptions, $val('education'), 'Select') ?></select>
                <input class="form-control mt-2 js-other-input family-form-hidden" data-other-for="education" placeholder="Enter education">
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label">Job</label>
                <select class="form-select js-other-select" data-other-field="job" data-initial-value="<?= esc($val('job'), 'attr') ?>" name="<?= esc($field('job'), 'attr') ?>"><?= $selectOptions($jobOptions, $val('job'), 'Select') ?></select>
                <input class="form-control mt-2 js-other-input family-form-hidden" data-other-for="job" placeholder="Enter job">
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label">Monthly income</label>
                <select class="form-select" name="<?= esc($field('salary'), 'attr') ?>"><?= $selectOptions($incomeOptions, $val('salary'), 'Select') ?></select>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label">Relationship</label>
                <select class="form-select js-other-select" data-other-field="relationship" data-initial-value="<?= esc($val('relationship'), 'attr') ?>" name="<?= esc($field('relationship'), 'attr') ?>"><?= $selectOptions($relationshipOptions, $val('relationship'), 'Select') ?></select>
                <input class="form-control mt-2 js-other-input family-form-hidden" data-other-for="relationship" placeholder="Enter relationship">
            </div>
        </div>

        <div class="row g-4 mt-1">
            <div class="col-12 col-lg-5">
                <h5 class="family-column-title">Sectors</h5>
                <div class="family-option-box family-option-box--sm">
                    <?php if ($sectorCatalog === []): ?>
                        <p class="text-muted mb-0">No sectors available.</p>
                    <?php endif; ?>
                    <?php foreach ($sectorCatalog as $sectorGroup): ?>
                        <?php
                        $sectorGroup = array_values((array) $sectorGroup);
                        if ($sectorGroup === []) { continue; }
                        $groupTitle = trim((string) ($sectorGroup[0]['category_label'] ?? ''));
                        ?>
                        <div class="family-option-group">
                            <?php if ($groupTitle !== ''): ?><p class="family-option-group-title"><?= esc($groupTitle) ?></p><?php endif; ?>
                            <?php foreach ($sectorGroup as $sector): ?>
                                <?php
                                $sector = (array) $sector;
                                $sectorId = (string) ($sector['sectorID'] ?? $sector['id'] ?? '');
                                $label = $sectorLabel($sector);
                                $isArchived = ! empty($sector['is_archived']);
                                ?>
                                <?php if ($sectorId !== '' && $label !== ''): ?>
                                    <label class="form-check family-choice<?= $isArchived ? ' family-choice--archived' : '' ?>">
                                        <input type="checkbox" name="<?= esc($field('sector_ids') . '[]', 'attr') ?>" value="<?= esc($sectorId, 'attr') ?>" data-label="<?= esc($label, 'attr') ?>"<?= $isArchived ? ' data-archived="1"' : '' ?> <?= in_array($sectorId, $selectedSectors, true) ? 'checked' : '' ?>>
                                        <span class="form-check-label"><?= esc($label) ?><?php if ($isArchived): ?> <span class="family-choice-badge">Archived</span><?php endif; ?></span>
                                    </label>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-12 col-lg-7">
                <h5 class="family-column-title">Services and Programs Available</h5>
                <div class="family-option-box family-option-box--sm">
                    <?php if ($servicesByCategory === []): ?>
                        <p class="text-muted mb-0">No services available.</p>
                    <?php endif; ?>
                    <?php foreach ($servicesByCategory as $category => $services): ?>
                        <div class="family-option-group">
                            <p class="family-option-group-title"><?= esc((string) $category) ?></p>
                            <?php foreach ((array) $services as $service): ?>
                                <?php
                                $service = (array) $service;
                                $serviceId = (string) ($service['serviceID'] ?? $service['id'] ?? '');
                                $label = $serviceLabel($service);
                                $isArchived = ! empty($service['is_archived']);
                                ?>
                                <?php if ($serviceId !== '' && $label !== ''): ?>
                                    <label class="form-check family-choice<?= $isArchived ? ' family-choice--archived' : '' ?>">
                                        <input type="checkbox" name="<?= esc($field('service_ids') . '[]', 'attr') ?>" value="<?= esc($serviceId, 'attr') ?>" data-label="<?= esc($label, 'attr') ?>"<?= $isArchived ? ' data-archived="1"' : '' ?> <?= in_array($serviceId, $selectedServices, true) ? 'checked' : '' ?>>
                                        <span class="form-check-label"><?= esc($label) ?><?php if ($isArchived): ?> <span class="family-choice-badge">Archived</span><?php endif; ?></span>
                                    </label>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
    return (string) ob_get_clean();
};
?>

<div class="family-entry-form" data-family-entry-form>
    <div class="family-entry-header">
        <div>
            <h2 class="family-entry-title"><?= esc($modalTitle) ?></h2>
        </div>
    </div>

    <form method="post" action="<?= esc($action, 'attr') ?>" autocomplete="off">
        <?= csrf_field() ?>
        <input type="hidden" name="entry_type" value="head">
        <input type="hidden" name="form_mode" value="<?= esc($modalMode, 'attr') ?>">
        <?php if ($headId > 0): ?>
            <input type="hidden" name="head_id" value="<?= esc((string) $headId, 'attr') ?>">
        <?php endif; ?>
        <?php /* Truncation guard: manage-family-modal.js sets this to the live member-row
                 count just before submit. The server compares it against the members it
                 actually received to catch a POST silently clipped by max_input_vars. */ ?>
        <input type="hidden" name="members_meta_count" value="0" data-members-count>

        <div class="btn-toolbar family-entry-steps" role="toolbar" aria-label="Family record steps">
            <div class="btn-group w-100" role="group" aria-label="Family record steps">
                <button class="btn active" id="<?= esc($fieldPrefix, 'attr') ?>HeadTab" data-family-step-target="head" data-family-step-pane="#<?= esc($fieldPrefix, 'attr') ?>HeadPane" type="button" role="tab" aria-controls="<?= esc($fieldPrefix, 'attr') ?>HeadPane" aria-selected="true">
                    <span class="family-step-number">1</span>
                    <span>Head of Family</span>
                </button>
                <button class="btn" id="<?= esc($fieldPrefix, 'attr') ?>MemberTab" data-family-step-target="members" data-family-step-pane="#<?= esc($fieldPrefix, 'attr') ?>MemberPane" type="button" role="tab" aria-controls="<?= esc($fieldPrefix, 'attr') ?>MemberPane" aria-selected="false">
                    <span class="family-step-number">2</span>
                    <span>Members</span>
                </button>
            </div>
        </div>

        <div class="tab-content family-entry-content">
            <div class="tab-pane fade show active" id="<?= esc($fieldPrefix, 'attr') ?>HeadPane" role="tabpanel" aria-labelledby="<?= esc($fieldPrefix, 'attr') ?>HeadTab" tabindex="0">
                <section class="family-entry-section family-entry-personal">
                    <h3 class="family-section-title">Personal Information</h3>

                    <div class="row g-3">
                        <div class="col-12 col-md-6 col-xl-3">
                            <label class="form-label" for="<?= esc($fieldPrefix, 'attr') ?>HeadLastname">Last Name</label>
                            <input id="<?= esc($fieldPrefix, 'attr') ?>HeadLastname" name="head_lastname" type="text" value="<?= esc($oldValue('head_lastname'), 'attr') ?>" data-summary="name-last" required>
                        </div>
                        <div class="col-12 col-md-6 col-xl-3">
                            <label class="form-label" for="<?= esc($fieldPrefix, 'attr') ?>HeadFirstname">First Name</label>
                            <input id="<?= esc($fieldPrefix, 'attr') ?>HeadFirstname" name="head_firstname" type="text" value="<?= esc($oldValue('head_firstname'), 'attr') ?>" data-summary="name-first" required>
                        </div>
                        <div class="col-12 col-md-6 col-xl-3">
                            <label class="form-label" for="<?= esc($fieldPrefix, 'attr') ?>HeadMiddlename">Middle Name</label>
                            <input id="<?= esc($fieldPrefix, 'attr') ?>HeadMiddlename" name="head_middlename" type="text" value="<?= esc($oldValue('head_middlename'), 'attr') ?>" data-summary="name-middle">
                        </div>
                        <div class="col-12 col-md-6 col-xl-3">
                            <label class="form-label" for="<?= esc($fieldPrefix, 'attr') ?>HeadSuffix">Suffix</label>
                            <select id="<?= esc($fieldPrefix, 'attr') ?>HeadSuffix" name="head_suffix" data-summary="name-suffix">
                                <?= $selectOptions($suffixOptions, $oldValue('head_suffix'), 'Select') ?>
                            </select>
                        </div>

                        <div class="col-12 col-md-6 col-xl-3">
                            <label class="form-label" for="<?= esc($fieldPrefix, 'attr') ?>HeadBirthday">Date of birth</label>
                            <input id="<?= esc($fieldPrefix, 'attr') ?>HeadBirthday" name="head_birthday" type="date" value="<?= esc($oldValue('head_birthday'), 'attr') ?>" data-summary="birthday" required>
                        </div>
                        <div class="col-12 col-md-6 col-xl-3">
                            <label class="form-label" for="<?= esc($fieldPrefix, 'attr') ?>HeadSex">Sex</label>
                            <select id="<?= esc($fieldPrefix, 'attr') ?>HeadSex" name="head_sex" data-summary="sex" required>
                                <?= $selectOptions($sexOptions, $oldValue('head_sex'), 'Select') ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6 col-xl-3">
                            <label class="form-label" for="<?= esc($fieldPrefix, 'attr') ?>HeadCivilStatus">Civil status</label>
                            <select id="<?= esc($fieldPrefix, 'attr') ?>HeadCivilStatus" name="head_civilstatus" class="js-other-select" data-other-field="head_civilstatus" data-initial-value="<?= esc($oldValue('head_civilstatus'), 'attr') ?>" data-summary="civil" required>
                                <?= $selectOptions($civilOptions, $oldValue('head_civilstatus'), 'Select') ?>
                            </select>
                            <input class="form-control mt-2 js-other-input family-form-hidden" data-other-for="head_civilstatus" placeholder="Enter civil status" aria-label="Other civil status">
                        </div>
                        <div class="col-12 col-md-6 col-xl-3">
                            <label class="form-label" for="<?= esc($fieldPrefix, 'attr') ?>HeadContact">Contact number</label>
                            <input id="<?= esc($fieldPrefix, 'attr') ?>HeadContact" name="head_contactnumber" type="tel" value="<?= esc($oldValue('head_contactnumber'), 'attr') ?>" data-summary="contact" maxlength="30">
                        </div>

                        <div class="col-12 col-md-6 col-xl-3">
                            <label class="form-label" for="<?= esc($fieldPrefix, 'attr') ?>HeadReligion">Religion</label>
                            <select id="<?= esc($fieldPrefix, 'attr') ?>HeadReligion" name="head_religion" class="js-other-select" data-other-field="head_religion" data-initial-value="<?= esc($oldValue('head_religion'), 'attr') ?>" data-summary="religion">
                                <?= $selectOptions($religionOptions, $oldValue('head_religion'), 'Select') ?>
                            </select>
                            <input class="form-control mt-2 js-other-input family-form-hidden" data-other-for="head_religion" placeholder="Enter religion" aria-label="Other religion">
                        </div>
                        <div class="col-12 col-md-6 col-xl-3">
                            <label class="form-label" for="<?= esc($fieldPrefix, 'attr') ?>HeadEducation">Education</label>
                            <select id="<?= esc($fieldPrefix, 'attr') ?>HeadEducation" name="head_education" class="js-other-select" data-other-field="head_education" data-initial-value="<?= esc($oldValue('head_education'), 'attr') ?>" data-summary="education" required>
                                <?= $selectOptions($educationOptions, $oldValue('head_education'), 'Select') ?>
                            </select>
                            <input class="form-control mt-2 js-other-input family-form-hidden" data-other-for="head_education" placeholder="Enter education" aria-label="Other education">
                        </div>
                        <div class="col-12 col-md-6 col-xl-3">
                            <label class="form-label" for="<?= esc($fieldPrefix, 'attr') ?>HeadJob">Job</label>
                            <select id="<?= esc($fieldPrefix, 'attr') ?>HeadJob" name="head_job" class="js-other-select" data-other-field="head_job" data-initial-value="<?= esc($oldValue('head_job'), 'attr') ?>" data-summary="job" required>
                                <?= $selectOptions($jobOptions, $oldValue('head_job'), 'Select') ?>
                            </select>
                            <input class="form-control mt-2 js-other-input family-form-hidden" data-other-for="head_job" placeholder="Enter job" aria-label="Other job">
                        </div>
                        <div class="col-12 col-md-6 col-xl-3">
                            <label class="form-label" for="<?= esc($fieldPrefix, 'attr') ?>HeadSalary">Monthly income</label>
                            <select id="<?= esc($fieldPrefix, 'attr') ?>HeadSalary" name="head_salary" data-summary="income" required>
                                <?= $selectOptions($incomeOptions, $oldValue('head_salary'), 'Select') ?>
                            </select>
                        </div>

                        <div class="col-12 col-xl-9">
                            <label class="form-label" for="<?= esc($fieldPrefix, 'attr') ?>HeadAddress">Address</label>
                            <input id="<?= esc($fieldPrefix, 'attr') ?>HeadAddress" name="head_address" type="text" value="<?= esc($oldValue('head_address'), 'attr') ?>" data-summary="address" required>
                        </div>
                        <div class="col-12 col-xl-3">
                            <label class="form-label" for="<?= esc($fieldPrefix, 'attr') ?>HeadBarangay">Barangay</label>
                            <select id="<?= esc($fieldPrefix, 'attr') ?>HeadBarangay" name="head_barangay" data-summary="barangay" required>
                                <?= $selectOptions($barangayOptions, $oldValue('head_barangay'), 'Barangay') ?>
                            </select>
                        </div>
                    </div>
                </section>

                <section class="family-entry-section family-sector-service">
                    <h3 class="family-section-title">Sectors and Services</h3>
                    <div class="row g-4">
                        <div class="col-12 col-lg-5">
                            <h4 class="family-column-title">Sectors</h4>
                            <div class="family-option-box">
                                <?php if ($sectorCatalog === []): ?>
                                    <p class="text-muted mb-0">No sectors available.</p>
                                <?php endif; ?>
                                <?php foreach ($sectorCatalog as $sectorGroup): ?>
                                    <?php
                                    $sectorGroup = array_values((array) $sectorGroup);
                                    if ($sectorGroup === []) { continue; }
                                    $groupTitle = trim((string) ($sectorGroup[0]['category_label'] ?? ''));
                                    ?>
                                    <div class="family-option-group">
                                        <?php if ($groupTitle !== ''): ?><p class="family-option-group-title"><?= esc($groupTitle) ?></p><?php endif; ?>
                                        <?php foreach ($sectorGroup as $sector): ?>
                                            <?php
                                            $sector = (array) $sector;
                                            $sectorId = (string) ($sector['sectorID'] ?? $sector['id'] ?? '');
                                            $label = $sectorLabel($sector);
                                            $isArchived = ! empty($sector['is_archived']);
                                            ?>
                                            <?php if ($sectorId !== '' && $label !== ''): ?>
                                                <label class="form-check family-choice<?= $isArchived ? ' family-choice--archived' : '' ?>">
                                                    <input type="checkbox" name="sector_ids[]" value="<?= esc($sectorId, 'attr') ?>" data-label="<?= esc($label, 'attr') ?>"<?= $isArchived ? ' data-archived="1"' : '' ?> <?= in_array($sectorId, $selectedSectorIds, true) ? 'checked' : '' ?>>
                                                    <span class="form-check-label"><?= esc($label) ?><?php if ($isArchived): ?> <span class="family-choice-badge">Archived</span><?php endif; ?></span>
                                                </label>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="col-12 col-lg-7">
                            <h4 class="family-column-title">Services and Programs Available</h4>
                            <div class="family-option-box">
                                <?php if ($servicesByCategory === []): ?>
                                    <p class="text-muted mb-0">No services available.</p>
                                <?php endif; ?>
                                <?php foreach ($servicesByCategory as $category => $services): ?>
                                    <div class="family-option-group">
                                        <p class="family-option-group-title"><?= esc((string) $category) ?></p>
                                        <?php foreach ((array) $services as $service): ?>
                                            <?php
                                            $service = (array) $service;
                                            $serviceId = (string) ($service['serviceID'] ?? $service['id'] ?? '');
                                            $label = $serviceLabel($service);
                                            $isArchived = ! empty($service['is_archived']);
                                            ?>
                                            <?php if ($serviceId !== '' && $label !== ''): ?>
                                                <label class="form-check family-choice<?= $isArchived ? ' family-choice--archived' : '' ?>">
                                                    <input type="checkbox" name="service_ids[]" value="<?= esc($serviceId, 'attr') ?>" data-label="<?= esc($label, 'attr') ?>"<?= $isArchived ? ' data-archived="1"' : '' ?> <?= in_array($serviceId, $selectedServiceIds, true) ? 'checked' : '' ?>>
                                                    <span class="form-check-label"><?= esc($label) ?><?php if ($isArchived): ?> <span class="family-choice-badge">Archived</span><?php endif; ?></span>
                                                </label>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <div class="tab-pane fade" id="<?= esc($fieldPrefix, 'attr') ?>MemberPane" role="tabpanel" aria-labelledby="<?= esc($fieldPrefix, 'attr') ?>MemberTab" tabindex="0">
                <section class="family-entry-section family-head-summary">
                    <h3 class="family-summary-title">Current Record Head</h3>
                    <div class="row g-3">
                        <?php foreach ([
                            'Name' => 'name',
                            'Date of birth' => 'birthday',
                            'Sex' => 'sex',
                            'Civil status' => 'civil',
                            'Contact' => 'contact',
                            'Religion' => 'religion',
                            'Education' => 'education',
                            'Job' => 'job',
                            'Monthly income' => 'income',
                        ] as $label => $key): ?>
                            <div class="col-12 col-md-6 col-xl-4">
                                <span class="family-summary-label"><?= esc($label) ?>:</span>
                                <div class="family-summary-value" data-head-summary="<?= esc($key, 'attr') ?>">-</div>
                            </div>
                        <?php endforeach; ?>
                        <div class="col-12">
                            <span class="family-summary-label">Address:</span>
                            <div class="family-summary-value" data-head-summary="address">-</div>
                        </div>
                        <div class="col-12 col-lg-6">
                            <span class="family-summary-label">Sector(s):</span>
                            <div class="family-summary-list" data-head-summary="sectors">-</div>
                        </div>
                        <div class="col-12 col-lg-6">
                            <span class="family-summary-label">Services and programs availed:</span>
                            <div class="family-summary-list" data-head-summary="services">-</div>
                        </div>
                    </div>
                </section>

                <section class="family-entry-section family-members-section">
                    <h3 class="family-section-title">Family Members</h3>
                    <p class="text-muted small">Add the household members under this head of family. Leave empty if there are none.</p>

                    <div data-family-members>
                        <?php foreach (array_values($existingMembers) as $i => $member): ?>
                            <?= $renderMemberRow($i, (array) $member) ?>
                        <?php endforeach; ?>
                    </div>

                    <template data-family-member-template>
                        <?= $renderMemberRow('__INDEX__') ?>
                    </template>

                    <div class="btn-toolbar family-member-toolbar" role="toolbar" aria-label="Family member actions">
                        <div class="btn-group" role="group" aria-label="Member actions">
                            <button class="btn btn-success" type="button" data-family-add-member data-next-index="<?= esc((string) count($existingMembers), 'attr') ?>">Add Member</button>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <footer class="btn-toolbar family-entry-actions" role="toolbar" aria-label="Family form actions">
            <div class="btn-group" role="group" aria-label="Form actions">
                <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Close</button>
                <button class="btn btn-danger" type="reset" data-family-clear>Clear</button>
                <button class="btn btn-secondary" type="button" data-family-prev hidden>Previous</button>
                <button class="btn btn-success" type="button" data-family-next>Next</button>
                <button class="btn btn-primary" type="submit" data-family-save <?= $saveDisabled ? 'disabled aria-disabled="true"' : '' ?> hidden><?= esc($submitLabel) ?></button>
            </div>
        </footer>

        <?php /* Truncation sentinel — MUST stay the last named field in the form. A POST
                 clipped by PHP's max_input_vars drops trailing vars first, so if this does
                 not arrive the server knows member data was cut and refuses to save. */ ?>
        <input type="hidden" name="_form_end" value="1">
    </form>
</div>
