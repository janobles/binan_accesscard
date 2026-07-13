<?php
/**
 * Distributions log body: client-side filter toolbar + distributions table.
 * Rendered inside components/card by Admin/layout.php's distributions block
 * (vars: distributions). Each row shows the aid type the batch handed out.
 * Filtering/paging handled by the inline script in
 * Admin/layout.php's distributions block.
 */
?>
<?php /* Filter bar + controls row: pure Bootstrap grid/utilities inside the
         card-body's own padding (Manage Records standard). */ ?>
<div class="row g-2 align-items-center mb-3">
          <div class="col-12 col-lg">
            <input class="form-control" type="search" id="distSearch" placeholder="Search the distributions log" aria-label="Search the distributions log" autocomplete="off">
          </div>
          <div class="col-12 col-lg-auto">
            <button class="<?= btn('clear') ?> w-100" type="button" id="distClear">Clear</button>
          </div>
        </div>

        <?= view('components/table_controls', [
            'searchId' => 'distLocalSearch',
            'searchAria' => 'Search shown distributions',
            'searchFormAttrs' => 'onsubmit="return false;"',
            'searchButtonAttrs' => 'onclick="document.getElementById(\'distLocalSearch\').dispatchEvent(new Event(\'input\'))"',
            'sizeId' => 'distPerPage',
            'sizeAction' => null,
            'perPage' => 25,
            'perPageOptions' => [10 => '10', 25 => '25', 50 => '50', 100 => '100', 0 => 'All'],
        ]) ?>

        <div class="table-responsive">
          <table class="table manage-record-table align-middle w-100" id="distTable">
            <thead>
              <tr>
                <th>Date</th>
                <th>QR #</th>
                <th>Family Head</th>
                <th>Claimant</th>
                <th>Aid Type</th>
                <th>Scanned By</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($distributions as $d): ?>
                <tr>
                  <td><?= esc($d['claim_date']) ?></td>
                  <td><span class="badge bg-light text-dark border"><?= esc($d['control_no']) ?></span></td>
                  <td><span class="sector-name"><?= esc($d['head']) ?></span></td>
                  <td><?= esc($d['claimant']) ?></td>
                  <td><span class="badge bg-light text-dark border"><?= esc((string) $d['aid_type']) ?></span></td>
                  <td><?= esc($d['scanned_by']) ?></td>
                  <td class="text-end">
                    <div class="dropdown actions-menu">
                      <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false" aria-label="Distribution actions">
                        <i class="bi bi-three-dots" aria-hidden="true"></i>
                      </button>
                      <div class="dropdown-menu dropdown-menu-end">
                        <form method="post" action="<?= esc(site_url('admin/distributions/void/' . $d['aidID']), 'attr') ?>"
                              onsubmit="return confirm('Void this distribution? This permanently removes the record.');">
                          <?= csrf_field() ?>
                          <button class="dropdown-item text-danger" type="submit"><i class="bi bi-x-circle" aria-hidden="true"></i>Void</button>
                        </form>
                      </div>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if ($distributions === []): ?>
                <tr><td colspan="7" class="sector-empty-state">No aid distributions logged yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
