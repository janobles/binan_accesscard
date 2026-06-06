<?php
// Member summary placeholders are filled by the family form script (renderHeadSummary).
?>
<div class="form-section family-step-panel" data-step="3">
    <article class="family-summary-card" id="headOfFamilySummary">
        <h2 class="family-summary-title">Current Record Head</h2>

        <div class="family-summary-grid">
            <p class="family-summary-item"><strong>Name:</strong> <span id="headSummaryName">-</span></p>
            <p class="family-summary-item"><strong>Date of birth:</strong> <span id="headSummaryBirthday">-</span></p>
            <p class="family-summary-item"><strong>Sex:</strong> <span id="headSummarySex">-</span></p>
            <p class="family-summary-item"><strong>Civil status:</strong> <span id="headSummaryCivil">-</span></p>
            <p class="family-summary-item"><strong>Contact:</strong> <span id="headSummaryContact">-</span></p>
            <p class="family-summary-item"><strong>Religion:</strong> <span id="headSummaryReligion">-</span></p>
            <p class="family-summary-item"><strong>Education:</strong> <span id="headSummaryEducation">-</span></p>
            <p class="family-summary-item"><strong>Job:</strong> <span id="headSummaryJob">-</span></p>
            <p class="family-summary-item"><strong>Monthly income:</strong> <span id="headSummaryIncome">-</span></p>
            <p class="family-summary-item family-summary-wide"><strong>Address:</strong> <span id="headSummaryAddress">-</span></p>
        </div>

        <div class="family-summary-lists">
            <div class="family-summary-list">
                <strong>Sector(s):</strong>
                <div id="headSummarySectors" class="head-summary-list">-</div>
            </div>
            <div class="family-summary-list">
                <strong>Services and programs availed:</strong>
                <div id="headSummaryServices" class="head-summary-list">-</div>
            </div>
        </div>

        <div class="family-member-actions">
            <button type="button" class="btn btn-success" id="addMemberStickyBtn"><i class="bi bi-person-plus" aria-hidden="true"></i>Add Member</button>
        </div>
    </article>

    <div id="memberRows" class="family-members-list"></div>
    <p class="text-muted mb-0 family-members-empty" id="memberRowsEmpty">No members added yet. Click Add Member.</p>
</div>
