<?php
/**
 * Distributions log body: client-side filter toolbar + distributions table.
 * Ported from Scanner/manage-distributions-body.php for the admin
 * distribution hub (void action moved from scanner/distributions/void/*
 * to admin/distributions/void/*). Rendered inside components/card by
 * Admin/distribution.php (vars: distributions, aidTypes). Filtering/paging
 * handled by the inline script in Admin/distribution.php.
 */
?>
<div class="records-search-panel">
          <div class="records-search-row records-lookup-search">
            <input class="form-control" type="search" id="distSearch" placeholder="Search the distributions log" aria-label="Search the distributions log" autocomplete="off">
            <select class="form-select records-status-select" id="distAidFilter" aria-label="Filter by aid type">
              <option value="">All aid types</option>
              <?php foreach ($aidTypes as $t): ?>
                <option value="<?= esc($t['name'], 'attr') ?>"><?= esc($t['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn btn-outline-secondary records-search-action" type="button" id="distClear"><i class="bi bi-x-lg" aria-hidden="true"></i><span>Clear</span></button>
          </div>
        </div>

        <div class="table-meta">
          <div class="records-table-controls">
            <div class="records-page-size-form">
              <label for="distPerPage">Show</label>
              <select class="form-select form-select-sm" id="distPerPage">
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50" selected>50</option>
                <option value="100">100</option>
                <option value="0">All</option>
              </select>
              <span>entries</span>
            </div>
            <div class="records-table-search-form">
              <label for="distLocalSearch">Search:</label>
              <input class="form-control form-control-sm" type="search" id="distLocalSearch" placeholder="Type to filter..." autocomplete="off">
            </div>
          </div>
        </div>

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
                <tr data-aidtype="<?= esc($d['aid_type'], 'attr') ?>">
                  <td><?= esc($d['claim_date']) ?></td>
                  <td><span class="badge bg-light text-dark border"><?= esc($d['control_no']) ?></span></td>
                  <td><span class="sector-name"><?= esc($d['head']) ?></span></td>
                  <td><?= esc($d['claimant']) ?></td>
                  <td><span class="badge bg-light text-dark border"><?= esc($d['aid_type']) ?></span></td>
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
