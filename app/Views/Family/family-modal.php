<?php
helper('family_modal');
extract(family_modal_prepare(get_defined_vars()), EXTR_OVERWRITE);
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
                            <select id="<?= esc($fieldPrefix, 'attr') ?>HeadCivilStatus" name="head_civilstatus" data-summary="civil" required>
                                <?= $selectOptions($civilOptions, $oldValue('head_civilstatus'), 'Select') ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6 col-xl-3">
                            <label class="form-label" for="<?= esc($fieldPrefix, 'attr') ?>HeadContact">Contact number</label>
                            <input id="<?= esc($fieldPrefix, 'attr') ?>HeadContact" name="head_contactnumber" type="tel" value="<?= esc($oldValue('head_contactnumber'), 'attr') ?>" data-summary="contact" maxlength="30">
                        </div>

                        <div class="col-12 col-md-6 col-xl-3">
                            <label class="form-label" for="<?= esc($fieldPrefix, 'attr') ?>HeadReligion">Religion</label>
                            <select id="<?= esc($fieldPrefix, 'attr') ?>HeadReligion" name="head_religion" data-summary="religion">
                                <?= $selectOptions($religionOptions, $oldValue('head_religion'), 'Select') ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6 col-xl-3">
                            <label class="form-label" for="<?= esc($fieldPrefix, 'attr') ?>HeadEducation">Education</label>
                            <select id="<?= esc($fieldPrefix, 'attr') ?>HeadEducation" name="head_education" data-summary="education" required>
                                <?= $selectOptions($educationOptions, $oldValue('head_education'), 'Select') ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6 col-xl-3">
                            <label class="form-label" for="<?= esc($fieldPrefix, 'attr') ?>HeadJob">Job</label>
                            <select id="<?= esc($fieldPrefix, 'attr') ?>HeadJob" name="head_job" data-summary="job" required>
                                <?= $selectOptions($jobOptions, $oldValue('head_job'), 'Select') ?>
                            </select>
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
                                <?php if ($sectorOptions === []): ?>
                                    <p class="text-muted mb-0">No sectors available.</p>
                                <?php endif; ?>
                                <?php foreach ($sectorOptions as $sector): ?>
                                    <?php
                                    $sector = (array) $sector;
                                    $sectorId = (string) ($sector['sectorID'] ?? $sector['id'] ?? '');
                                    $label = $sectorLabel($sector);
                                    ?>
                                    <?php if ($sectorId !== '' && $label !== ''): ?>
                                        <label class="form-check family-choice">
                                            <input type="checkbox" name="sector_ids[]" value="<?= esc($sectorId, 'attr') ?>" data-label="<?= esc($label, 'attr') ?>" <?= in_array($sectorId, $selectedSectorIds, true) ? 'checked' : '' ?>>
                                            <span class="form-check-label"><?= esc($label) ?></span>
                                        </label>
                                    <?php endif; ?>
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
                                            ?>
                                            <?php if ($serviceId !== '' && $label !== ''): ?>
                                                <label class="form-check family-choice">
                                                    <input type="checkbox" name="service_ids[]" value="<?= esc($serviceId, 'attr') ?>" data-label="<?= esc($label, 'attr') ?>" <?= in_array($serviceId, $selectedServiceIds, true) ? 'checked' : '' ?>>
                                                    <span class="form-check-label"><?= esc($label) ?></span>
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

                <div class="btn-toolbar family-member-toolbar" role="toolbar" aria-label="Family member actions">
                    <div class="btn-group" role="group" aria-label="Member actions">
                        <button class="btn btn-success" type="button" data-family-add-member>Add Member</button>
                    </div>
                </div>
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
    </form>
</div>
