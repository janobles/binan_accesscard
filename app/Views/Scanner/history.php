<?php
/**
 * "View all" page for one scanned family (Scanner\ScanController::history()).
 * The tabs themselves live in Scanner/history-fragment.php, shared with the
 * AJAX fragment the scan panel injects inline (see history()'s isAJAX() branch).
 */
$controlNo = (int) ($controlNo ?? 0);
$headName  = (string) ($headName ?? '');
?>
<?= $this->extend('Scanner/simple-layout') ?>
<?= $this->section('content') ?>

<div class="d-flex align-items-center gap-2 mb-3">
  <a href="<?= site_url('scanner/scan') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back to Scan</a>
  <h1 class="h5 mb-0 ms-2"><?= esc($headName) ?> (Family #<?= esc((string) $controlNo) ?>)</h1>
</div>

<?= view('Scanner/history-fragment', get_defined_vars()) ?>

<?= $this->endSection() ?>
