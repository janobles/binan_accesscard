<?php
/**
 * Dashboard schedule card: the current month with the days each batch covers
 * marked, and the running or next batches listed beneath.
 *
 * Read only. Plotting happens on the Schedule tab of the distribution page,
 * which the heading links to. The grid is written by hand rather than with
 * FullCalendar because shrinking that into a single dashboard column costs
 * more than drawing a static month.
 *
 * Data source: DashboardPageBuilder::buildViewData(), keys upcomingSchedule
 * and scheduleMonthDays.
 */
$upcomingSchedule  = $upcomingSchedule ?? [];
$scheduleMonthDays = $scheduleMonthDays ?? [];

$first     = (int) date('N', strtotime(date('Y-m-01'))) % 7; // leading blanks, Sunday first
$daysInMonth = (int) date('t');
$today       = date('Y-m-d');
?>
<div class="card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span><i class="bi bi-calendar-event me-1"></i>Distribution Schedule</span>
    <a href="<?= esc(site_url('distribution?tab=schedule'), 'attr') ?>" class="small">Open calendar</a>
  </div>
  <div class="card-body">
    <p class="fw-semibold mb-2"><?= esc(date('F Y')) ?></p>
    <table class="table table-sm text-center mb-3">
      <thead>
        <tr class="text-muted small">
          <th>S</th><th>M</th><th>T</th><th>W</th><th>T</th><th>F</th><th>S</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <?php for ($blank = 0; $blank < $first; $blank++): ?>
            <td></td>
          <?php endfor; ?>
          <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
            <?php
              $date  = date('Y-m-') . str_pad((string) $day, 2, '0', STR_PAD_LEFT);
              $color = $scheduleMonthDays[$date] ?? null;
            ?>
            <td<?= $date === $today ? ' class="fw-bold"' : '' ?>>
              <?= esc((string) $day) ?>
              <?php if ($color !== null): ?>
                <span class="d-block mx-auto rounded-circle" aria-hidden="true"
                      style="width:5px;height:5px;background:var(--batch-<?= esc($color, 'attr') ?>)"></span>
              <?php endif; ?>
            </td>
            <?php if ((($day + $first) % 7) === 0 && $day !== $daysInMonth): ?>
              </tr><tr>
            <?php endif; ?>
          <?php endfor; ?>
        </tr>
      </tbody>
    </table>

    <?php if ($upcomingSchedule === []): ?>
      <p class="text-muted small mb-0">Nothing scheduled. Plot a distribution on the calendar.</p>
    <?php else: ?>
      <?php foreach ($upcomingSchedule as $row): ?>
        <div class="d-flex gap-2 align-items-start border-top pt-2 mt-2">
          <span class="rounded" aria-hidden="true"
                style="width:4px;align-self:stretch;background:var(--batch-<?= esc($row['color'], 'attr') ?>)"></span>
          <div class="small">
            <span class="fw-semibold d-block"><?= esc($row['name']) ?></span>
            <span class="text-muted d-block"><?= esc($row['venue']) ?></span>
            <span class="text-muted d-block">
              <?= esc(date('M j', strtotime($row['start']))) ?><?= $row['end'] !== $row['start'] ? esc(' to ' . date('M j', strtotime($row['end']))) : '' ?>
            </span>
            <?php if ($row['status'] === 'running'): ?>
              <span class="badge bg-success-subtle text-success-emphasis mt-1">Scanning open</span>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
