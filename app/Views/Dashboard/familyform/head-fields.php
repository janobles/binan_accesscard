<div data-entry-panel="head">
    <div class="section-title">
        <span>Head of Family</span>
    </div>
    <div class="row g-3">
        <div class="col-md-3">
            <label class="form-label" for="head_firstname"><?= esc($fieldLabels['firstname'] ?? 'First name') ?></label>
            <input class="form-control" id="head_firstname" name="head_firstname" value="<?= esc((string) ($familyRecord['firstname'] ?? '')) ?>" required>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="head_middlename"><?= esc($fieldLabels['middlename'] ?? 'Middle name') ?></label>
            <input class="form-control" id="head_middlename" name="head_middlename" value="<?= esc((string) ($familyRecord['middlename'] ?? '')) ?>" required>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="head_lastname"><?= esc($fieldLabels['lastname'] ?? 'Last name') ?></label>
            <input class="form-control" id="head_lastname" name="head_lastname" value="<?= esc((string) ($familyRecord['lastname'] ?? '')) ?>" required>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="head_suffix"><?= esc($fieldLabels['suffix'] ?? 'Suffix') ?></label>
            <select class="form-select" id="head_suffix" name="head_suffix">
                <option value="">Select</option>
                <?php foreach ($suffixOptions as $suffix): ?>
                    <option value="<?= esc($suffix) ?>" <?= (string) ($familyRecord['suffix'] ?? '') === (string) $suffix ? 'selected' : '' ?>><?= esc($suffix) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="head_birthday"><?= esc($fieldLabels['birthday'] ?? 'Birthday') ?></label>
            <input type="date" class="form-control" id="head_birthday" name="head_birthday" value="<?= esc((string) ($familyRecord['birthday'] ?? '')) ?>" required>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="head_sex"><?= esc($fieldLabels['sex'] ?? 'Sex') ?></label>
            <select class="form-select" id="head_sex" name="head_sex" required>
                <option value="">Select</option>
                <?php foreach ($sexOptions as $sex): ?>
                    <option value="<?= esc($sex) ?>" <?= (string) ($familyRecord['sex'] ?? '') === (string) $sex ? 'selected' : '' ?>><?= esc($sex) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="head_civilstatus"><?= esc($fieldLabels['civilstatus'] ?? 'Civil status') ?></label>
            <select class="form-select" id="head_civilstatus" name="head_civilstatus">
                <option value="">Select</option>
                <?php foreach ($civilOptions as $civilStatus): ?>
                    <option value="<?= esc($civilStatus) ?>" <?= (string) ($familyRecord['civilstatus'] ?? '') === (string) $civilStatus ? 'selected' : '' ?>><?= esc($civilStatus) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="head_contactnumber"><?= esc($fieldLabels['contactnumber'] ?? 'Contact number') ?></label>
            <input class="form-control" id="head_contactnumber" name="head_contactnumber" value="<?= esc((string) ($familyRecord['contactnumber'] ?? '')) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="head_education"><?= esc($fieldLabels['education'] ?? 'Education') ?></label>
            <select class="form-select" id="head_education" name="head_education">
                <option value="">Select</option>
                <?php foreach ($educationOptions as $education): ?>
                    <option value="<?= esc($education) ?>" <?= (string) ($familyRecord['education'] ?? '') === (string) $education ? 'selected' : '' ?>><?= esc($education) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label" for="head_job"><?= esc($fieldLabels['job'] ?? 'Job') ?></label>
            <input class="form-control" id="head_job" name="head_job" value="<?= esc((string) ($familyRecord['job'] ?? '')) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="head_salary"><?= esc($fieldLabels['salary'] ?? 'Monthly income') ?></label>
            <select class="form-select" id="head_salary" name="head_salary">
                <?php foreach ($incomeOptions as $incomeOption): ?>
                    <?php $incomeValue = (string) ($incomeOption['value'] ?? ''); ?>
                    <?php $incomeLabel = (string) ($incomeOption['label'] ?? $incomeValue); ?>
                    <option value="<?= esc($incomeValue) ?>" <?= (string) ($familyRecord['Salary'] ?? '') === $incomeValue ? 'selected' : '' ?>><?= esc($incomeLabel) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</div>
