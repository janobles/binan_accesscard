/* Reports/Statistics tab. Reuses the same house style as Scanner/manage.php:
  nav-tabs header, sector-management panels, stat-card KPI tiles. Charts are
  progressive enhancement over the fallback summary tables (which also feed
  the PDF). All server data esc()'d. */

<?= $this->extend("Scanner/layout") ?>
<?= $this->section("content") ?>

 <?php $rangeLabel =
     $from || $to
         ? "Showing " .
             ($from ? esc($from) : "the beginning") .
             " to " .
             ($to ? esc($to) : "today")
         : "Showing all dates"; ?>

<!-- Date range + PDF export -->
<div class="reports-toolbar">
  <form class="reports-filter" method="get" action="<?= site_url(
      "scanner/reports",
  ) ?>">
    <label for="fromDate" class="form-label mb-0">From</label>
    <input class="form-control" type="date" id="fromDate" name="from" value="<?= esc(
        $from ?? "",
        "attr",
    ) ?>">
    <label for="toDate" class="form-label mb-0">To</label>
    <input class="form-control" type="date" id="toDate" name="to" value="<?= esc(
        $to ?? "",
        "attr",
    ) ?>">
    <button class="btn btn-primary reports-apply-btn" type="submit"><i class="bi bi-funnel" aria-hidden="true"></i><span>Apply</span></button>
  </form>
  <div class="reports-actions">
    <a class="btn btn-secondary" href="<?= site_url(
        "scanner/reports",
    ) ?>"><i class="bi bi-x-lg" aria-hidden="true"></i><span>Clear</span></a>
    <a class="btn btn-primary reports-download-btn" href="<?= site_url(
        "scanner/reports/pdf",
    ) .
        ($from || $to
            ? "?from=" .
                esc($from ?? "", "url") .
                "&to=" .
                esc($to ?? "", "url")
            : "") ?>"><i class="bi bi-file-earmark-arrow-down" aria-hidden="true"></i><span>Download Report</span></a>
  </div>
</div>
<p class="text-muted small mb-3"><?= $rangeLabel ?></p>

<!-- KPI tiles: same house style as the admin dashboard stat cards -->
<section class="reports-stats" aria-label="Report statistics">
  <article class="stat-card stat-card--records card h-100 py-2">
    <div class="card-body"><div class="stat-card-content">
      <div><p>Families with a QR</p><strong><?= esc(
          (string) $summary["total"],
      ) ?></strong></div>
      <i class="bi bi-qr-code stat-card-icon" aria-hidden="true"></i>
    </div></div>
  </article>
  <article class="stat-card stat-card--members card h-100 py-2">
    <div class="card-body"><div class="stat-card-content">
      <div><p>Received aid</p><strong><?= esc(
          (string) $summary["received"],
      ) ?></strong></div>
      <i class="bi bi-check2-circle stat-card-icon" aria-hidden="true"></i>
    </div></div>
  </article>
  <article class="stat-card stat-card--sectors card h-100 py-2">
    <div class="card-body"><div class="stat-card-content">
      <div><p>Still waiting</p><strong><?= esc(
          (string) $summary["notReceived"],
      ) ?></strong></div>
      <i class="bi bi-hourglass-split stat-card-icon" aria-hidden="true"></i>
    </div></div>
  </article>
  <article class="stat-card stat-card--services card h-100 py-2">
    <div class="card-body"><div class="stat-card-content">
      <div><p>Coverage</p><strong><?= esc(
          (string) $summary["coverage"],
      ) ?>%</strong></div>
      <i class="bi bi-pie-chart stat-card-icon" aria-hidden="true"></i>
    </div></div>
  </article>
</section>

<!-- Charts: each in the standard card anatomy (components/card) -->
<div class="row g-3 reports-charts">
  <div class="col-lg-4">
    <?= view('components/card', [
        'icon' => 'pie-chart',
        'title' => 'Families that received aid vs still waiting',
        'bodyHtml' => '<canvas id="chartReceived" height="220"></canvas>',
        'footer' => $rangeLabel,
        'cardClass' => 'reports-chart-card h-100',
    ]) ?>
  </div>
  <div class="col-lg-8">
    <?= view('components/card', [
        'icon' => 'bar-chart',
        'title' => 'Coverage by barangay (percent)',
        'bodyHtml' => '<div class="reports-barangay-chart"><canvas id="chartBarangay"></canvas></div>',
        'footer' => $rangeLabel,
        'cardClass' => 'reports-chart-card h-100',
    ]) ?>
  </div>
  <div class="col-lg-12">
    <?= view('components/card', [
        'icon' => 'bar-chart-line',
        'title' => 'Number of handouts by aid type',
        'bodyHtml' => '<canvas id="chartAidType" height="180"></canvas>',
        'footer' => $rangeLabel,
        'cardClass' => 'reports-chart-card',
    ]) ?>
  </div>
</div>

<!-- No-JS / print fallback summary table (components/data_table) -->
<?php
$barangayRows = [];
foreach ($byBarangay as $b) {
    $barangayRows[] = [
        esc($b["barangay"]),
        esc((string) $b["total"]),
        esc((string) $b["received"]),
        '<span class="badge bg-light text-dark border">' . esc((string) $b["coverage"]) . '%</span>',
    ];
}
?>
<?= view('components/data_table', [
    'icon' => 'table',
    'title' => 'Coverage by barangay',
    'columns' => ['Barangay', 'Families', 'Received', 'Coverage'],
    'rows' => $barangayRows,
    'emptyMessage' => 'No data for this range.',
    'tableClass' => 'table table-sm manage-record-table align-middle w-100 mb-0',
    'cardClass' => 'reports-fallback',
    'footer' => $rangeLabel,
]) ?>

<script id="reportsData" type="application/json"><?= json_encode(
    [
        "received" => $summary,
        "barangay" => $byBarangay,
        "aidType" => $byAidType,
    ],
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
) ?></script>

<?= $this->endSection() ?>
