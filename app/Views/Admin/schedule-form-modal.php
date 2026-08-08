<?php
/**
 * Create and edit form for a plotted distribution batch.
 *
 * Opened by schedule-calendar.js, either from a drag across the month or from
 * the New schedule button, and submitted to POST distribution/schedule/save.
 * Subsidy types come from the subsidy reference table; barangay and sector
 * options come from BarangayModel::activeList() and SectorModel::getActive()
 * (DashboardPageBuilder). The count under Covers calls
 * GET distribution/batches/preview, the same query the roster freezes on, so
 * the estimate and the eventual roster cannot disagree in shape.
 *
 * The plan fields sit in a fieldset schedule-calendar.js can disable wholesale
 * for a batch the server's editable flag refuses (started, or its window has
 * begun): the Delete button and the read-only note are the only things left
 * live, since deleteSchedule() judges a different, looser rule than
 * saveSchedule() does.
 *
 * Variables:
 * - $activeSubsidyTypes list of subsidy rows (subsidy_type_id, name)
 * - $barangayOptions    list of barangay rows (barangayID, name)
 * - $sectorOptions      list of sector rows (sectorID, name)
 * - $scheduleColors     list of allowed colour names
 * - $venueSuggestions   list of venues used before
 */
$activeSubsidyTypes = $activeSubsidyTypes ?? [];
$barangayOptions    = $barangayOptions ?? [];
$sectorOptions      = $sectorOptions ?? [];
$scheduleColors     = $scheduleColors ?? [];
$venueSuggestions   = $venueSuggestions ?? [];
?>
<div class="modal fade" id="scheduleFormModal" tabindex="-1" aria-labelledby="scheduleFormTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content" method="post" id="scheduleForm"
          action="<?= esc(site_url('distribution/schedule/save'), 'attr') ?>"
          data-preview-url="<?= esc(site_url('distribution/batches/preview'), 'attr') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="batch_id" id="scheduleBatchId" value="0">
      <div class="modal-header">
        <h5 class="modal-title" id="scheduleFormTitle">New schedule</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small d-none" id="scheduleReadOnlyNote">
          This batch has already started, so its plan cannot be changed. It can still be removed if it has served no families.
        </p>
        <fieldset id="scheduleFields">
        <div class="mb-3">
          <label for="scheduleName" class="form-label">Name</label>
          <input type="text" class="form-control" id="scheduleName" name="name" required maxlength="100">
        </div>
        <div class="mb-3">
          <label for="scheduleVenue" class="form-label">Venue</label>
          <input type="text" class="form-control" id="scheduleVenue" name="venue" maxlength="150" list="venueSuggestions">
          <datalist id="venueSuggestions">
            <?php foreach ($venueSuggestions as $venue): ?>
              <option value="<?= esc($venue, 'attr') ?>"></option>
            <?php endforeach; ?>
          </datalist>
        </div>
        <div class="mb-3">
          <label for="scheduleSubsidyType" class="form-label">Subsidy type</label>
          <select class="form-select" id="scheduleSubsidyType" name="subsidy_type_id" required>
            <option value="" selected disabled>Choose one...</option>
            <?php foreach ($activeSubsidyTypes as $t): ?>
              <option value="<?= (int) $t['subsidy_type_id'] ?>"><?= esc($t['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label for="scheduleStart" class="form-label">Days</label>
          <div class="row g-2">
            <div class="col"><input type="date" class="form-control" id="scheduleStart" name="scheduled_start" required aria-label="First day"></div>
            <div class="col"><input type="date" class="form-control" id="scheduleEnd" name="scheduled_end" required aria-label="Last day"></div>
          </div>
          <div class="form-text">Opens on the first day, closes 30 minutes after the last scan.</div>
        </div>
        <div class="mb-3">
          <label for="scheduleDailyStart" class="form-label">Daily hours</label>
          <div class="row g-2">
            <div class="col"><input type="time" class="form-control" id="scheduleDailyStart" name="daily_start_time" value="08:00" required aria-label="Daily start time"></div>
            <div class="col"><input type="time" class="form-control" id="scheduleDailyEnd" name="daily_end_time" value="17:00" required aria-label="Daily end time"></div>
          </div>
        </div>
        <div class="mb-3">
          <label for="scheduleBarangayIds" class="form-label">Covers</label>
          <div class="row g-2">
            <div class="col-sm-6">
              <select class="form-select" id="scheduleBarangayIds" name="barangay_ids[]" multiple size="4"
                      aria-label="All barangays">
                <?php foreach ($barangayOptions as $b): ?>
                  <option value="<?= (int) $b['barangayID'] ?>"><?= esc($b['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-sm-6">
              <select class="form-select" id="scheduleSectorIds" name="sector_ids[]" multiple size="4"
                      aria-label="All sectors">
                <?php foreach ($sectorOptions as $s): ?>
                  <option value="<?= (int) $s['sectorID'] ?>"><?= esc($s['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <p class="form-text mb-0" id="scheduleEligible">
            <strong data-eligible-count>All families</strong> so far. Locked in when the batch opens.
          </p>
        </div>
        <div class="mb-1">
          <span class="form-label d-block">Label</span>
          <div class="d-flex gap-2" role="radiogroup" aria-label="Label colour">
            <?php foreach ($scheduleColors as $i => $color): ?>
              <button type="button" class="batch-swatch<?= $i === 0 ? ' selected' : '' ?>"
                      style="background: var(--batch-<?= esc($color, 'attr') ?>)"
                      data-color="<?= esc($color, 'attr') ?>" role="radio"
                      aria-checked="<?= $i === 0 ? 'true' : 'false' ?>"
                      aria-label="<?= esc(ucfirst($color), 'attr') ?>"></button>
            <?php endforeach; ?>
          </div>
          <input type="hidden" name="color" id="scheduleColor" value="<?= esc($scheduleColors[0] ?? 'green', 'attr') ?>">
        </div>
        </fieldset>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-danger me-auto d-none" id="scheduleDelete">Delete</button>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="<?= btn('save') ?>" id="scheduleSubmit">Save</button>
      </div>
    </form>
  </div>
</div>
