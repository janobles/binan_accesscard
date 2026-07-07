<?= $this->extend('Scanner/layout') ?>
<?= $this->section('content') ?>

<?php /* Flash banners rendered by Scanner/layout.php; don't duplicate here. */ ?>

<?php /* Tabs sit on top of the panels; each pane is a Sector-Management-style
         .sector-management card so spacing/padding matches the Lookups pages. */ ?>
<ul class="nav nav-tabs manage-tabs mb-0" role="tablist">
  <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-dist" type="button" role="tab">All Distributions</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-types" type="button" role="tab">Aid Types</button></li>
</ul>

<div class="tab-content">
  <!-- All distributions -->
  <div class="tab-pane fade show active" id="tab-dist" role="tabpanel">
    <?= view('components/card', [
        'icon' => 'clipboard-check-fill',
        'title' => 'All Distributions',
        'cardClass' => 'sector-management records-scroll-panel',
        'bodyView' => 'Scanner/manage-distributions-body',
        'bodyData' => ['distributions' => $distributions, 'aidTypes' => $aidTypes],
        'footer' => '<span id="distCount"></span>',
    ]) ?>
  </div>

  <!-- Aid types CRUD -->
  <div class="tab-pane fade" id="tab-types" role="tabpanel">
    <?= view('components/card', [
        'icon' => 'tags-fill',
        'title' => 'Aid Types',
        'cardClass' => 'sector-management records-scroll-panel',
        'bodyView' => 'Scanner/manage-aidtypes-body',
        'bodyData' => ['aidTypes' => $aidTypes],
    ]) ?>
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
