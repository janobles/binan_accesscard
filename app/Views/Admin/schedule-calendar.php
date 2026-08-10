<?php
/**
 * Schedule tab body: the month calendar of plotted distribution batches.
 *
 * The grid itself is drawn by FullCalendar, started by
 * public/assets/js/dashboard/schedule-calendar.js against the data-* URLs
 * below. Events come from GET distribution/schedule/feed. Admin and Developer
 * can drag to plot; a Viewer gets the same calendar with no write affordances.
 *
 * Data source: DashboardPageBuilder::buildViewData().
 */
$canManageBatches = in_array($currentRole ?? '', ['Admin', 'Developer'], true);
?>
<div class="d-flex justify-content-end align-items-center flex-wrap gap-2 mb-3">
    <?php if ($canManageBatches): ?>
        <button type="button" class="<?= btn('add') ?>" id="newScheduleBtn">New schedule</button>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body">
        <div id="scheduleCalendar"
             data-feed-url="<?= esc(site_url('distribution/schedule/feed'), 'attr') ?>"
             data-save-url="<?= esc(site_url('distribution/schedule/save'), 'attr') ?>"
             data-delete-url="<?= esc(site_url('distribution/schedule'), 'attr') ?>"
             data-can-manage="<?= $canManageBatches ? '1' : '0' ?>"></div>
    </div>
</div>
