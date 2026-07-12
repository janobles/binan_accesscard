<?php
/**
 * New Batch modal: name + category -> service pick. Services are embedded
 * as JSON and the service select is filtered client-side by the chosen
 * category (services.category stores the category NAME, not an id).
 *
 * Variables:
 * - $activeCategories list of category rows (code, name)
 * - $activeServices   list of service rows (serviceID, shortcode, category, name)
 */
$activeCategories = $activeCategories ?? [];
$activeServices   = $activeServices ?? [];
?>
<div class="modal fade" id="newBatchModal" tabindex="-1" aria-labelledby="newBatchModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="<?= site_url('admin/batches/open') ?>">
      <?= csrf_field() ?>
      <div class="modal-header">
        <h5 class="modal-title" id="newBatchModalLabel">New Batch</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label for="batchName" class="form-label">Batch name</label>
          <input type="text" class="form-control" id="batchName" name="name" required maxlength="100"
                 placeholder="e.g. Relief Distribution — <?= esc(date('M j, Y')) ?>">
        </div>
        <div class="mb-3">
          <label for="batchCategory" class="form-label">Category</label>
          <select class="form-select" id="batchCategory" required>
            <option value="" selected disabled>Choose a category...</option>
            <?php foreach ($activeCategories as $c): ?>
              <option value="<?= esc($c['name'], 'attr') ?>"><?= esc($c['code']) ?> — <?= esc($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label for="batchService" class="form-label">Service / program</label>
          <select class="form-select" id="batchService" name="service_id" required disabled>
            <option value="" selected disabled>Choose a category first...</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="<?= btn('add') ?>">Open Batch</button>
      </div>
    </form>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const services = <?= json_encode(array_map(static fn (array $s) => [
      'id'       => (int) $s['serviceID'],
      'code'     => (string) ($s['shortcode'] ?? ''),
      'name'     => (string) ($s['name'] ?? ''),
      'category' => (string) ($s['category'] ?? ''),
  ], $activeServices), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  const catSel = document.getElementById('batchCategory');
  const svcSel = document.getElementById('batchService');
  if (!catSel || !svcSel) return;
  catSel.addEventListener('change', () => {
    svcSel.innerHTML = '<option value="" selected disabled>Choose a service...</option>';
    services.filter(s => s.category === catSel.value).forEach(s => {
      const o = document.createElement('option');
      o.value = s.id;
      o.textContent = s.code ? s.code + ' — ' + s.name : s.name;
      svcSel.appendChild(o);
    });
    svcSel.disabled = false;
  });
});
</script>
