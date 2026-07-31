<?php
/**
 * Import review (Family Records > Import Excel, step 2). The staged rows of an upload
 * as one paginated row per person, so every problem in the file is reachable and a
 * 10,000-row import renders as fast as a small one.
 *
 * Rows arrive from records/import/review/:id/rows, a page at a time, and are painted by
 * import-review.js. Clicking a flagged row expands an editor for every field carrying a
 * problem; nothing is staged until Apply, and nothing reaches the member table until
 * Confirm import. Cancel discards staging.
 *
 * Renders inside the shared dashboard layout, so it loads no assets of its own.
 */
$jobId   = (int) ($jobId ?? 0);
$summary = $summary ?? ['file' => '', 'counts' => [], 'codes' => [], 'fileNotices' => []];
$fieldOptions = $fieldOptions ?? [];
$counts  = is_array($summary['counts'] ?? null) ? $summary['counts'] : [];

// JSON islands: HEX_TAG/HEX_AMP keep any "</script>" or "&" from a spreadsheet cell
// from breaking out of the <script> tag (defence against a crafted .xlsx).
$summaryJson = json_encode($summary, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
$fieldOptionsJson = json_encode($fieldOptions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
?>
<div id="importReview"
     data-rows-url="<?= esc(site_url('records/import/review/' . $jobId . '/rows'), 'attr') ?>"
     data-apply-url="<?= esc(site_url('records/import/review/' . $jobId . '/apply'), 'attr') ?>"
     data-commit-url="<?= esc(site_url('records/import/review/' . $jobId . '/commit'), 'attr') ?>"
     data-cancel-url="<?= esc(site_url('records/import/review/' . $jobId . '/cancel'), 'attr') ?>"
     data-redirect-url="<?= esc(site_url('records'), 'attr') ?>">

    <input type="hidden" id="reviewCsrf" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>">

    <ul class="nav nav-pills segmented-tabs mb-4" role="tablist">
        <li class="nav-item"><span class="nav-link disabled">1. Upload</span></li>
        <li class="nav-item"><span class="nav-link active">2. Review and Fix</span></li>
        <li class="nav-item"><span class="nav-link disabled">3. Done</span></li>
    </ul>

    <p class="text-muted">
        File: <strong id="reviewFileName"><?= esc($summary['file'] ?? '') ?></strong>.
        Nothing is saved until you press <strong>Confirm import</strong>. Open a flagged
        row to correct it.
    </p>

    <div id="importReviewNotices">
        <?php foreach (($summary['fileNotices'] ?? []) as $notice) : ?>
            <div class="alert alert-danger" role="alert"><?= esc($notice) ?></div>
        <?php endforeach; ?>
    </div>

    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
        <div class="flex-grow-1 import-review-search">
            <label for="importReviewSearch" class="form-label small text-muted mb-1">Search</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
                <input type="search" class="form-control" id="importReviewSearch"
                       placeholder="Search this import..." aria-label="Search this import">
            </div>
        </div>
        <div class="d-flex align-items-end gap-2">
            <div>
                <label for="importReviewCodeFilter" class="form-label small text-muted mb-1">Problem</label>
                <select class="form-select" id="importReviewCodeFilter">
                    <option value="">All problems</option>
                    <?php foreach (($summary['codes'] ?? []) as $code) : ?>
                        <option value="<?= esc($code['code'], 'attr') ?>"><?= esc($code['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="importReviewPerPage" class="form-label small text-muted mb-1">Show</label>
                <select class="form-select" id="importReviewPerPage">
                    <option value="25" selected>25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
        </div>
    </div>

    <ul class="nav nav-pills segmented-tabs mb-3" id="importReviewSeverity" role="tablist">
        <li class="nav-item"><button type="button" class="nav-link active" data-severity="all">All</button></li>
        <li class="nav-item"><button type="button" class="nav-link" data-severity="problems">Problems</button></li>
        <li class="nav-item"><button type="button" class="nav-link" data-severity="blocking">
            Must fix <span class="badge text-bg-danger" data-count="blocking"><?= (int) ($counts['blocking'] ?? 0) ?></span>
        </button></li>
        <li class="nav-item"><button type="button" class="nav-link" data-severity="warning">
            Warnings <span class="badge text-bg-warning" data-count="warnings"><?= (int) ($counts['warnings'] ?? 0) ?></span>
        </button></li>
    </ul>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="importReviewTable">
                <thead>
                    <tr>
                        <th scope="col"><span class="visually-hidden">Status</span></th>
                        <th scope="col">Family</th>
                        <th scope="col">Role</th>
                        <th scope="col">Last Name</th>
                        <th scope="col">First Name</th>
                        <th scope="col">Issues</th>
                        <th scope="col"><span class="visually-hidden">Open</span></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
        <span class="text-muted small" id="importReviewCount" role="status" aria-live="polite"></span>
        <nav aria-label="Review pages"><ul class="pagination mb-0" id="importReviewPager"></ul></nav>
    </div>

    <div class="d-flex flex-wrap justify-content-end align-items-center gap-2 mt-4">
        <span id="importReviewStatus" class="text-muted me-auto" role="status" aria-live="polite"></span>
        <button type="button" class="btn btn-outline-secondary" id="importReviewCancel">
            <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Cancel import
        </button>
        <button type="button" class="<?= btn('save') ?>" id="importReviewConfirm" disabled>
            <i class="bi bi-check2-circle me-1" aria-hidden="true"></i>Confirm import
        </button>
    </div>

    <script id="importReviewSummary" type="application/json"><?= $summaryJson ?></script>
    <script id="importReviewFieldOptions" type="application/json"><?= $fieldOptionsJson ?></script>
</div>
