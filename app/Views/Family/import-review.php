<?php
/**
 * Import review (Family Records > Import Excel, step 2). Renders the staged rows of
 * an upload as one row per person so an error flag sits on the value that is wrong,
 * with the family and role columns carrying the household grouping.
 *
 * The rows are painted by import-review.js from the JSON island below
 * (ImportReviewPresenter::build() output, its `people` list). Flagged cells edit in
 * place against import/review/:id/family/cell, which restages and re-validates.
 *
 * Nothing reaches the member table until Confirm import; Cancel discards staging.
 * Renders inside the shared dashboard layout, so it loads no assets of its own.
 */
$jobId  = (int) ($jobId ?? 0);
$review = $review ?? ['file' => '', 'counts' => [], 'people' => []];
$fieldOptions = $fieldOptions ?? [];

// JSON island: HEX_TAG/HEX_AMP keep any "</script>" or "&" inside a spreadsheet cell
// from breaking out of the <script> tag (defence against a crafted .xlsx).
$reviewJson = json_encode($review, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
// Dropdown option lists for inline <select> cells (mirrors the Excel template dropdowns).
$fieldOptionsJson = json_encode($fieldOptions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
?>
<div id="importReview"
     data-commit-url="<?= esc(site_url('records/import/review/' . $jobId . '/commit'), 'attr') ?>"
     data-cancel-url="<?= esc(site_url('records/import/review/' . $jobId . '/cancel'), 'attr') ?>"
     data-cell-url="<?= esc(site_url('records/import/review/' . $jobId . '/family/cell'), 'attr') ?>"
     data-redirect-url="<?= esc(site_url('records'), 'attr') ?>">

    <input type="hidden" id="reviewCsrf" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>">

    <ul class="nav nav-pills segmented-tabs mb-4" role="tablist">
        <li class="nav-item"><span class="nav-link disabled">1. Upload</span></li>
        <li class="nav-item"><span class="nav-link active">2. Review and Fix</span></li>
        <li class="nav-item"><span class="nav-link disabled">3. Done</span></li>
    </ul>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <p class="text-muted mb-0">
            File: <strong id="reviewFileName"></strong>. Nothing is saved until you press
            <strong>Confirm import</strong>. Click a flagged cell to correct it.
        </p>
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="problemsOnly" data-problems-only checked>
            <label class="form-check-label" for="problemsOnly">Only show rows with problems</label>
        </div>
    </div>

    <div id="importReviewNotices"></div>

    <div class="table-responsive">
        <table class="table table-hover align-middle" id="importReviewTable">
            <thead>
                <tr>
                    <th scope="col"><span class="visually-hidden">Status</span></th>
                    <th scope="col">Family</th>
                    <th scope="col">Role</th>
                    <th scope="col">Last Name</th>
                    <th scope="col">First Name</th>
                    <th scope="col">Date of Birth</th>
                    <th scope="col">Sex</th>
                </tr>
            </thead>
            <tbody></tbody>
            <tfoot>
                <tr>
                    <td colspan="7" class="text-muted small" id="importReviewCount"></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="d-flex flex-wrap justify-content-end align-items-center gap-2 mt-4">
        <span id="importReviewStatus" class="text-muted me-auto" role="status" aria-live="polite"></span>
        <button type="button" class="btn btn-outline-secondary" id="importReviewCancel">
            <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Cancel import
        </button>
        <button type="button" class="btn btn-primary" id="importReviewConfirm" disabled>
            <i class="bi bi-check2-circle me-1" aria-hidden="true"></i>Confirm import
        </button>
    </div>

    <script id="importReviewData" type="application/json"><?= $reviewJson ?></script>
    <script id="importReviewFieldOptions" type="application/json"><?= $fieldOptionsJson ?></script>
</div>
