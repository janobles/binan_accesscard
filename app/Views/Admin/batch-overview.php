<?php
/**
 * Dashboard Zone 2, "this batch": the batch picker, the served-of-eligible
 * headline, and the Barangay/Stations/Remaining tabs. A fragment, not a page:
 * rendered inline by Pages/dashboard.php for every role. Data comes
 * from DashboardPageBuilder::buildReportsData() (batches, batchRow, batchOpen,
 * batchSnapshot, remainingPage, batchBodyTab, busiestDay). All server data is
 * escaped.
 *
 * The batch figures render as a KPI card row plus a slim coverage bar,
 * matching the Overview pane's card row (`.kpi-card` styles are shared, see
 * theme.css).
 */

$batches = $batches ?? [];
$batchRow = $batchRow ?? null;
$batchOpen = (bool) ($batchOpen ?? false);
$batchSnapshot = $batchSnapshot ?? ['coverage' => ['eligible' => 0, 'served' => 0, 'remaining' => 0, 'coverage' => 0, 'voided' => 0], 'byBarangay' => [], 'perScanner' => [], 'timeline' => [], 'byDay' => []];
$remainingPage = $remainingPage ?? ['rows' => [], 'keyword' => '', 'page' => 1, 'perPage' => 25, 'perPageOptions' => [10, 25, 50, 100], 'totalPages' => 1, 'totalRows' => 0, 'fromRecord' => 0, 'toRecord' => 0];
$batchBodyTab = $batchBodyTab ?? 'barangay';
$busiestDay = $busiestDay ?? null;

$c = $batchSnapshot['coverage'];
$byBarangay = $batchSnapshot['byBarangay'];
$perScanner = $batchSnapshot['perScanner'];
$timeline = $batchSnapshot['timeline'];
$byDay = $batchSnapshot['byDay'] ?? [];

$batchId = (int) ($batchRow['batch_id'] ?? 0);
$noBatch = $batchRow === null;
$noEligible = ! $noBatch && $c['eligible'] === 0;
?>

<header class="batch-head">
  <?php /* A fixed heading, not the batch name: the selector on the right
           already carries the name, and printing it twice reads as two
           different scopes. The batch's state goes under the figure it
           qualifies, in .batch-progress-sub. */ ?>
  <h2>This batch</h2>
  <div class="section-actions">
    <?php if ($batches !== []): ?>
    <form class="reports-filter" method="get" action="<?= site_url('dashboard') ?>">
      <?php /* A GET form submits only its own fields, so the pane and the
               sub-tab have to ride along or picking a batch drops the reader
               back on Overview/Barangay. Same fix the scanner performance
               page's batch selector already carries. */ ?>
      <input type="hidden" name="view" value="distribution">
      <input type="hidden" name="tab" value="<?= esc($batchBodyTab, 'attr') ?>">
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
</header>

<?php if ($noBatch): ?>
<p class="text-muted mb-4">No distribution batch exists yet. Open one from the Distribution page to see its coverage here.</p>
<?php elseif ($noEligible): ?>
<p class="text-muted mb-4">This batch has no eligible families. Check its barangay and sector filters.</p>
<?php else: ?>

<div class="row row-cols-2 row-cols-md-4 g-3 kpi-row">
  <div class="col">
    <div class="card kpi-card h-100"><div class="card-body">
      <p class="kpi-label">Eligible families</p>
      <p class="kpi-value" id="progressEligible"><?= esc(number_format($c['eligible'])) ?></p>
    </div></div>
  </div>
  <div class="col">
    <div class="card kpi-card h-100"><div class="card-body">
      <p class="kpi-label">Served</p>
      <p class="kpi-value" id="progressServed"><?= esc(number_format($c['served'])) ?></p>
      <p class="kpi-sub"><span id="progressCoverage"><?= esc((string) $c['coverage']) ?></span>%</p>
    </div></div>
  </div>
  <div class="col">
    <div class="card kpi-card h-100"><div class="card-body">
      <p class="kpi-label"><?= $batchOpen ? 'Remaining' : 'Not claimed' ?></p>
      <p class="kpi-value" id="remainingTileValue"><?= esc(number_format($c['remaining'])) ?></p>
    </div></div>
  </div>
  <div class="col">
    <div class="card kpi-card h-100"><div class="card-body">
      <p class="kpi-label">Busiest day</p>
      <?php /* Carries ids, and the sub-line is always in the markup (hidden
               when there is nothing to say), because the live poll recomputes
               this card from the same byDay series it repaints the rollout
               chart from. Left server-only it would drift out of agreement
               with the tallest bar beside it during an open batch. */ ?>
      <p class="kpi-value" id="busiestDayLabel"><?= $busiestDay === null ? '-' : esc($busiestDay['label']) ?></p>
      <p class="kpi-sub<?= $busiestDay === null ? ' d-none' : '' ?>" id="busiestDaySub">
        <span id="busiestDayCount"><?= $busiestDay === null ? '0' : esc(number_format($busiestDay['served'])) ?></span> families</p>
    </div></div>
  </div>
</div>

<div class="progress batch-bar" id="coverageProgress" role="progressbar"
     aria-label="Coverage"
     aria-valuenow="<?= esc((string) $c['coverage'], 'attr') ?>"
     aria-valuemin="0" aria-valuemax="100">
  <div class="progress-bar" id="coverageProgressFill" style="width: <?= esc((string) $c['coverage'], 'attr') ?>%"></div>
</div>
<p class="batch-progress-sub">
  <?php /* Always rendered (hidden at 0) so the live poll can reveal it in
           place without inserting new markup mid-batch. */ ?>
  <span id="voidedTileWrap"<?= $c['voided'] > 0 ? '' : ' class="d-none"' ?>>
    <span id="voidedTileValue"><?= esc((string) $c['voided']) ?></span> voided &middot;</span>
  <?php if ($batchOpen): ?>
    batch open, updated <span id="lastUpdated">-</span>
  <?php else: ?>
    batch closed
  <?php endif; ?>
</p>

<?php if (count($byDay) > 1): ?>
<?php /* One bar per day the batch ran. A single-day batch gets no chart:
         one bar says nothing the Served card has not already said. Shown for
         closed batches too, because this is retrospective reporting rather
         than the live monitoring the cumulative timeline does. */ ?>
<section class="batch-pane">
  <h3 class="batch-pane-title">Rollout by day</h3>
  <div class="batch-rollout-chart"><canvas id="chartRollout"></canvas></div>
</section>
<?php endif; ?>

<?php if ($batchOpen): ?>
<section class="batch-pane">
  <h3 class="batch-pane-title">Families served over time</h3>
  <div class="batch-timeline-chart"><canvas id="chartTimeline"></canvas></div>
</section>
<?php endif; ?>

<?= view('components/page_tabs', [
    'tabs' => [
        ['key' => 'barangay', 'label' => 'Barangay'],
        ['key' => 'stations', 'label' => 'Stations'],
        ['key' => 'remaining', 'label' => 'Remaining'],
    ],
    'active' => $batchBodyTab,
    'baseUrl' => 'dashboard',
    // Named even though 'tab' is the default: the outer strip on
    // Pages/dashboard.php renders first with 'view', and CI4's renderer carries
    // view data from one view() call into the next, so leaving this implicit
    // makes the sub-tabs switch ?view= instead of ?tab=.
    'param' => 'tab',
    // Carries the pane and the batch selection through the sub-tab switch.
    // Without view=, DashboardPageBuilder falls back to Overview and the click
    // leaves the Distribution pane; without batch=, ?tab= alone silently
    // reverts an explicitly-picked batch back to the default.
    'queryParams' => ['view' => 'distribution', 'batch' => $batchId],
    // Subordinate to the outer Overview/Distribution strip on
    // Pages/dashboard.php: same component, toned down in theme.css so it
    // reads as inside a pane rather than a second page nav.
    'variantClass' => 'segmented-tabs--subordinate',
]) ?>

<?php if ($batchBodyTab === 'barangay'): ?>
  <div class="row g-3 align-items-start">
    <div class="col-12 col-lg-5">
      <?= view('Admin/batch-barangay-map', ['byBarangay' => $byBarangay]) ?>
    </div>
    <div class="col-12 col-lg-7">
      <div class="table-responsive">
        <table class="table manage-record-table align-middle w-100 mb-0" id="barangayTable">
          <thead><tr><th>Barangay</th><th>Eligible</th><th>Served</th><th>Coverage</th></tr></thead>
          <tbody>
            <?php foreach ($byBarangay as $b): ?>
            <tr data-barangay="<?= esc($b['barangay'], 'attr') ?>">
              <td><?= esc($b['barangay']) ?></td>
              <td><?= esc(number_format($b['total'])) ?></td>
              <td><?= esc(number_format($b['received'])) ?></td>
              <td><?= esc((string) $b['coverage']) ?>%</td>
            </tr>
            <?php endforeach; ?>
            <?php if ($byBarangay === []): ?>
            <tr><td colspan="4" class="text-muted">No data for this batch.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
<?php elseif ($batchBodyTab === 'stations'): ?>
  <?= view('Admin/batch-stations-grid', ['perScanner' => $perScanner, 'batchId' => $batchId]) ?>
<?php else: ?>
  <?php
  // Server-side paginated (SubsidyStatsModel::remainingPage()): against the
  // 100k-family target, fetching the whole roster into the DOM for
  // table-paginate.js to slice client-side (the Distribution Batches/Log
  // pattern) is too large a page. Preserves batch + tab across page links.
  $remainingPageUrl = static function (int $targetPage) use ($batchId, $remainingPage): string {
      $params = array_filter([
          'view'     => 'distribution',
          'tab'      => 'remaining',
          'batch'    => $batchId > 0 ? (string) $batchId : '',
          'q'        => $remainingPage['keyword'],
          'per_page' => $remainingPage['perPage'] !== 25 ? (string) $remainingPage['perPage'] : '',
          'page'     => $targetPage > 1 ? (string) $targetPage : '',
      ], static fn ($value): bool => $value !== '');

      return site_url('dashboard') . ($params === [] ? '' : '?' . http_build_query($params));
  };
  // Hidden fields that keep the pane, tab and batch selection through the
  // search and per-page forms below; a new search always lands on page 1.
  $remainingHiddenHtml = '<input type="hidden" name="view" value="distribution">'
      . '<input type="hidden" name="tab" value="remaining">'
      . ($batchId > 0 ? '<input type="hidden" name="batch" value="' . esc((string) $batchId, 'attr') . '">' : '');
  $remainingPerPageHidden = $remainingPage['perPage'] !== 25
      ? '<input type="hidden" name="per_page" value="' . esc((string) $remainingPage['perPage'], 'attr') . '">'
      : '';
  ?>
  <?= view('Admin/batch-remaining', [
      'remaining' => $remainingPage['rows'],
      'keyword' => $remainingPage['keyword'],
      'perPage' => $remainingPage['perPage'],
      'perPageOptions' => $remainingPage['perPageOptions'],
      'searchHiddenHtml' => $remainingHiddenHtml . $remainingPerPageHidden,
      'sizeHiddenHtml' => $remainingHiddenHtml . ($remainingPage['keyword'] !== ''
          ? '<input type="hidden" name="q" value="' . esc($remainingPage['keyword'], 'attr') . '">'
          : ''),
  ]) ?>
  <?= view('components/table_footer', [
      'fromRecord' => $remainingPage['fromRecord'],
      'toRecord' => $remainingPage['toRecord'],
      'totalRows' => $remainingPage['totalRows'],
      'page' => $remainingPage['page'],
      'totalPages' => $remainingPage['totalPages'],
      'pageUrl' => $remainingPageUrl,
      'entityLabel' => 'families',
  ]) ?>
<?php endif; ?>

<script id="reportsData" type="application/json"><?= json_encode(
    [
        'coverage'  => $c,
        'barangay'  => $byBarangay,
        'timeline'  => $timeline,
        'byDay'     => $byDay,
    ],
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
) ?></script>

<script src="<?= esc(asset_url('vendor/chart.js/chart.umd.min.js'), 'attr') ?>"></script>
<script src="<?= esc(asset_url('assets/js/dashboard/scanner-reports.js'), 'attr') ?>"></script>
<?php /* This fragment renders inside <main>, ahead of layout.php's Bootstrap
         bundle script at the end of the body, so a plain <script src> here
         would run before window.bootstrap exists and the Popover constructor
         below would throw. defer holds it until after the document (and the
         blocking bootstrap.bundle.min.js tag within it) has parsed. */ ?>
<script src="<?= esc(asset_url('assets/js/dashboard/barangay-map.js'), 'attr') ?>" defer></script>
<script>
(function () {
  // Live poll: fetch fresh stats for the selected batch and repaint the charts
  // in place (no page reload, so the batch selector, tab and scroll stay put).
  var statsUrl = '<?= site_url('distribution/reports/stats') ?>';
  var batchId = <?= (int) $batchId ?>;
  var batchOpen = <?= $batchOpen ? 'true' : 'false' ?>;
  // distribution/reports/stats carries no remaining-families rows, so there is
  // nothing to repaint the Remaining table with. Rather than tick the figures
  // and the "updated" stamp while the list underneath sits frozen (a poll that
  // looks live but visibly isn't), the whole poll is skipped on this tab.
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
