<div data-entry-panel="head">
    <div class="section-title">
        <span>Record Head</span>
    </div>
    <?php
    $headCivilStatus = (string) ($familyRecord['civilstatus'] ?? '');
    $headCivilIsCustom = $headCivilStatus !== '' && ! in_array($headCivilStatus, $civilOptions, true);
    $headEducation = (string) ($familyRecord['education'] ?? '');
    $headEducationIsCustom = $headEducation !== '' && ! in_array($headEducation, $educationOptions, true);
    $headJob = (string) ($familyRecord['job'] ?? '');
    $headJobIsCustom = $headJob !== '' && ! in_array($headJob, $jobOptions, true);
    $headAddress = trim((string) ($familyRecord['address'] ?? ''));
    $headBarangay = trim((string) ($familyRecord['barangay'] ?? ''));
    ?>
    <div class="row g-3">
        <div class="col-md-3">
            <label class="form-label" for="head_lastname">Last Name</label>
            <input class="form-control" id="head_lastname" name="head_lastname" value="<?= esc((string) ($familyRecord['lastname'] ?? '')) ?>" required>
            <div class="invalid-feedback">Last name is required.</div>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="head_firstname">First Name</label>
            <input class="form-control" id="head_firstname" name="head_firstname" value="<?= esc((string) ($familyRecord['firstname'] ?? '')) ?>" required>
            <div class="invalid-feedback">First name is required.</div>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="head_middlename">Middle Name</label>
            <input class="form-control" id="head_middlename" name="head_middlename" value="<?= esc((string) ($familyRecord['middlename'] ?? '')) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="head_suffix">Suffix</label>
            <select class="form-select" id="head_suffix" name="head_suffix">
                <option value="">Select</option>
                <?php foreach ($suffixOptions as $suffix): ?>
                    <option value="<?= esc($suffix) ?>" <?= (string) ($familyRecord['suffix'] ?? '') === (string) $suffix ? 'selected' : '' ?>><?= esc($suffix) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="head_birthday">Date of birth</label>
            <input type="date" class="form-control" id="head_birthday" name="head_birthday" value="<?= esc((string) ($familyRecord['birthday'] ?? '')) ?>" required>
            <div class="invalid-feedback">Date of birth is required.</div>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="head_sex">Sex</label>
            <select class="form-select" id="head_sex" name="head_sex" required>
                <option value="">Select</option>
                <?php foreach ($sexOptions as $sex): ?>
                    <option value="<?= esc($sex) ?>" <?= (string) ($familyRecord['sex'] ?? '') === (string) $sex ? 'selected' : '' ?>><?= esc($sex) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="invalid-feedback">Sex is required.</div>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="head_civilstatus">Civil status</label>
            <select class="form-select js-other-select" id="head_civilstatus" name="head_civilstatus" data-other-input="#head_civilstatus_other">
                <option value="">Select</option>
                <?php foreach ($civilOptions as $civilStatus): ?>
                    <option value="<?= esc($civilStatus) ?>" <?= ($headCivilIsCustom && in_array((string) $civilStatus, ['Other', 'Others'], true)) || $headCivilStatus === (string) $civilStatus ? 'selected' : '' ?>><?= esc($civilStatus) ?></option>
                <?php endforeach; ?>
            </select>
            <input class="form-control mt-2 js-other-input <?= $headCivilIsCustom ? '' : 'family-form-hidden' ?>" id="head_civilstatus_other" value="<?= esc($headCivilIsCustom ? $headCivilStatus : '') ?>" placeholder="Enter civil status">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="head_contactnumber">Contact number</label>
            <input class="form-control" id="head_contactnumber" name="head_contactnumber" inputmode="numeric" value="<?= esc((string) ($familyRecord['contactnumber'] ?? '')) ?>">
            <div class="invalid-feedback">Contact number must contain digits only.</div>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="head_religion">Religion</label>
            <input class="form-control" id="head_religion" name="head_religion" value="<?= esc((string) ($familyRecord['religion'] ?? '')) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="head_education">Education</label>
            <select class="form-select js-other-select" id="head_education" name="head_education" data-other-input="#head_education_other">
                <option value="">Select</option>
                <?php foreach ($educationOptions as $education): ?>
                    <option value="<?= esc($education) ?>" <?= ($headEducationIsCustom && in_array((string) $education, ['Other', 'Others'], true)) || $headEducation === (string) $education ? 'selected' : '' ?>><?= esc($education) ?></option>
                <?php endforeach; ?>
            </select>
            <input class="form-control mt-2 js-other-input <?= $headEducationIsCustom ? '' : 'family-form-hidden' ?>" id="head_education_other" value="<?= esc($headEducationIsCustom ? $headEducation : '') ?>" placeholder="Enter education">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="head_job">Job</label>
            <select class="form-select js-other-select" id="head_job" name="head_job" data-other-input="#head_job_other">
                <option value="">Select</option>
                <?php foreach ($jobOptions as $jobOption): ?>
                    <option value="<?= esc((string) $jobOption) ?>" <?= ($headJobIsCustom && in_array((string) $jobOption, ['Other', 'Others'], true)) || $headJob === (string) $jobOption ? 'selected' : '' ?>><?= esc((string) $jobOption) ?></option>
                <?php endforeach; ?>
            </select>
            <input class="form-control mt-2 js-other-input <?= $headJobIsCustom ? '' : 'family-form-hidden' ?>" id="head_job_other" value="<?= esc($headJobIsCustom ? $headJob : '') ?>" placeholder="Enter job">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="head_salary">Monthly income</label>
            <select class="form-select" id="head_salary" name="head_salary">
                <option value="">Select</option>
                <?php foreach ($incomeOptions as $incomeOption): ?>
                    <?php $incomeValue = (string) ($incomeOption['value'] ?? ''); ?>
                    <?php $incomeLabel = (string) ($incomeOption['label'] ?? $incomeValue); ?>
                    <option value="<?= esc($incomeValue) ?>" <?= (string) ($familyRecord['Salary'] ?? '') === $incomeValue ? 'selected' : '' ?>><?= esc($incomeLabel) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-9">
            <label class="form-label" for="head_address">Address</label>
            <input class="form-control" id="head_address" name="head_address" value="<?= esc($headAddress) ?>" placeholder="House no., street, subdivision">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="head_barangay">Barangay</label>
            <input class="form-control" id="head_barangay" name="head_barangay" value="<?= esc($headBarangay) ?>" placeholder="Barangay">
        </div>
    </div>
</div>
