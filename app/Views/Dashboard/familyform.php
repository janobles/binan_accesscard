<form method="post" action="/families" id="familyForm" class="needs-validation js-family-form" novalidate>
    <div id="familyFormAlert" class="mb-3" aria-live="polite"></div>

    <div class="form-section">
        <div class="section-title">
            <span>Head of Family</span>
        </div>
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label" for="head_firstname">First name</label>
                <input class="form-control" id="head_firstname" name="head_firstname" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="head_middlename">Middle name</label>
                <input class="form-control" id="head_middlename" name="head_middlename" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="head_lastname">Last name</label>
                <input class="form-control" id="head_lastname" name="head_lastname" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="head_suffix">Suffix</label>
                <select class="form-select" id="head_suffix" name="head_suffix">
                    <option value="">Select</option>
                    <option value="I">I</option>
                    <option value="II">II</option>
                    <option value="III">III</option>
                    <option value="IV">IV</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="head_birthday">Birthday</label>
                <input type="date" class="form-control" id="head_birthday" name="head_birthday" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="head_sex">Sex</label>
                <select class="form-select" id="head_sex" name="head_sex" required>
                    <option value="">Select</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="head_civilstatus">Civil status</label>
                <select class="form-select" id="head_civilstatus" name="head_civilstatus">
                    <option value="">Select</option>
                    <option value="Single">Single</option>
                    <option value="Married">Married</option>
                    <option value="Widowed">Widowed</option>
                    <option value="Others">Others</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="sectorID">Sector</label>
                <select class="form-select" id="sectorID" name="sectorID" required>
                    <option value="">Select</option>
                    <option value="1">Sector 1</option>
                    <option value="2">Sector 2</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="head_contactnumber">Contact number</label>
                <input class="form-control" id="head_contactnumber" name="head_contactnumber">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="head_education">Education</label>
                <select class="form-select" id="head_education" name="head_education">
                    <option value="">Select</option>
                    <option value="Elementary Graduate">Elementary Graduate</option>
                    <option value="High School Graduate">High School Graduate</option>
                    <option value="College Graduate">College Graduate</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="head_job">Job</label>
                <input class="form-control" id="head_job" name="head_job">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="head_salary">Monthly income</label>
                <select class="form-select" id="head_salary" name="head_salary">
                    <option value="">Select</option>
                    <option value="0">No regular income</option>
                    <option value="13000">PHP 8,000 - 13,000</option>
                    <option value="25000">PHP 18,001 - 25,000</option>
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
        <div class="row g-3">
            <div class="col-lg-4">
                <div class="assistance-box">
                    <h6 class="mb-2">Health</h6>
                    <div class="service-check-list">
                        <label class="form-check">
                            <input class="form-check-input" type="checkbox" name="service_ids[]" value="1">
                            <span class="form-check-label">Medical Assistance</span>
                        </label>
                        <label class="form-check">
                            <input class="form-check-input" type="checkbox" name="service_ids[]" value="2">
                            <span class="form-check-label">Medicine Support</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="assistance-box">
                    <h6 class="mb-2">Education</h6>
                    <div class="service-check-list">
                        <label class="form-check">
                            <input class="form-check-input" type="checkbox" name="service_ids[]" value="3">
                            <span class="form-check-label">School Supplies</span>
                        </label>
                        <label class="form-check">
                            <input class="form-check-input" type="checkbox" name="service_ids[]" value="4">
                            <span class="form-check-label">Scholarship Referral</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
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
                    <option value="I">I</option>
                    <option value="II">II</option>
                    <option value="III">III</option>
                    <option value="IV">IV</option>
                </select>
            </div>
            <div class="col-md-3"><input type="date" class="form-control" data-name="birthday"></div>
            <div class="col-md-3">
                <select class="form-select" data-name="sex">
                    <option value="">Sex</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" data-name="civilstatus">
                    <option value="">Civil status</option>
                    <option value="Single">Single</option>
                    <option value="Married">Married</option>
                    <option value="Widowed">Widowed</option>
                    <option value="Others">Others</option>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" data-name="relationship">
                    <option value="">Relationship</option>
                    <option value="Spouse">Spouse</option>
                    <option value="Son">Son</option>
                    <option value="Daughter">Daughter</option>
                    <option value="Parent">Parent</option>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" data-name="sectorID">
                    <option value="1">Sector 1</option>
                    <option value="2">Sector 2</option>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" data-name="education">
                    <option value="">Education</option>
                    <option value="Elementary Graduate">Elementary Graduate</option>
                    <option value="High School Graduate">High School Graduate</option>
                    <option value="College Graduate">College Graduate</option>
                </select>
            </div>
            <div class="col-md-3"><input class="form-control" data-name="job" placeholder="Job"></div>
            <div class="col-md-3">
                <select class="form-select" data-name="salary">
                    <option value="">Select</option>
                    <option value="0">No regular income</option>
                    <option value="13000">PHP 8,000 - 13,000</option>
                    <option value="25000">PHP 18,001 - 25,000</option>
                </select>
            </div>
        </div>
    </div>
</template>