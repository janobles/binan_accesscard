<?php
/**
 * Import Review — full-page screen where an operator resolves import problems inline
 * before any data is written. The staged rows + grouped errors are rendered by
 * import-review.js from the JSON island below; each edit POSTs to the review/row
 * endpoint, re-validates server-side, and re-renders. Confirm queues the write job.
 *
 * @var int    $jobId
 * @var string $routeBase  admin/manage-family or employee/manage-family
 * @var array  $review     ImportReviewPresenter::build() output
 * @var string $username
 */
$jobId     = (int) ($jobId ?? 0);
$routeBase = (string) ($routeBase ?? 'admin/manage-family');
$review    = $review ?? ['file' => '', 'counts' => ['families' => 0, 'members' => 0, 'blocking' => 0, 'warnings' => 0], 'groups' => []];
$backUrl   = site_url($routeBase);

// JSON island: HEX_TAG/HEX_AMP keep any "</script>" or "&" inside a spreadsheet cell
// from breaking out of the <script> tag (defence against a crafted .xlsx).
$reviewJson = json_encode($review, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Import - Binan Access Card MIS</title>
    <link rel="icon" type="image/png" href="<?= asset_url('assets/image/binan.png') ?>">
    <?php foreach (array_merge(asset_styles('head'), asset_styles('admin')) as $stylePath): ?>
    <link rel="stylesheet" href="<?= esc(asset_url($stylePath), 'attr') ?>">
    <?php endforeach; ?>
    <link rel="stylesheet" href="<?= esc(asset_url('css/import-review.css'), 'attr') ?>">
</head>
<body class="bg-light">
<nav class="navbar navbar-dark bg-dark px-4">
    <span class="navbar-brand mb-0 h1"><i class="bi bi-clipboard-check me-2" aria-hidden="true"></i>Review Import</span>
    <a class="btn btn-sm btn-outline-light" href="<?= esc($backUrl, 'attr') ?>">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Back to Records
    </a>
</nav>

<main class="container-fluid px-4 py-4"
      id="importReview"
      data-row-url="<?= esc(site_url($routeBase . '/import/review/' . $jobId . '/row'), 'attr') ?>"
      data-commit-url="<?= esc(site_url($routeBase . '/import/review/' . $jobId . '/commit'), 'attr') ?>"
      data-cancel-url="<?= esc(site_url($routeBase . '/import/review/' . $jobId . '/cancel'), 'attr') ?>"
      data-status-base="<?= esc(site_url($routeBase . '/import/status/'), 'attr') ?>"
      data-redirect-url="<?= esc($backUrl, 'attr') ?>">

    <input type="hidden" id="reviewCsrf" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>">

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h4 mb-1">Check before importing</h2>
            <p class="text-muted mb-0">
                File: <strong id="reviewFileName"></strong>. Nothing is saved until you press
                <strong>Confirm import</strong>. Fix the highlighted values below — start with the QR numbers.
            </p>
        </div>
    </div>

    <div id="importReviewStats" class="row g-3 mb-4"></div>

    <div id="importReviewGroups"></div>

    <div class="import-review-actionbar d-flex flex-wrap justify-content-end align-items-center gap-2 mt-4">
        <span id="importReviewStatus" class="text-muted me-auto" role="status" aria-live="polite"></span>
        <button type="button" class="btn btn-outline-secondary" id="importReviewCancel">
            <i class="bi bi-x-circle me-1" aria-hidden="true"></i>Cancel import
        </button>
        <button type="button" class="btn btn-primary" id="importReviewConfirm" disabled>
            <i class="bi bi-check2-circle me-1" aria-hidden="true"></i>Confirm import
        </button>
    </div>
</main>

<script id="importReviewData" type="application/json"><?= $reviewJson ?></script>
<?php foreach (asset_scripts('core') as $scriptPath): ?>
<script src="<?= esc(asset_url($scriptPath), 'attr') ?>"></script>
<?php endforeach; ?>
<script src="<?= esc(asset_url('assets/js/dashboard/import-review.js'), 'attr') ?>"></script>
</body>
</html>
