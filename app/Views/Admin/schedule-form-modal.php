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
 * Covers is two closed one-line dropdowns of checkboxes rather than tall
 * native multi-selects, so the form reads as six short fields the way the
 * approved mockup draws it. The toggle carries the summary text.
 *
 * The dialog holds two panes and shows one at a time. A save that collides
 * with another batch swaps #scheduleFormPane for #scheduleConflictPane, which
 * asks whether to delete the batch in the way; Back returns to the filled-in
 * form. Bootstrap does not support two open modals, so the confirmation is a
 * second state of this one rather than a dialog of its own.
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
<div class="modal fade" id="scheduleFormModal" tabindex="-1" aria-labelledby="scheduleFormTitle" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <form class="modal-content" method="post" id="scheduleForm"
          action="<?= esc(site_url('distribution/schedule/save'), 'attr') ?>"
          data-preview-url="<?= esc(site_url('distribution/batches/preview'), 'attr') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="batch_id" id="scheduleBatchId" value="0">
      <style>
        .contents-pane { display: contents; }
        .contents-pane.d-none { display: none !important; }
      </style>
      <div id="scheduleFormPane" class="contents-pane">
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
              <div class="dropdown">
                <button class="form-select text-start" type="button" id="scheduleBarangayToggle"
                        data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                  All barangays
                </button>
                <div class="dropdown-menu p-2 schedule-covers-menu" aria-labelledby="scheduleBarangayToggle">
                  <?php foreach ($barangayOptions as $b): ?>
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" name="barangay_ids[]"
                             value="<?= (int) $b['barangayID'] ?>" id="scheduleBarangay<?= (int) $b['barangayID'] ?>">
                      <label class="form-check-label" for="scheduleBarangay<?= (int) $b['barangayID'] ?>">
                        <?= esc($b['name']) ?>
                      </label>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
            <div class="col-sm-6">
              <div class="dropdown">
                <button class="form-select text-start" type="button" id="scheduleSectorToggle"
                        data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                  All sectors
                </button>
                <div class="dropdown-menu p-2 schedule-covers-menu" aria-labelledby="scheduleSectorToggle">
                  <?php foreach ($sectorOptions as $s): ?>
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" name="sector_ids[]"
                             value="<?= (int) $s['sectorID'] ?>" id="scheduleSector<?= (int) $s['sectorID'] ?>">
                      <label class="form-check-label" for="scheduleSector<?= (int) $s['sectorID'] ?>">
                        <?= esc($s['name']) ?>
                      </label>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          </div>
          <p class="form-text mb-0 text-success" id="scheduleEligible">
            <i class="bi bi-check-circle-fill me-1"></i>
            <strong data-eligible-count>All families eligible</strong>
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
        <button type="submit" class="<?= btn('add') ?>" id="scheduleSubmit">Save</button>
      </div>
      </div>

      <div id="scheduleConflictPane" class="contents-pane d-none">
        <div class="modal-header">
          <h5 class="modal-title" id="scheduleConflictTitle">Replace the existing schedule?</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="mb-0" data-conflict-message></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" id="scheduleConflictBack">Back</button>
          <button type="button" class="btn btn-danger" id="scheduleConflictConfirm">Delete and replace</button>
        </div>
      </div>
    </form>
  </div>
</div>
