<?php
/**
 * The peak-hours grid: one row per day (or per weekday in the all-time view),
 * one column per hour. A table rather than a Chart.js canvas, because a matrix
 * chart needs the chartjs-chart-matrix plugin, a new vendored dependency for
 * one view, and because a table reads as real rows and columns to a screen
 * reader, keeps every value as text, and takes keyboard focus so a row header
 * can select its day.
 *
 * Three cell states carry the whole reading. A blank hatched cell is one the
 * station was never open for. A lightest-step cell printed "0" is a staffed
 * hour that served nobody, which is the one worth acting on. Anything else is
 * one of five steps scaled to this batch's own busiest cell, not an absolute
 * ceiling, so a small batch still shows contrast.
 *
 * Params: $heatmap (ScannerMetrics::heatmap() shape), $selectedDay ?string,
 *         $rowLabels array<string,string> day key to display label,
 *         $gridId string the table's id, $selectable bool, and $caption string.
 *
 * The last three exist because the Activity card renders this partial twice,
 * once for the batch's days and once for the all-time weekday grid. Two tables
 * cannot share one id; a weekday is not a day the batch ran, so its row headers
 * are plain text rather than buttons offering a filter that has nothing to
 * filter; and the caption is the only description a screen reader gets of what
 * the rows are, so a grid of weekdays must not claim one row per day. All three
 * default to the batch view's values, so the day grid's caller passes none.
 */
$heatmap = $heatmap ?? ['days' => [], 'hours' => [], 'cells' => [], 'max' => 0];
$selectedDay = $selectedDay ?? null;
$rowLabels = $rowLabels ?? [];
$gridId = (string) ($gridId ?? 'peakHeatmap');
$selectable = (bool) ($selectable ?? true);
$caption = (string) ($caption ?? 'Families served by hour, one row per day');

$max = (int) $heatmap['max'];

// Five steps. Integer division against the maximum would put almost every cell
// of a lopsided batch in step one, so the scale is proportional and any
// non-zero count reaches at least step one.
$step = static function (int $families) use ($max): int {
    if ($families <= 0 || $max <= 0) {
        return 0;
    }

    return max(1, (int) ceil($families / $max * 5));
};

$hourLabel = static fn (int $hour): string => date('ga', mktime($hour, 0));
?>
<?php if ($heatmap['days'] === []): ?>
<p class="text-muted mb-0">No scans logged yet, so there are no peak hours to show.</p>
<?php else: ?>
<div class="table-responsive">
  <table class="heatmap" id="<?= esc($gridId, 'attr') ?>">
    <caption class="visually-hidden"><?= esc($caption) ?></caption>
    <thead>
      <tr>
        <th scope="col"><span class="visually-hidden">Day</span></th>
        <?php foreach ($heatmap['hours'] as $hour): ?>
        <th scope="col"><?= esc($hourLabel((int) $hour)) ?></th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($heatmap['days'] as $day): ?>
      <tr<?= $selectedDay === $day ? ' class="is-selected"' : '' ?>>
        <th scope="row">
          <?php if ($selectable): ?>
          <button type="button" class="heatmap-day" data-day="<?= esc($day, 'attr') ?>"
                  aria-pressed="<?= $selectedDay === $day ? 'true' : 'false' ?>">
            <?= esc($rowLabels[$day] ?? $day) ?>
          </button>
          <?php else: ?>
          <?= esc($rowLabels[$day] ?? $day) ?>
          <?php endif; ?>
        </th>
        <?php foreach ($heatmap['hours'] as $hour): ?>
        <?php
          $cell = $heatmap['cells'][$day][$hour] ?? ['families' => 0, 'state' => 'closed'];
          $reading = $cell['state'] === 'closed'
              ? ($rowLabels[$day] ?? $day) . ', ' . $hourLabel((int) $hour) . ', station closed'
              : ($rowLabels[$day] ?? $day) . ', ' . $hourLabel((int) $hour) . ', '
                  . number_format($cell['families']) . ' families';
        ?>
        <td class="heatmap-cell is-<?= esc($cell['state'], 'attr') ?>"
            data-heat="<?= esc((string) $step((int) $cell['families']), 'attr') ?>"
            data-day="<?= esc($day, 'attr') ?>"
            title="<?= esc($reading, 'attr') ?>">
          <span class="visually-hidden"><?= esc($reading) ?></span>
          <span aria-hidden="true"><?= $cell['state'] === 'closed' ? '' : esc(number_format($cell['families'])) ?></span>
        </td>
        <?php endforeach; ?>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<ul class="heatmap-legend" aria-hidden="true">
  <li><span class="heatmap-swatch is-closed"></span>closed</li>
  <li><span class="heatmap-swatch" data-heat="0"></span>0 served</li>
  <li><span class="heatmap-swatch" data-heat="3"></span>busy</li>
  <li><span class="heatmap-swatch" data-heat="5"></span>busiest</li>
</ul>
<?php endif; ?>
