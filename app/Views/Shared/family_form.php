<?php
$formOptions = $formOptions ?? [];
$sectorOptions = $formOptions['sectors'] ?? [];
$sexOptions = $formOptions['sexes'] ?? [];
$suffixOptions = $formOptions['suffixes'] ?? [];
$civilOptions = $formOptions['civil_statuses'] ?? [];
$relationshipOptions = $formOptions['relationships'] ?? [];
$educationOptions = $formOptions['education_levels'] ?? [];
$incomeOptions = $formOptions['income_ranges'] ?? [];
$servicesByCategory = $formOptions['services_by_category'] ?? [];
?>

<?php if (! ($canCreateFamily ?? false)): ?>
    <div class="alert alert-warning mb-0">
        Your account does not have permission to add family records yet.
    </div>
<?php elseif ($sectorOptions === []): ?>
    <div class="alert alert-warning mb-0">
        No sector records are available. Import accesscardV1.4.sql before encoding families.
    </div>
<?php else: ?>
    <form method="post" action="<?= site_url('families') ?>" id="familyForm" class="needs-validation" novalidate>
        <?= csrf_field() ?>

        <div class="form-section">
            <div class="section-title">
                <span>Head of Family</span>
            </div>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label" for="head_firstname">First name</label>
                    <input class="form-control" id="head_firstname" name="head_firstname" value="<?= esc(old('head_firstname')) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="head_middlename">Middle name</label>
                    <input class="form-control" id="head_middlename" name="head_middlename" value="<?= esc(old('head_middlename')) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="head_lastname">Last name</label>
                    <input class="form-control" id="head_lastname" name="head_lastname" value="<?= esc(old('head_lastname')) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="head_suffix">Suffix</label>
                    <select class="form-select" id="head_suffix" name="head_suffix">
                        <option value="">Select</option>
                        <?php foreach ($suffixOptions as $option): ?>
                            <option value="<?= esc($option) ?>" <?= old('head_suffix') === $option ? 'selected' : '' ?>><?= esc($option) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="head_birthday">Birthday</label>
                    <input type="date" class="form-control" id="head_birthday" name="head_birthday" value="<?= esc(old('head_birthday')) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="head_sex">Sex</label>
                    <select class="form-select" id="head_sex" name="head_sex" required>
                        <option value="">Select</option>
                        <?php foreach ($sexOptions as $option): ?>
                            <option value="<?= esc($option) ?>" <?= old('head_sex') === $option ? 'selected' : '' ?>><?= esc($option) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="head_civilstatus">Civil status</label>
                    <select class="form-select" id="head_civilstatus" name="head_civilstatus">
                        <option value="">Select</option>
                        <?php foreach ($civilOptions as $option): ?>
                            <option value="<?= esc($option) ?>" <?= old('head_civilstatus') === $option ? 'selected' : '' ?>><?= esc($option) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="sectorID">Sector</label>
                    <select class="form-select" id="sectorID" name="sectorID" required>
                        <option value="">Select</option>
                        <?php foreach ($sectorOptions as $sector): ?>
                            <option value="<?= esc($sector['sectorID']) ?>" <?= old('sectorID') == $sector['sectorID'] ? 'selected' : '' ?>>
                                <?= esc($sector['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="head_contactnumber">Contact number</label>
                    <input class="form-control" id="head_contactnumber" name="head_contactnumber" value="<?= esc(old('head_contactnumber')) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="head_education">Education</label>
                    <select class="form-select" id="head_education" name="head_education">
                        <option value="">Select</option>
                        <?php foreach ($educationOptions as $option): ?>
                            <option value="<?= esc($option) ?>" <?= old('head_education') === $option ? 'selected' : '' ?>><?= esc($option) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="head_job">Job</label>
                    <input class="form-control" id="head_job" name="head_job" value="<?= esc(old('head_job')) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="head_salary">Monthly income</label>
                    <select class="form-select" id="head_salary" name="head_salary">
                        <?php foreach ($incomeOptions as $value => $label): ?>
                            <option value="<?= esc($value) ?>" <?= old('head_salary') === (string) $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

        </div>

        <div class="form-section">
            <div class="section-title">
                <span>Family Members</span>
                <button type="button" class="btn btn-outline-primary btn-sm" id="addMemberBtn">Add member</button>
            </div>
            <div id="memberRows" class="member-stack"></div>
        </div>

        <div class="form-section">
            <div class="section-title">
                <span>Services and Programs</span>
            </div>
            <?php if ($servicesByCategory === []): ?>
                <div class="alert alert-warning mb-0">No service records are available in the database.</div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($servicesByCategory as $category => $services): ?>
                        <div class="col-lg-4">
                            <div class="assistance-box">
                                <h6 class="mb-2"><?= esc($category) ?></h6>
                                <div class="service-check-list">
                                    <?php foreach ($services as $service): ?>
                                        <label class="form-check">
                                            <input class="form-check-input" type="checkbox" name="service_ids[]" value="<?= esc($service['serviceID']) ?>">
                                            <span class="form-check-label"><?= esc($service['name']) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <button type="reset" class="btn btn-outline-secondary">Clear</button>
            <button type="submit" class="btn btn-primary">Save Family Data</button>
        </div>
    </form>

    <template id="memberTemplate">
        <div class="member-row">
            <div class="member-row-header">
                <strong>Family Member</strong>
                <button type="button" class="btn btn-sm btn-outline-danger remove-member">Remove</button>
            </div>
            <div class="row g-2">
                <div class="col-md-3"><input class="form-control" data-name="firstname" placeholder="First name"></div>
                <div class="col-md-3"><input class="form-control" data-name="middlename" placeholder="Middle name"></div>
                <div class="col-md-3"><input class="form-control" data-name="lastname" placeholder="Last name"></div>
                <div class="col-md-3">
                    <select class="form-select" data-name="suffix">
                        <option value="">Suffix</option>
                        <?php foreach ($suffixOptions as $option): ?>
                            <option value="<?= esc($option) ?>"><?= esc($option) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3"><input type="date" class="form-control" data-name="birthday"></div>
                <div class="col-md-3">
                    <select class="form-select" data-name="sex">
                        <option value="">Sex</option>
                        <?php foreach ($sexOptions as $option): ?>
                            <option value="<?= esc($option) ?>"><?= esc($option) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" data-name="civilstatus">
                        <option value="">Civil status</option>
                        <?php foreach ($civilOptions as $option): ?>
                            <option value="<?= esc($option) ?>"><?= esc($option) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" data-name="relationship">
                        <option value="">Relationship</option>
                        <?php foreach ($relationshipOptions as $option): ?>
                            <option value="<?= esc($option) ?>"><?= esc($option) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" data-name="sectorID">
                        <?php foreach ($sectorOptions as $sector): ?>
                            <option value="<?= esc($sector['sectorID']) ?>"><?= esc($sector['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" data-name="education">
                        <option value="">Education</option>
                        <?php foreach ($educationOptions as $option): ?>
                            <option value="<?= esc($option) ?>"><?= esc($option) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3"><input class="form-control" data-name="job" placeholder="Job"></div>
                <div class="col-md-3">
                    <select class="form-select" data-name="salary">
                        <?php foreach ($incomeOptions as $value => $label): ?>
                            <option value="<?= esc($value) ?>"><?= esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </template>
<?php endif; ?>
