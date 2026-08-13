<?php
/**
 * Distributions log body: page controls + distributions table.
 * Rendered inside components/card by Pages/distribution.php's log tab (vars:
 * distributions, listRoute, keyword, perPage, perPageOptions). The rows are
 * one server-side page from SubsidyDistributionModel::distributionsPage();
 * paging and the database search live in Pages/distribution.php, above this
 * card. Each row shows the subsidy type the batch handed out.
 */
?>
<?php /* Filter bar + controls row: pure Bootstrap grid/utilities inside the
         card-body's own padding (Manage Records standard). */ ?>
        <?= view('components/table_controls', [
            'searchId' => 'distLocalSearch',
            'searchAria' => 'Search shown distributions',
            'searchFormAttrs' => 'data-lookup-search',
            'searchInputAttrs' => 'data-lookup-search-input',
            'sizeId' => 'distPerPage',
            'sizeAction' => site_url('distribution'),
            'sizeHidden' => ['tab' => 'log', 'q' => $keyword],
            'perPage' => $perPage,
            'perPageOptions' => $perPageOptions,
        ]) ?>

        <div class="table-responsive">
          <table class="table manage-record-table align-middle w-100" id="distTable">
            <thead>
              <tr>
                <th>Date</th>
                <th>QR #</th>
                <th>Family Head</th>
                <th>Claimant</th>
                <th>Subsidy Type</th>
                <th>Scanned By</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($distributions as $d): ?>
                <tr>
                  <td><?= esc($d['claim_date']) ?></td>
                  <td><?= esc($d['control_no']) ?></td>
                  <td><span class="sector-name"><?= esc($d['head']) ?></span></td>
                  <td><?= esc($d['claimant']) ?></td>
                  <td><span class="badge bg-light text-dark border"><?= esc((string) $d['subsidy_type']) ?></span></td>
                  <td><?= esc($d['scanned_by']) ?></td>
                  <td class="text-end">
                    <div class="dropdown actions-menu">
                      <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false" aria-label="Distribution actions">
                        <i class="bi bi-three-dots" aria-hidden="true"></i>
                      </button>
                      <div class="dropdown-menu dropdown-menu-end">
                        <form method="post" action="<?= esc(site_url('distribution/void/' . $d['distribution_id']), 'attr') ?>"
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
                <tr><td colspan="7" class="sector-empty-state">No subsidy distributions logged yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
