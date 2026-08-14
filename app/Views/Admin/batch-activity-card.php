<?php
/**
 * The Activity card: three views of when the batch was busy, at three grains.
 * Hours is the day-by-hour heatmap, Days is the rollout bar chart, and Weekdays
 * is the all-time weekday grid, which is the one view on this pane that ignores
 * the batch selector, so the card says so rather than leaving the reader to
 * wonder why it does not change.
 *
 * The strip is a card-level tab, not a page tab: all three are views of this
 * card's own data, and switching them changes nothing else on the page. Which
 * is why it switches client-side and writes no query parameter.
 *
 * The Days view is also where the busiest day is read off now. It used to be a
 * KPI card of its own, recomputed by the live poll so it could not drift out of
 * agreement with the tallest bar beside it. Putting the reading and the bars in
 * one place removes the disagreement instead of policing it.
 *
 * Params: $heatmap, $weekdayHeatmap, $byDay, $selectedDay, $batchOpen.
 */
$heatmap = $heatmap ?? ['days' => [], 'hours' => [], 'cells' => [], 'max' => 0];
$weekdayHeatmap = $weekdayHeatmap ?? ['days' => [], 'hours' => [], 'cells' => [], 'max' => 0];
$byDay = $byDay ?? [];
$selectedDay = $selectedDay ?? null;

$dayLabels = [];
foreach ($byDay as $index => $day) {
    $dayLabels[$day['date']] = date('M j', strtotime($day['date'])) . ' (Day ' . ($index + 1) . ')';
}

// Row keys are the integer weekday weekdayHistogram() normalises to, 0 for
// Sunday, so the labels are keyed the same way rather than by date.
$weekdayLabels = [0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];
?>
<section class="card batch-card" id="activityCard" data-strip="hours">
  <div class="card-body">
    <h3 class="batch-pane-title">Activity</h3>
    <?= view('components/card_tabs', [
        'tabs' => [
            ['key' => 'hours', 'label' => 'Hours'],
            ['key' => 'days', 'label' => 'Days'],
            ['key' => 'weekdays', 'label' => 'Weekdays'],
        ],
        'active' => 'hours',
    ]) ?>

    <div data-strip-pane="hours">
      <?= view('Admin/batch-heatmap', ['heatmap' => $heatmap, 'selectedDay' => $selectedDay, 'rowLabels' => $dayLabels]) ?>
    </div>

    <div data-strip-pane="days" hidden>
      <?php /* One bar per day the batch ran. A single-day batch gets no chart:
               one bar says nothing the Served card has not already said. Shown
               for closed batches too, because this is retrospective reporting
               rather than the live monitoring the cumulative timeline does. */ ?>
      <?php if (count($byDay) > 1): ?>
      <div class="batch-rollout-chart"><canvas id="chartRollout"></canvas></div>
      <?php else: ?>
      <p class="text-muted mb-0">This batch ran for one day, so there is nothing to compare it against.</p>
      <?php endif; ?>
    </div>

    <div data-strip-pane="weekdays" hidden>
      <p class="text-muted small">Every batch the city has run, not just this one.</p>
      <?= view('Admin/batch-heatmap', [
          'heatmap' => $weekdayHeatmap,
          'selectedDay' => null,
          'rowLabels' => $weekdayLabels,
          'gridId' => 'weekdayHeatmap',
          'selectable' => false,
      ]) ?>
    </div>
  </div>
</section>
