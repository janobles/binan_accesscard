<?php
// Member summary placeholders are filled by the family form script.
?>
<div class="form-section family-step-panel" data-step="3">
    <div class="section-title">
        <span>Family Members</span>
        <button type="button" class="btn btn-sm btn-primary" id="addMemberBtn">Add Member</button>
    </div>
    <div class="member-row mb-3" id="headOfFamilySummary">
        <div class="member-row-header">
            <strong>Current Head of Family</strong>
        </div>
        <div class="row g-2">
            <div class="col-md-4"><small><strong>Name:</strong> <span id="headSummaryName">-</span></small></div>
            <div class="col-md-4"><small><strong>Birthday:</strong> <span id="headSummaryBirthday">-</span></small></div>
            <div class="col-md-4"><small><strong>Sex:</strong> <span id="headSummarySex">-</span></small></div>
            <div class="col-md-4"><small><strong>Civil status:</strong> <span id="headSummaryCivil">-</span></small></div>
            <div class="col-md-4"><small><strong>Contact:</strong> <span id="headSummaryContact">-</span></small></div>
            <div class="col-md-4"><small><strong>Education:</strong> <span id="headSummaryEducation">-</span></small></div>
            <div class="col-md-4"><small><strong>Job:</strong> <span id="headSummaryJob">-</span></small></div>
            <div class="col-md-4"><small><strong>Monthly income:</strong> <span id="headSummaryIncome">-</span></small></div>
            <div class="col-md-6"><small><strong>Sector(s):</strong></small><div id="headSummarySectors" class="head-summary-list">-</div></div>
            <div class="col-md-6"><small><strong>Services availed:</strong></small><div id="headSummaryServices" class="head-summary-list">-</div></div>
        </div>
    </div>
    <div id="memberRows" class="member-stack"></div>
    <p class="text-muted mb-0" id="memberRowsEmpty">No family members added yet. Click Add Member.</p>
</div>
