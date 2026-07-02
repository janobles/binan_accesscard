<?= $this->extend('Scanner/layout') ?>
<?= $this->section('content') ?>

<?php
/* Reports/Statistics tab. Reuses the same house style as Scanner/manage.php:
   nav-tabs header, sector-management panels, stat-card KPI tiles. Charts are
   progressive enhancement over the fallback summary tables (which also feed
   the PDF). All server data esc()'d. */
$rangeLabel = ($from || $to)
    ? 'Showing ' . ($from ? esc($from) : 'the beginning') . ' to ' . ($to ? esc($to) : 'today')
    : 'Showing all dates';
?>

<ul class="nav nav-tabs manage-tabs mb-0" role="tablist">
  <li class="nav-item"><a class="nav-link" href="<?= site_url('scanner/scan') ?>">Scan</a></li>
  <li class="nav-item"><a class="nav-link" href="<?= site_url('scanner/manage') ?>">Manage</a></li>
  <li class="nav-item"><a class="nav-link active" href="<?= site_url('scanner/reports') ?>">Reports</a></li>
</ul>

<div class="sector-management records-scroll-panel">

  <!-- Date range + PDF export -->
  <div class="records-search-panel">
    <form class="records-search-row records-lookup-search" method="get" action="<?= site_url('scanner/reports') ?>">
      <label for="fromDate" class="form-label mb-0">From</label>
      <input class="form-control" type="date" id="fromDate" name="from" value="<?= esc($from ?? '', 'attr') ?>">
      <label for="toDate" class="form-label mb-0">To</label>
      <input class="form-control" type="date" id="toDate" name="to" value="<?= esc($to ?? '', 'attr') ?>">
      <button class="btn btn-primary records-search-action" type="submit"><i class="bi bi-funnel" aria-hidden="true"></i><span>Apply</span></button>
      <a class="btn btn-outline-secondary records-search-action" href="<?= site_url('scanner/reports') ?>"><i class="bi bi-x-lg" aria-hidden="true"></i><span>Clear</span></a>
      <a class="btn btn-outline-danger records-search-action" href="<?= site_url('scanner/reports/pdf') . (($from || $to) ? '?from=' . esc($from ?? '', 'url') . '&to=' . esc($to ?? '', 'url') : '') ?>"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i><span>Download PDF report</span></a>
    </form>
    <p class="text-muted small mb-0 mt-2"><?= $rangeLabel ?></p>
  </div>

  <!-- KPI tiles -->
  <div class="stat-card-row d-flex flex-wrap gap-3 my-3">
    <article class="stat-card"><p>Families with a QR</p><strong><?= esc((string) $summary['total']) ?></strong></article>
    <article class="stat-card"><p>Received aid</p><strong><?= esc((string) $summary['received']) ?></strong></article>
    <article class="stat-card"><p>Still waiting</p><strong><?= esc((string) $summary['notReceived']) ?></strong></article>
    <article class="stat-card"><p>Coverage</p><strong><?= esc((string) $summary['coverage']) ?>%</strong></article>
  </div>

  <!-- Charts -->
  <div class="row g-3 reports-charts">
    <div class="col-lg-4">
      <div class="sector-management reports-chart-card">
        <h6>Families that received aid vs still waiting</h6>
        <canvas id="chartReceived" height="220"></canvas>
      </div>
    </div>
    <div class="col-lg-8">
      <div class="sector-management reports-chart-card">
        <h6>Coverage by barangay (percent)</h6>
        <canvas id="chartBarangay" height="220"></canvas>
      </div>
    </div>
    <div class="col-lg-12">
      <div class="sector-management reports-chart-card">
        <h6>Number of handouts by aid type</h6>
        <canvas id="chartAidType" height="180"></canvas>
      </div>
    </div>
  </div>

  <!-- No-JS / print fallback summary tables -->
  <div class="reports-fallback mt-3">
    <table class="table table-sm manage-record-table align-middle w-100">
      <thead><tr><th>Barangay</th><th>Families</th><th>Received</th><th>Coverage</th></tr></thead>
      <tbody>
        <?php foreach ($byBarangay as $b): ?>
          <tr>
            <td><?= esc($b['barangay']) ?></td>
            <td><?= esc((string) $b['total']) ?></td>
            <td><?= esc((string) $b['received']) ?></td>
            <td><span class="badge bg-light text-dark border"><?= esc((string) $b['coverage']) ?>%</span></td>
          </tr>
        <?php endforeach; ?>
        <?php if ($byBarangay === []): ?>
          <tr><td colspan="4" class="text-center text-muted">No data for this range.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script id="reportsData" type="application/json"><?= json_encode([
    'received'  => $summary,
    'barangay'  => $byBarangay,
    'aidType'   => $byAidType,
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

<?= $this->endSection() ?>
