<?php
/**
 * Dashboard Zone 2, "this batch": the batch selector, progress block, coverage
 * and cumulative charts, and the Barangay/Stations/Remaining table. A
 * fragment, not a page: rendered inline by Pages/dashboard.php for
 * Developer/Admin only. Data comes from
 * DashboardPageBuilder::buildReportsData() (batches, batchRow, batchOpen,
 * batchSnapshot, remainingPage, batchBodyTab). All server data is escaped.
 */

$batches = $batches ?? [];
$batchRow = $batchRow ?? null;
$batchOpen = (bool) ($batchOpen ?? false);
$batchSnapshot = $batchSnapshot ?? ['coverage' => ['eligible' => 0, 'served' => 0, 'remaining' => 0, 'coverage' => 0, 'voided' => 0], 'byBarangay' => [], 'perScanner' => [], 'timeline' => []];
$remainingPage = $remainingPage ?? ['rows' => [], 'keyword' => '', 'page' => 1, 'perPage' => 25, 'perPageOptions' => [10, 25, 50, 100], 'totalPages' => 1, 'totalRows' => 0, 'fromRecord' => 0, 'toRecord' => 0];
$batchBodyTab = $batchBodyTab ?? 'barangay';

$c = $batchSnapshot['coverage'];
$byBarangay = $batchSnapshot['byBarangay'];
$perScanner = $batchSnapshot['perScanner'];
$timeline = $batchSnapshot['timeline'];

$batchId = (int) ($batchRow['batch_id'] ?? 0);
$rangeLabel = $batchRow !== null ? 'Showing batch: ' . esc($batchRow['name']) : 'No batch selected';
$noBatch = $batchRow === null;
$noEligible = ! $noBatch && $c['eligible'] === 0;
// A closed batch with no scans can never grow bars, so the coverage chart would
// be a screen of empty gridlines under one label per barangay. An OPEN batch at
// zero still gets the canvas: the live poll fills it in place, and swapping
// markup mid-batch is what the poll is written to avoid.
$emptyChart = ! $noBatch && ! $batchOpen && $c['served'] === 0;
?>

<div class="dashboard-section-head dashboard-section-head--subtitled">
  <div class="dashboard-section-head-title">
    <h2><i class="bi bi-clipboard-check-fill" aria-hidden="true"></i>This batch</h2>
    <p class="text-muted small mb-0"><?= $rangeLabel ?><?php if ($batchOpen): ?> &middot; Last updated <span id="lastUpdated">-</span><?php endif; ?></p>
  </div>
  <div class="section-actions">
    <?php if ($batches !== []): ?>
    <form class="reports-filter" method="get" action="<?= site_url('dashboard') ?>">
      <label for="batchPick" class="form-label mb-0 visually-hidden">Batch</label>
      <select class="form-select" id="batchPick" name="batch" onchange="this.form.submit()">
        <?php foreach ($batches as $b): ?>
          <option value="<?= esc($b['batch_id'], 'attr') ?>" <?= $batchId === (int) $b['batch_id'] ? 'selected' : '' ?>>
            <?= esc($b['name']) ?><?= $b['closed_at'] === null ? ' (open)' : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
    </form>
    <?php endif; ?>
    <?php if (! $noBatch): ?>
    <a class="btn btn-primary reports-download-btn" href="<?= site_url('distribution/reports/pdf') . '?batch=' . (int) $batchId ?>"><i class="bi bi-file-earmark-arrow-down" aria-hidden="true"></i><span>Download Report</span></a>
    <?php endif; ?>
  </div>
</div>

<?php if ($noBatch): ?>
<p class="text-muted mb-4"><i class="bi bi-info-circle" aria-hidden="true"></i> No distribution batch exists yet. Open one from the Distribution page to see its coverage here.</p>
<?php elseif ($noEligible): ?>
<p class="text-muted mb-4"><i class="bi bi-info-circle" aria-hidden="true"></i> This batch has no eligible families. Check its barangay and sector filters.</p>
<?php else: ?>

<div class="row g-3">
  <div class="col-12 col-lg-6">
    <?php /* Progress block: one unit, because remaining and percent are both
             derived from served and eligible. */ ?>
    <div class="card h-100"><div class="card-body">
      <p class="stat-label">This batch</p>
      <strong class="progress-headline">
        <span id="progressServed"><?= esc((string) $c['served']) ?></span> of
        <span id="progressEligible"><?= esc((string) $c['eligible']) ?></span> served,
        <span id="progressCoverage"><?= esc((string) $c['coverage']) ?></span>%
      </strong>
      <div class="progress" id="coverageProgress" role="progressbar"
           aria-valuenow="<?= esc((string) $c['coverage'], 'attr') ?>"
           aria-valuemin="0" aria-valuemax="100">
        <div class="progress-bar" id="coverageProgressFill" style="width: <?= esc((string) $c['coverage'], 'attr') ?>%"></div>
      </div>
    </div></div>
  </div>
  <?php /* Full width below sm: the Voided tile beside this one is hidden at 0,
           so a col-6 leaves a lone half-width card against dead space on a
           phone. */ ?>
  <div class="col-12 col-sm-6 col-lg-3">
    <?= view('components/stat_card', [
        'label' => $batchOpen ? 'Remaining' : 'Not claimed',
        'value' => (string) $c['remaining'],
        'icon' => 'hourglass-split',
        'variant' => 'stat-card--records',
        'valueId' => 'remainingTileValue',
    ]) ?>
  </div>
  <?php /* Always rendered (hidden at 0) so a live poll can reveal it in place
           without inserting new markup mid-batch. */ ?>
  <div class="col-12 col-sm-6 col-lg-3<?= $c['voided'] > 0 ? '' : ' d-none' ?>" id="voidedTileWrap">
    <?= view('components/stat_card', [
        'label' => 'Voided',
        'value' => (string) $c['voided'],
        'icon' => 'x-circle',
        'variant' => 'stat-card--members',
        'valueId' => 'voidedTileValue',
    ]) ?>
  </div>
</div>

<div class="row g-3 reports-charts mt-1">
  <div class="col-12 col-lg-8">
    <?= view('components/card', [
        'icon' => 'bar-chart-fill',
        'title' => 'Coverage by barangay (percent, worst first)',
        'bodyHtml' => $emptyChart
            ? '<p class="text-muted mb-0">Nothing was handed out in this batch, so there is no coverage to plot. The per-barangay roster is in the Barangay tab below.</p>'
            : '<div class="reports-barangay-chart"><canvas id="chartBarangay"></canvas></div>',
        // No footer: the batch name is already the section subtitle above, and
        // repeating it under the chart reads as a second, different scope.
        'footer' => null,
        'cardClass' => 'reports-chart-card h-100',
    ]) ?>
  </div>
  <div class="col-12 col-lg-4">
    <?php if ($batchOpen): ?>
    <?= view('components/card', [
        'icon' => 'graph-up',
        'title' => 'Families served over time',
        'bodyHtml' => '<div class="batch-timeline-chart"><canvas id="chartTimeline"></canvas></div>',
        'cardClass' => 'reports-chart-card h-100',
    ]) ?>
    <?php else: ?>
    <?php
    $scannerSummaryRows = [];
    foreach ($perScanner as $p) {
        $scannerSummaryRows[] = [esc($p['scanner']), esc((string) $p['families'])];
    }
    ?>
    <?= view('components/data_table', [
        'icon' => 'person-badge',
        'title' => 'Per-kiosk families served',
        'columns' => ['Scanner', 'Families'],
        'rows' => $scannerSummaryRows,
        'emptyMessage' => 'No scans in this batch.',
        'tableClass' => 'table align-middle w-100 mb-0',
        'cardClass' => 'reports-fallback h-100 mb-0',
        'footer' => null,
    ]) ?>
    <?php endif; ?>
  </div>
</div>

<?= view('components/page_tabs', [
    'tabs' => [
        ['key' => 'barangay', 'label' => 'Barangay'],
        ['key' => 'stations', 'label' => 'Stations'],
        ['key' => 'remaining', 'label' => 'Remaining'],
    ],
    'active' => $batchBodyTab,
    'baseUrl' => 'dashboard',
    // Carries the batch selection through the sub-tab switch: without this,
    // ?tab= alone silently reverts an explicitly-picked batch back to the
    // default (active, or most recent) on every Barangay/Stations/Remaining
    // click.
    'queryParams' => $noBatch ? [] : ['batch' => $batchId],
]) ?>

<?php if ($batchBodyTab === 'barangay'):
    $barangayRows = [];
    foreach ($byBarangay as $b) {
        $barangayRows[] = [
            esc($b['barangay']),
            esc((string) $b['total']),
            esc((string) $b['received']),
            '<span class="badge bg-light text-dark border">' . esc((string) $b['coverage']) . '%</span>',
        ];
    }
    ?>
    <?= view('components/data_table', [
        'icon' => 'table',
        'title' => 'Coverage by barangay',
        'columns' => ['Barangay', 'Eligible', 'Served', 'Coverage'],
        'rows' => $barangayRows,
        'emptyMessage' => 'No data for this batch.',
        'tableClass' => 'table manage-record-table align-middle w-100 mb-0',
        'cardClass' => 'reports-fallback',
        'footer' => null,
    ]) ?>
<?php elseif ($batchBodyTab === 'stations'):
    // Plain text, not a link: scanner/performance reads $userId from the
    // session, not a query param, so a link here would take an admin to their
    // own (usually zero) numbers under a station's name - a silent wrong
    // answer, worse than no link at all. Extending that route to accept a
    // target user is Task 12's scope, not this one's.
    $stationRows = [];
    foreach ($perScanner as $p) {
        $stationRows[] = [
            esc($p['scanner']),
            esc((string) $p['families']),
            esc((string) $p['handouts']),
        ];
    }
    ?>
    <?= view('components/data_table', [
        'icon' => 'person-badge',
        'title' => 'Per-station performance',
        'columns' => ['Scanner', 'Families', 'Handouts'],
        'rows' => $stationRows,
        'emptyMessage' => 'No scans in this batch yet.',
        'tableClass' => 'table align-middle w-100 mb-0',
        'cardClass' => 'reports-fallback',
        'footer' => null,
        'tableId' => 'stationsTable',
    ]) ?>
<?php else:
    // Server-side paginated (SubsidyStatsModel::remainingPage()): against the
    // 100k-family target, fetching the whole roster into the DOM for
    // table-paginate.js to slice client-side (the Distribution Batches/Log
    // pattern) is too large a page. Preserves batch + tab across page links.
    $remainingPageUrl = static function (int $targetPage) use ($batchId, $remainingPage): string {
        $params = array_filter([
            'tab'      => 'remaining',
            'batch'    => $batchId > 0 ? (string) $batchId : '',
            'q'        => $remainingPage['keyword'],
            'per_page' => $remainingPage['perPage'] !== 25 ? (string) $remainingPage['perPage'] : '',
            'page'     => $targetPage > 1 ? (string) $targetPage : '',
        ], static fn ($value): bool => $value !== '');

        return site_url('dashboard') . ($params === [] ? '' : '?' . http_build_query($params));
    };
    // Hidden fields that keep the tab/batch selection through the search and
    // per-page forms below; a new search always lands on page 1.
    $remainingHiddenHtml = '<input type="hidden" name="tab" value="remaining">'
        . ($batchId > 0 ? '<input type="hidden" name="batch" value="' . esc((string) $batchId, 'attr') . '">' : '');
    $remainingPerPageHidden = $remainingPage['perPage'] !== 25
        ? '<input type="hidden" name="per_page" value="' . esc((string) $remainingPage['perPage'], 'attr') . '">'
        : '';
    ?>
    <?= view('components/card', [
        'icon' => 'hourglass-split',
        'title' => $batchOpen ? 'Remaining families' : 'Families not claimed',
        'cardClass' => 'reports-fallback',
        'bodyView' => 'Admin/dashboard-remaining-body',
        'bodyData' => [
            'remaining' => $remainingPage['rows'],
            'keyword' => $remainingPage['keyword'],
            'perPage' => $remainingPage['perPage'],
            'perPageOptions' => $remainingPage['perPageOptions'],
            'searchHiddenHtml' => $remainingHiddenHtml . $remainingPerPageHidden,
            'sizeHiddenHtml' => $remainingHiddenHtml . ($remainingPage['keyword'] !== ''
                ? '<input type="hidden" name="q" value="' . esc($remainingPage['keyword'], 'attr') . '">'
                : ''),
        ],
        'footer' => view('components/table_footer', [
            'fromRecord' => $remainingPage['fromRecord'],
            'toRecord' => $remainingPage['toRecord'],
            'totalRows' => $remainingPage['totalRows'],
            'page' => $remainingPage['page'],
            'totalPages' => $remainingPage['totalPages'],
            'pageUrl' => $remainingPageUrl,
            'entityLabel' => 'families',
        ]),
    ]) ?>
<?php endif; ?>

<script id="reportsData" type="application/json"><?= json_encode(
    [
        'coverage'  => $c,
        'barangay'  => $byBarangay,
        'timeline'  => $timeline,
    ],
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
) ?></script>

<script src="<?= esc(asset_url('vendor/chart.js/chart.umd.min.js'), 'attr') ?>"></script>
<script src="<?= esc(asset_url('assets/js/dashboard/scanner-reports.js'), 'attr') ?>"></script>
<script>
(function () {
  // Live poll: fetch fresh stats for the selected batch and repaint the charts
  // in place (no page reload, so the batch selector, tab and scroll stay put).
  var statsUrl = '<?= site_url('distribution/reports/stats') ?>';
  var batchId = <?= (int) $batchId ?>;
  var batchOpen = <?= $batchOpen ? 'true' : 'false' ?>;
  // distribution/reports/stats carries no remaining-families rows, so there is
  // nothing to repaint the Remaining table with. Rather than tick the tile and
  // the "Last updated" stamp while the list underneath sits frozen (a poll
  // that looks live but visibly isn't), the whole poll is skipped on this tab:
  // tile, list and stamp all stay exactly what the page loaded with, together.
  var onRemainingTab = <?= $batchBodyTab === 'remaining' ? 'true' : 'false' ?>;
  if (batchId > 0) { statsUrl += '?batch=' + batchId; }

  function apply(d) {
    if (window.ReportsCharts) { window.ReportsCharts.update(d); }
    var stamp = document.getElementById('lastUpdated');
    if (stamp) { stamp.textContent = new Date().toLocaleTimeString(); }
  }

  function poll() {
    fetch(statsUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) { if (d) { apply(d); } })
      .catch(function () {});
  }

  var stamp = document.getElementById('lastUpdated');
  if (stamp) { stamp.textContent = new Date().toLocaleTimeString(); }

  // Live-poll only while the selected batch is open (closed batches are
  // static), the sub-tab isn't Remaining (see onRemainingTab above), and the
  // tab is visible; browsers throttle hidden-tab timers anyway.
  if (batchOpen && !onRemainingTab) {
    setInterval(function () {
      if (document.visibilityState === 'visible') { poll(); }
    }, 5000);
  }
})();
</script>
<?php endif; ?>
