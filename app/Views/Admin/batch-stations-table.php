<?php
/**
 * Per-scanner performance for the batch, one row each and a TOTAL row folded by
 * the same code. Replaces the squares grid: eight metrics do not fit in a
 * square, and the squares only ever carried a name and a count.
 *
 * A row opens the station modal for the roles that may read scanner/stats, the
 * endpoint that answers Scanner, Admin and Developer only. For everyone else
 * the row is inert, matching what the server would have rendered, so the live
 * poll cannot hand a role a control the page withheld. The modal opens rather
 * than the station's own page, which lives in the kiosk shell: an admin reading
 * the batch wants one station's figures, not to be dropped into the scanner's
 * chrome and have to navigate back.
 *
 * Rows are named by the account username because that is already the
 * operational identity: the accounts are named Scanner1 through Scanner20, so
 * inventing a separate kiosk numbering would add a second name for one thing.
 *
 * Pace is a cadence figure, non-idle gaps per active hour, which is why it is
 * qualified "while active" rather than labelled families per hour. A station
 * that served forty families in a morning and then stood idle all afternoon has
 * the pace of the morning, not the average of the day.
 *
 * The table always renders, even with zero stations: the live poll rebuilds
 * #stationsTable's body in place, and an open batch's first station needs
 * somewhere to land without a page reload.
 *
 * Params: $byScanner (SubsidyStatsModel::batchSnapshot()['byScanner']),
 *         $batchId int, $canDrillIn bool.
 */

use App\Libraries\ViewFormatter;

$byScanner = $byScanner ?? [];
$batchId = (int) ($batchId ?? 0);
$canDrillIn = (bool) ($canDrillIn ?? false);

$hourRange = static fn (int $hour): string => date('ga', mktime($hour, 0)) . ' - ' . date('ga', mktime($hour + 1, 0));
?>
<div class="table-responsive">
  <table class="table manage-record-table align-middle w-100 mb-0" id="stationsTable"
         data-batch="<?= esc((string) $batchId, 'attr') ?>"
         data-can-drill-in="<?= $canDrillIn ? '1' : '0' ?>">
    <thead>
      <tr>
        <th scope="col">Scanner</th>
        <th scope="col">Families</th>
        <th scope="col">Handouts</th>
        <th scope="col">Pace <span class="text-muted fw-normal">while active</span></th>
        <th scope="col">Typical</th>
        <th scope="col">On station</th>
        <th scope="col">Idle</th>
        <th scope="col">Best hour</th>
        <th scope="col">Share</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($byScanner as $row): ?>
      <tr<?= (int) $row['userID'] === 0 ? ' class="is-total"' : '' ?><?php
        if ($canDrillIn && (int) $row['userID'] > 0) {
            echo ' data-scanner-id="' . esc((string) (int) $row['userID'], 'attr') . '"'
                . ' data-scanner-name="' . esc($row['scanner'], 'attr') . '"';
        }
      ?>>
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
      <?php if ($byScanner === []): ?>
      <tr id="stationsTableEmpty"><td colspan="9" class="text-muted">No station has logged a scan in this batch yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
