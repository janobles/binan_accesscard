<?php
/**
 * The dashboard's Overview pane: the program end to end, from profiling
 * through to distribution. Never scoped to a batch.
 *
 * Figures come from DashboardModel::programStats() and the per-batch outcome
 * rows from DashboardPageBuilder::buildDistributionRows(), both handed over by
 * DashboardPageBuilder::buildViewData(). The schedule card at the bottom is
 * Admin/dashboard-schedule-card.php, fed by the same builder's
 * buildUpcomingSchedule() and buildScheduleGrid().
 *
 * The tiles carry no icon and no card header block. That is a deliberate
 * exception to the SB Admin card convention, scoped to KPI tiles: an icon
 * beside a number is decoration, and four of them compete with the four
 * numbers.
 */

$overviewStats = $overviewStats ?? ['families' => 0, 'cardsIssued' => 0, 'distributions' => 0, 'everServed' => 0, 'neverServed' => 0];
$distributionRows = $distributionRows ?? [];

$neverServed = (int) ($overviewStats['neverServed'] ?? 0);

// Read left to right the row is the program's funnel: every family profiled,
// how many of them hold a card, how many have collected at least once, and the
// number of distributions those collections came out of. Never-served is
// families minus ever-served, so it rides under the card it is the remainder of
// rather than taking a fourth tile to restate a number already on the row.
$cards = [
    ['label' => 'Families profiled', 'value' => (int) $overviewStats['families'], 'sub' => null],
    ['label' => 'Access cards issued', 'value' => (int) ($overviewStats['cardsIssued'] ?? 0), 'sub' => null],
    [
        'label' => 'Families ever served',
        'value' => (int) $overviewStats['everServed'],
        'sub'   => number_format($neverServed) . ' never served',
    ],
    ['label' => 'Distributions hosted', 'value' => (int) $overviewStats['distributions'], 'sub' => null],
];
?>
<h2 class="dashboard-zone-title">Program to date</h2>

<div class="row row-cols-2 row-cols-md-4 g-3 kpi-row">
  <?php foreach ($cards as $card): ?>
  <div class="col">
    <div class="card kpi-card h-100">
      <div class="card-body">
        <p class="kpi-label"><?= esc($card['label']) ?></p>
        <p class="kpi-value"><?= esc(number_format($card['value'])) ?></p>
        <?php if ($card['sub'] !== null): ?>
        <p class="kpi-sub"><?= esc($card['sub']) ?></p>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<section class="batch-pane">
  <h3 class="batch-pane-title">Distributions</h3>
  <div class="table-responsive">
    <table class="table manage-record-table align-middle w-100 mb-0">
      <thead>
        <tr><th>Batch</th><th>Subsidy</th><th>Opened</th><th>Eligible</th><th>Served</th><th>Coverage</th></tr>
      </thead>
      <tbody>
        <?php foreach ($distributionRows as $row): ?>
        <tr>
          <td>
            <a href="<?= site_url('dashboard') ?>?view=distribution&batch=<?= esc((string) (int) $row['batch_id'], 'attr') ?>">
              <?= esc((string) $row['name']) ?>
            </a>
            <?= ($row['closed_at'] ?? null) === null ? '<span class="status-pill is-muted">open</span>' : '' ?>
          </td>
          <td><?= esc((string) ($row['subsidy_type_name'] ?? '')) ?></td>
          <td><?= esc((string) ($row['started_at'] ?? '')) ?></td>
          <td><?= esc(number_format((int) $row['eligible'])) ?></td>
          <td><?= esc(number_format((int) $row['served'])) ?></td>
          <td><?= esc((string) (int) $row['coverage']) ?>%</td>
        </tr>
        <?php endforeach; ?>
        <?php if ($distributionRows === []): ?>
        <tr><td colspan="6" class="text-muted">No distribution has been run yet. Open one from the Distribution page.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="batch-pane">
  <h3 class="batch-pane-title">Upcoming schedule</h3>
  <?= view('Admin/dashboard-schedule-card', [
      'upcomingSchedule' => $upcomingSchedule ?? [],
      'scheduleGrid'     => $scheduleGrid ?? ['weeks' => [], 'bars' => []],
  ]) ?>
</section>
