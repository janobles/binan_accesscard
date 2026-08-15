<?php
/**
 * One station's figures for the selected batch, shown over the dashboard's
 * Stations table instead of navigating into the kiosk shell.
 *
 * Empty on render. Rendered only for the roles that may read it (see
 * Admin/batch-stations-table.php), and filled by
 * assets/js/dashboard/station-modal.js from scanner/stats, which already
 * accepts ?scanner= and ?batch= and already restricts the ?scanner= override to
 * Admin and Developer.
 */
?>
<div class="modal fade" id="stationModal" tabindex="-1" aria-labelledby="stationModalLabel" aria-hidden="true"
     data-stats-url="<?= esc(site_url('scanner/stats'), 'attr') ?>">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="stationModalLabel">Station</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?php /* aria-live so the numbers arriving after the fetch are announced;
                 the modal opens before they land. Rendered empty (metrics null)
                 and filled in place by station-modal.js, which writes into the
                 data-metric spans this partial renders, not by re-rendering the
                 partial itself. */ ?>
        <div id="stationModalBody">
          <?= $this->include('Scanner/_metrics-grid', ['metrics' => null]) ?>
        </div>
        <p class="text-danger mb-0 mt-3 d-none" id="stationModalError">Could not load this station's figures.</p>
      </div>
    </div>
  </div>
</div>
