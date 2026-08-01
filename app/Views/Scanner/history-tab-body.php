<?php
/**
 * Audit Logs tab table body: rows only, no card wrapper, per-page control,
 * or footer. Rendered directly inside the tab-pane by
 * Scanner/history-fragment.php (bodyData is that view's get_defined_vars()).
 * Read-only - no per-row actions.
 */
$historyRows = (array) ($historyRows ?? []);
?>
<?php /* Grows with row count up to 60vh, then scrolls internally instead of
         pushing the rest of the panel (and the page) taller without bound. */ ?>
<div class="table-responsive" style="max-height: 60vh; overflow-y: auto;">
  <table class="table manage-record-table align-middle w-100">
    <thead>
      <tr>
        <th>Date &amp; Time</th>
        <th>Batch</th>
        <th>Subsidy Type</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($historyRows as $r): ?>
        <tr>
          <td><?= esc((string) $r['dt_created']) ?></td>
          <td><?= esc((string) ($r['batch_name'] ?? '-')) ?></td>
          <td><span class="badge bg-light text-dark border"><?= esc((string) $r['subsidy_type']) ?></span></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($historyRows === []): ?>
        <tr><td colspan="3" class="sector-empty-state">No subsidy received yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
