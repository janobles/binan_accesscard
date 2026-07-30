<?php
/**
 * Family Information tab table body: head + rest of the family, one row
 * per person. Rendered inside the family card by Scanner/history-fragment.php
 * (bodyData is that view's get_defined_vars()), which also builds $rows. No
 * search, filters, per-page control, or footer/pagination - families are
 * small enough to show in full.
 */
$rows = (array) ($rows ?? []);
?>
<?php /* Grows with row count up to 60vh, then scrolls internally instead of
         pushing the rest of the panel (and the page) taller without bound. */ ?>
<div class="table-responsive" style="max-height: 60vh; overflow-y: auto;">
  <table class="table manage-record-table align-middle mb-0" id="familyInfoTable">
    <thead>
      <tr>
        <th>Relationship</th>
        <th>Full Name</th>
        <th>Suffix</th>
        <th>Date of Birth</th>
        <th>Sex</th>
        <th>Civil Status</th>
        <th>Contact Number</th>
        <th>Job</th>
        <th>Address</th>
        <th>Sectors</th>
        <th>Services and Programs</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= esc($r['relationship']) ?></td>
          <td class="fw-bold"><?= esc($r['fullName']) ?></td>
          <td><?= esc($r['suffix']) ?></td>
          <td><?= esc($r['birthday']) ?></td>
          <td><?= esc($r['sex']) ?></td>
          <td><?= esc($r['civilstatus']) ?></td>
          <td><?= esc($r['contact']) ?></td>
          <td><?= esc($r['job']) ?></td>
          <td><?= esc($r['address']) ?></td>
          <td><?php foreach ($r['sectors'] as $s): ?><span class="badge bg-light text-dark border me-1"><?= esc($s) ?></span><?php endforeach; ?></td>
          <td><?php foreach ($r['servicesPrograms'] as $s): ?><span class="badge bg-light text-dark border me-1"><?= esc($s) ?></span><?php endforeach; ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
