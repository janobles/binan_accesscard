<?php
/**
 * One scanner's eight figures as label-over-value lines, on the same
 * .family-detail-grid pattern the family record view uses. Not KPI cards: four
 * cards already crowded the station modal, and eight would be a wall of boxes.
 *
 * Every line carries a qualifier under the value, because none of these numbers
 * is readable alone. "47 an hour" means nothing without "while active", which
 * is the whole distinction between this and the wall-clock figure it replaced.
 *
 * Shared by the station modal and the kiosk performance page, so the two
 * cannot drift the way a copy-pasted card set would.
 *
 * Params: $metrics one byScanner row (families, handouts, pace, typicalSeconds,
 * share, onStationSeconds, idleSeconds, bestHour, bestHourFamilies), or null
 * before the fetch lands / when the account has no scans in the batch.
 */

use App\Libraries\ViewFormatter;

$metrics = $metrics ?? null;

$hourRange = static fn (int $hour): string => date('ga', mktime($hour, 0)) . ' - ' . date('ga', mktime($hour + 1, 0));
?>
<div class="family-detail-grid" aria-live="polite">
  <div class="family-detail-item">
    <span>Families served</span>
    <strong data-metric="families"><?= $metrics === null ? '-' : esc(number_format((int) $metrics['families'])) ?></strong>
  </div>
  <div class="family-detail-item">
    <span>Handouts logged</span>
    <strong data-metric="handouts"><?= $metrics === null ? '-' : esc(number_format((int) $metrics['handouts'])) ?></strong>
  </div>
  <div class="family-detail-item">
    <span>Pace</span>
    <strong data-metric="pace"><?= $metrics === null || $metrics['pace'] === null ? '-' : esc(number_format((float) $metrics['pace'], 0) . ' / hour') ?></strong>
    <small data-metric-sub="pace">while active</small>
  </div>
  <div class="family-detail-item">
    <span>Typical time</span>
    <strong data-metric="typical"><?= $metrics === null || $metrics['typicalSeconds'] === null ? '-' : esc(ViewFormatter::duration((int) $metrics['typicalSeconds'])) ?></strong>
    <small data-metric-sub="typical">per family</small>
  </div>
  <div class="family-detail-item">
    <span>On station</span>
    <strong data-metric="onStation"><?= $metrics === null ? '-' : esc(ViewFormatter::duration((int) $metrics['onStationSeconds'])) ?></strong>
    <small data-metric-sub="onStation">first scan to last</small>
  </div>
  <div class="family-detail-item">
    <span>Idle</span>
    <strong data-metric="idle"><?= $metrics === null ? '-' : esc(ViewFormatter::duration((int) $metrics['idleSeconds'])) ?></strong>
    <small data-metric-sub="idle">gaps longer than 15 minutes</small>
  </div>
  <div class="family-detail-item">
    <span>Best hour</span>
    <strong data-metric="bestHour"><?= $metrics === null || $metrics['bestHour'] === null ? '-' : esc($hourRange((int) $metrics['bestHour'])) ?></strong>
    <small data-metric-sub="bestHour"><?= $metrics === null || $metrics['bestHour'] === null ? '' : esc(number_format((int) $metrics['bestHourFamilies']) . ' families') ?></small>
  </div>
  <div class="family-detail-item">
    <span>Share</span>
    <strong data-metric="share"><?= $metrics === null ? '-' : esc(number_format((float) $metrics['share'] * 100, 0) . '%') ?></strong>
    <small data-metric-sub="share">of this batch's families</small>
  </div>
</div>
