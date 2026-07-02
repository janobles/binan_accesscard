<?= $this->extend('Scanner/layout') ?>
<?= $this->section('content') ?>

<?php if (session('success')): ?>
  <div class="alert alert-success"><?= esc(session('success')) ?></div>
<?php elseif (session('error')): ?>
  <div class="alert alert-danger"><?= esc(session('error')) ?></div>
<?php endif; ?>

<?php /* Tabs sit on top of the panels; each pane is a Sector-Management-style
         .sector-management card so spacing/padding matches the Lookups pages. */ ?>
<ul class="nav nav-tabs manage-tabs mb-0" role="tablist">
  <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-dist" type="button" role="tab">All Distributions</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-types" type="button" role="tab">Aid Types</button></li>
</ul>

<div class="tab-content">
  <!-- All distributions -->
  <div class="tab-pane fade show active" id="tab-dist" role="tabpanel">
    <div class="sector-management records-scroll-panel">
        <?php /* Same two-band layout as the Lookups pages (Sector/Service/Category). */ ?>
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
          <table class="table table-sm manage-record-table align-middle w-100" id="distTable">
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
                        <form method="post" action="<?= site_url('scanner/distributions/void/' . $d['aidID']) ?>"
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

        <div class="lookup-list-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
          <span class="text-muted small" id="distCount"></span>
        </div>
    </div>
  </div>

  <!-- Aid types CRUD -->
  <div class="tab-pane fade" id="tab-types" role="tabpanel">
    <div class="sector-management records-scroll-panel">
        <div class="records-search-panel">
          <div class="records-search-row justify-content-end">
            <button class="btn btn-primary records-search-action" type="button" data-bs-toggle="modal" data-bs-target="#addAidTypeModal"><i class="bi bi-plus-lg" aria-hidden="true"></i><span>Add Aid Type</span></button>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-sm manage-record-table align-middle w-100">
            <thead>
              <tr><th>Name</th><th>Status</th><th class="text-end">Actions</th></tr>
            </thead>
            <tbody>
              <?php foreach ($aidTypes as $t): ?>
                <?php $archived = ! empty($t['dt_deleted']); ?>
                <tr data-row-archived="<?= $archived ? '1' : '0' ?>">
                  <td><span class="sector-name"><?= esc($t['name']) ?></span></td>
                  <td><span class="sector-status-badge <?= $archived ? 'sector-status-archived' : 'sector-status-active' ?>"><?= $archived ? 'Archived' : 'Active' ?></span></td>
                  <td class="text-end">
                    <div class="dropdown actions-menu">
                      <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false" aria-label="Aid type actions">
                        <i class="bi bi-three-dots" aria-hidden="true"></i>
                      </button>
                      <div class="dropdown-menu dropdown-menu-end">
                        <?php if ($archived): ?>
                          <form method="post" action="<?= site_url('scanner/aid-types/restore/' . $t['aid_type_id']) ?>">
                            <?= csrf_field() ?>
                            <button class="dropdown-item text-success" type="submit"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>Restore</button>
                          </form>
                          <form method="post" action="<?= site_url('scanner/aid-types/delete/' . $t['aid_type_id']) ?>"
                                onsubmit="return confirm('Delete permanently? Only allowed if never used.');">
                            <?= csrf_field() ?>
                            <button class="dropdown-item text-danger" type="submit"><i class="bi bi-trash" aria-hidden="true"></i>Delete</button>
                          </form>
                        <?php else: ?>
                          <form method="post" action="<?= site_url('scanner/aid-types/archive/' . $t['aid_type_id']) ?>">
                            <?= csrf_field() ?>
                            <button class="dropdown-item" type="submit"><i class="bi bi-archive" aria-hidden="true"></i>Archive</button>
                          </form>
                        <?php endif; ?>
                      </div>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if ($aidTypes === []): ?>
                <tr><td colspan="3" class="sector-empty-state">No aid types defined.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
    </div>
  </div>
</div>

<!-- Add aid type modal -->
<div class="modal fade" id="addAidTypeModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="<?= site_url('scanner/aid-types/create') ?>">
      <?= csrf_field() ?>
      <div class="modal-header">
        <h5 class="modal-title">Add Aid Type</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <label for="aidTypeName" class="form-label">Name</label>
        <input type="text" class="form-control" id="aidTypeName" name="name" required maxlength="100">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Add</button>
      </div>
    </form>
  </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
  // Client-side filtering to mirror the Lookups pages (search + aid-type + local filter + page size).
  const table   = document.getElementById('distTable');
  const search  = document.getElementById('distSearch');
  const filter  = document.getElementById('distAidFilter');
  const clear   = document.getElementById('distClear');
  const perPage = document.getElementById('distPerPage');
  const local   = document.getElementById('distLocalSearch');
  const count   = document.getElementById('distCount');
  if (!table) return;
  const rows = Array.from(table.tBodies[0].rows).filter(r => !r.querySelector('.sector-empty-state'));

  const render = () => {
    const q     = (search.value || '').trim().toLowerCase();
    const q2    = (local.value || '').trim().toLowerCase();
    const aid   = filter.value || '';
    const limit = parseInt(perPage.value, 10) || 0; // 0 = all
    let matched = 0;
    let shown = 0;
    rows.forEach(r => {
      const text = r.textContent.toLowerCase();
      const ok = (q === '' || text.includes(q))
              && (q2 === '' || text.includes(q2))
              && (aid === '' || (r.getAttribute('data-aidtype') || '') === aid);
      let visible = ok;
      if (ok) {
        matched++;
        if (limit > 0 && matched > limit) visible = false;
      }
      r.hidden = !visible;
      if (visible) shown++;
    });
    if (count) count.textContent = 'Showing ' + shown + ' of ' + rows.length + ' distribution' + (rows.length === 1 ? '' : 's');
  };

  [search, filter, local, perPage].forEach(el => el && el.addEventListener('input', render));
  if (perPage) perPage.addEventListener('change', render);
  if (clear) clear.addEventListener('click', () => { search.value = ''; local.value = ''; filter.value = ''; perPage.value = '50'; render(); });
  render();
});
</script>
<?= $this->endSection() ?>
