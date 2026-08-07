<?php
/**
 * The scanner stations working this batch, one square each.
 *
 * A station appears once it logs its first successful scan, so the grid is the
 * fleet that actually turned up rather than a fixed set of configured slots.
 * Rows come from SubsidyStatsModel::perScanner() through the batch snapshot.
 *
 * Squares are labelled with the account username because that is already the
 * operational identity: the accounts are named Scanner1 through Scanner20, so
 * inventing a separate kiosk numbering would add a second name for one thing.
 *
 * A square opens a modal rather than the station's own page, which lives in the
 * kiosk shell: an admin reading the dashboard wants one station's figures, not
 * to be dropped into the scanner's chrome and have to navigate back. Only
 * Developer and Admin get that, because the figures come from scanner/stats and
 * that endpoint answers Scanner/Admin/Developer only; for anyone else the
 * square renders as a plain tile, since a control that 403s is worse than no
 * control. The modal itself lives in Admin/batch-overview.php.
 *
 * The grid container always renders, even with zero stations: the live poll
 * (scanner-reports.js applyStations()) targets #stationsGrid by id, and an
 * open batch's first station needs somewhere to land without a page reload.
 */

$perScanner = $perScanner ?? [];
$batchId = (int) ($batchId ?? 0);
$canDrillIn = (bool) ($canDrillIn ?? false);
?>
<div class="row row-cols-2 row-cols-sm-3 row-cols-lg-4 row-cols-xl-6 g-3" id="stationsGrid"
     data-batch="<?= esc((string) $batchId, 'attr') ?>"
     data-can-drill-in="<?= $canDrillIn ? '1' : '0' ?>">
  <?php if ($perScanner === []): ?>
  <p class="text-muted" id="stationsGridEmpty">No station has logged a scan in this batch yet.</p>
  <?php else: ?>
  <?php foreach ($perScanner as $p): ?>
  <div class="col">
    <?php if ($canDrillIn): ?>
    <button type="button" class="station-square"
            data-scanner-id="<?= esc((string) (int) $p['userID'], 'attr') ?>"
            data-scanner-name="<?= esc($p['scanner'], 'attr') ?>">
      <span class="station-name"><?= esc($p['scanner']) ?></span>
      <span class="station-count"><?= esc(number_format((int) $p['families'])) ?></span>
      <span class="station-unit">families</span>
    </button>
    <?php else: ?>
    <div class="station-square is-static">
      <span class="station-name"><?= esc($p['scanner']) ?></span>
      <span class="station-count"><?= esc(number_format((int) $p['families'])) ?></span>
      <span class="station-unit">families</span>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>
