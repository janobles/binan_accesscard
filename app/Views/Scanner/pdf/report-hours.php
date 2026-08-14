<?php
/**
 * Subsidy Distribution report PDF body, rendered by the admin Reports PDF
 * action. Five sections: the KPI row, coverage by barangay, rollout by day,
 * the peak-hours grid and per-scanner performance. This is a liquidation
 * artifact that gets filed on paper, so it carries what the screen shows
 * rather than the thin summary it used to print.
 *
 * Built by dompdf, so no JavaScript runs and the barangay coverage is drawn
 * as CSS bars rather than a chart. The figures come from the same batch
 * snapshot the screen reads, so the two cannot disagree.
 *
 * Summary figures only. The unclaimed families are a KPI count here, not a
 * roster: printing the names ran the file to a hundred-odd pages behind one
 * page of report. The names stay available on the dashboard's Remaining tab.
 *
 * Params: $coverage, $byBarangay, $byScanner, $heatmap, $byDayRows
 * (ReportsPdfGenerator::dayRows()'s widened byDay rows), $batchName.
 */

use App\Libraries\ViewFormatter;

$byScanner = $byScanner ?? [];
$heatmap   = $heatmap ?? ['days' => [], 'hours' => [], 'cells' => [], 'max' => 0];
$byDayRows = $byDayRows ?? [];

$window = ($batchName ?? null) !== null ? 'Batch: ' . $batchName : 'All batches';

$hourLabel = static fn (int $hour): string => date('ga', mktime($hour, 0));
$hourRange = static fn (int $hour): string => $hourLabel($hour) . ' - ' . $hourLabel($hour + 1);

// Five steps, proportional to this batch's own busiest cell rather than an
// absolute ceiling, so a small batch still shows contrast. Matches the
// screen heatmap's scaling (Admin/batch-heatmap.php) so the two never
// disagree about what "busy" means.
$heatMax = (int) $heatmap['max'];
$heatStep = static function (int $families) use ($heatMax): int {
    if ($families <= 0 || $heatMax <= 0) {
        return 0;
    }

    return max(1, (int) ceil($families / $heatMax * 5));
};
?>
<?= $this->include('Scanner/pdf/_styles') ?>
<h1>Subsidy Distribution Report</h1>
<p class="sub">City of Bi&ntilde;an CSWD &middot; <?= esc($window) ?> &middot; Generated <?= esc(date('Y-m-d H:i')) ?></p>

<table class="kpis" style="width:100%; border-collapse:collapse;">
  <tr>
    <td>Eligible<br><span class="n"><?= esc((string) $coverage['eligible']) ?></span></td>
    <td>Served<br><span class="n"><?= esc((string) $coverage['served']) ?></span></td>
    <td>Remaining<br><span class="n"><?= esc((string) $coverage['remaining']) ?></span></td>
    <td>Coverage<br><span class="n"><?= esc((string) $coverage['coverage']) ?>%</span></td>
  </tr>
</table>

<h2>Coverage by barangay</h2>
<table class="data">
  <colgroup><col style="width:35%"><col style="width:15%"><col style="width:15%"><col style="width:35%"></colgroup>
  <thead><tr><th>Barangay</th><th>Families</th><th>Received</th><th>Coverage</th></tr></thead>
  <tbody>
  <?php foreach ($byBarangay as $b): ?>
    <tr>
      <td><?= esc($b['barangay']) ?></td>
      <td><?= esc((string) $b['total']) ?></td>
      <td><?= esc((string) $b['received']) ?></td>
      <td><span class="bar" style="width: <?= (int) $b['coverage'] ?>px;"></span> <?= esc((string) $b['coverage']) ?>%</td>
    </tr>
  <?php endforeach; ?>
  <?php if ($byBarangay === []): ?>
    <tr><td colspan="4">No data for this range.</td></tr>
  <?php endif; ?>
  </tbody>
</table>

<h2>Rollout by day</h2>
<table class="data">
  <colgroup><col style="width:35%"><col style="width:25%"><col style="width:20%"><col style="width:20%"></colgroup>
  <thead><tr><th>Day</th><th>Families served</th><th>Peak hour</th><th>Scanners active</th></tr></thead>
  <tbody>
  <?php foreach ($byDayRows as $d): ?>
    <tr>
      <td><?= esc($d['label']) ?> (<?= esc($d['date']) ?>)</td>
      <td><?= esc(number_format((int) $d['served'])) ?></td>
      <td><?= $d['peakHour'] === null ? '-' : esc($hourRange((int) $d['peakHour'])) . ' &middot; ' . esc(number_format((int) $d['peakFamilies'])) . ' families' ?></td>
      <td><?= esc(number_format((int) $d['scannersActive'])) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if ($byDayRows === []): ?>
    <tr><td colspan="4">No scans logged yet.</td></tr>
  <?php endif; ?>
  </tbody>
</table>
<p class="note">Scanners active is a batch-wide count, not sliced per day: the underlying figures carry no day dimension to slice on.</p>

<h2>Peak hours</h2>
<?php if ($heatmap['days'] === []): ?>
<p class="note">No scans logged yet, so there are no peak hours to show.</p>
<?php else: ?>
<table class="heat">
  <thead>
    <tr>
      <th class="rowhead">Day</th>
      <?php foreach ($heatmap['hours'] as $hour): ?>
      <th><?= esc($hourLabel((int) $hour)) ?></th>
      <?php endforeach; ?>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($heatmap['days'] as $day): ?>
    <tr>
      <td class="rowhead"><?= esc($day) ?></td>
      <?php foreach ($heatmap['hours'] as $hour): ?>
      <?php
        $cell = $heatmap['cells'][$day][$hour] ?? ['families' => 0, 'state' => 'closed'];
      ?>
      <?php if ($cell['state'] === 'closed'): ?>
      <td class="heat-closed">closed</td>
      <?php else: ?>
      <td class="heat-<?= (int) $heatStep((int) $cell['families']) ?>"><?= esc(number_format((int) $cell['families'])) ?></td>
      <?php endif; ?>
      <?php endforeach; ?>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<ul class="legend">
  <li><span class="swatch heat-closed"></span>closed</li>
  <li><span class="swatch heat-0"></span>0 served</li>
  <li><span class="swatch heat-3"></span>busy</li>
  <li><span class="swatch heat-5"></span>busiest</li>
</ul>
<?php endif; ?>

<h2>Scanner performance</h2>
<p class="note">Pace is a cadence figure, non-idle gaps per active hour, not families over the whole day: a station idle all afternoon after a busy morning still reads at the morning's pace.</p>
<?php if ($byScanner !== []): ?>
<table class="data wide">
  <colgroup>
    <col style="width:16%"><col style="width:9%"><col style="width:9%"><col style="width:9%">
    <col style="width:9%"><col style="width:16%"><col style="width:9%"><col style="width:14%"><col style="width:9%">
  </colgroup>
  <thead>
    <tr>
      <th>Scanner</th><th>Families</th><th>Handouts</th><th>Pace / h</th><th>Typical</th>
      <th>On station</th><th>Idle</th><th>Best hour</th><th>Share</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($byScanner as $row): ?>
    <tr>
      <td><?= esc($row['scanner']) ?></td>
      <td><?= esc(number_format((int) $row['families'])) ?></td>
      <td><?= esc(number_format((int) $row['handouts'])) ?></td>
      <td><?= $row['pace'] === null ? '-' : esc(number_format((float) $row['pace'], 0)) ?></td>
      <td><?= $row['typicalSeconds'] === null ? '-' : esc(ViewFormatter::duration((int) $row['typicalSeconds'])) ?></td>
      <td><?= $row['firstTs'] === null ? '-' : esc(date('g:ia', (int) $row['firstTs']) . ' - ' . date('g:ia', (int) $row['lastTs'])) ?></td>
      <td><?= esc(ViewFormatter::duration((int) $row['idleSeconds'])) ?></td>
      <td><?= $row['bestHour'] === null ? '-' : esc($hourRange((int) $row['bestHour'])) ?></td>
      <td><?= esc(number_format((float) $row['share'] * 100, 0)) ?>%</td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
