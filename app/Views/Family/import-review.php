<?php
/**
 * Import Review — full-page screen where an operator resolves import problems before any
 * data is written. The staged rows + grouped errors are rendered by import-review.js from
 * the JSON island below. A flagged family can be fixed two ways: in the spreadsheet (every
 * issue names the exact cell), or in place via the Edit modal — which POSTs to
 * import/review/:id/family/:qr/save, re-validates server-side, and re-renders without a
 * re-upload. Confirm queues the write job.
 *
 * @var int    $jobId
 * @var string $routeBase  admin/manage-family or employee/manage-family
 * @var array  $review     ImportReviewPresenter::build() output
 * @var string $username
 * @var int    $idleTimeoutSeconds
 */
$jobId     = (int) ($jobId ?? 0);
$routeBase = (string) ($routeBase ?? 'admin/manage-family');
$review    = $review ?? ['file' => '', 'counts' => ['families' => 0, 'members' => 0, 'blocking' => 0, 'warnings' => 0], 'groups' => []];
$backUrl   = site_url($routeBase);
$idleTimeoutSeconds = (int) ($idleTimeoutSeconds ?? 900);

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
      data-commit-url="<?= esc(site_url($routeBase . '/import/review/' . $jobId . '/commit'), 'attr') ?>"
      data-cancel-url="<?= esc(site_url($routeBase . '/import/review/' . $jobId . '/cancel'), 'attr') ?>"
      data-family-base-url="<?= esc(site_url($routeBase . '/import/review/' . $jobId . '/family'), 'attr') ?>"
      data-redirect-url="<?= esc($backUrl, 'attr') ?>">

    <input type="hidden" id="reviewCsrf" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>">

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h4 mb-1">Check before importing</h2>
            <p class="text-muted mb-0">
                File: <strong id="reviewFileName"></strong>. Nothing is saved until you press
                <strong>Confirm import</strong>. Fix a flagged family right here with
                <strong>Edit</strong>, or <strong>Remove</strong> it from this import.
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

<?php /* Shared modal target for the in-place family Edit flow. manage-family-modal.js loads
         the prefilled family-modal fragment into #familyModalBody (same as Manage Records). */ ?>
<?= view('components/modal', [
    'id' => 'familyModal',
    'modalClass' => 'floating-family-modal',
    'attrs' => 'aria-label="Fix family record" data-bs-backdrop="static" data-bs-keyboard="false"',
    'size' => 'modal-xl',
    'title' => 'Fix Family Record',
    'titleId' => 'familyModalLabel',
    'bodyId' => 'familyModalBody',
    'bodyHtml' => '<div class="family-modal-loading" role="status" aria-live="polite"><div class="spinner-border text-primary" aria-hidden="true"></div><span>Loading...</span></div>',
    'footerHtml' => '<button type="button" class="btn btn-outline-secondary family-modal-close" data-bs-dismiss="modal">Close</button>',
]) ?>

<?= view('Family/action-confirm-modal') ?>

<script id="importReviewData" type="application/json"><?= $reviewJson ?></script>
<?php foreach (asset_scripts('core') as $scriptPath): ?>
<script src="<?= esc(asset_url($scriptPath), 'attr') ?>"></script>
<?php endforeach; ?>
<?php /* Dashboard modal infrastructure so the family Edit modal works on this standalone
         page (the dashboard layouts load these; this page is its own shell). */ ?>
<script src="<?= esc(asset_url('assets/js/dashboard/dashboard-modal-loader.js'), 'attr') ?>"></script>
<script src="<?= esc(asset_url('assets/js/dashboard/manage-family-modal.js'), 'attr') ?>"></script>
<script src="<?= esc(asset_url('assets/js/dashboard/import-review.js'), 'attr') ?>"></script>
<?php /* Idle-timeout logout — this page is its own shell, so it wires the same
         session-timeout script the dashboard layouts render. */ ?>
<script src="<?= esc(asset_url('assets/js/session-timeout.js'), 'attr') ?>"
        data-timeout-seconds="<?= esc((string) $idleTimeoutSeconds) ?>"
        data-logout-url="<?= site_url('logout?timeout=1') ?>"
        data-home-url="<?= site_url('/') ?>"
        data-keep-alive-url="<?= site_url('session/keep-alive') ?>"></script>
</body>
</html>
