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
 */

$perScanner = $perScanner ?? [];
$batchId = (int) ($batchId ?? 0);
?>
<?php if ($perScanner === []): ?>
<p class="text-muted">No station has logged a scan in this batch yet.</p>
<?php else: ?>
<div class="row row-cols-2 row-cols-sm-3 row-cols-lg-4 row-cols-xl-6 g-3" id="stationsGrid"
     data-performance-url="<?= esc(site_url('scanner/performance'), 'attr') ?>"
     data-batch="<?= esc((string) $batchId, 'attr') ?>">
  <?php foreach ($perScanner as $p): ?>
  <div class="col">
    <a class="station-square" href="<?= site_url('scanner/performance') ?>?scanner=<?= esc((string) (int) $p['userID'], 'attr') ?>&batch=<?= esc((string) $batchId, 'attr') ?>">
      <span class="station-name"><?= esc($p['scanner']) ?></span>
      <span class="station-count"><?= esc(number_format((int) $p['families'])) ?></span>
      <span class="station-unit">families</span>
    </a>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
