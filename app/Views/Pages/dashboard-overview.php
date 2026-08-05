<?php
/**
 * The dashboard's Overview pane: the program end to end, from profiling
 * through to distribution. Never scoped to a batch.
 *
 * Figures come from DashboardModel::programStats() and the per-batch outcome
 * rows from DashboardPageBuilder::buildDistributionRows(), both handed over by
 * DashboardPageBuilder::buildViewData().
 *
 * The tiles carry no icon and no card header block. That is a deliberate
 * exception to the SB Admin card convention, scoped to KPI tiles: an icon
 * beside a number is decoration, and four of them compete with the four
 * numbers.
 */

$overviewStats = $overviewStats ?? ['families' => 0, 'distributions' => 0, 'everServed' => 0, 'neverServed' => 0];
$distributionRows = $distributionRows ?? [];

$cards = [
    ['label' => 'Families profiled', 'value' => (int) $overviewStats['families']],
    ['label' => 'Distributions hosted', 'value' => (int) $overviewStats['distributions']],
    ['label' => 'Families ever served', 'value' => (int) $overviewStats['everServed']],
    ['label' => 'Families never served', 'value' => (int) $overviewStats['neverServed']],
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
