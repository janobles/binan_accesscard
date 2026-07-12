<?php
/**
 * Applied-filter pill row. The container is server-rendered; the pills
 * themselves are rendered by JS from the checked filter inputs (see
 * renderFilterPills() in assets/js/dashboard/family-datatable.js), which keeps
 * one renderer instead of a PHP copy and a JS copy.
 *
 * Pill markup contract (JS must produce exactly this shape):
 *   <span class="badge text-bg-light border d-inline-flex align-items-center gap-1">
 *     Sector: IP - Indigenous People
 *     <button type="button" class="btn-close" aria-label="Remove filter ..."></button>
 *   </span>
 *
 * Variables:
 * - $id string container id, required by the consuming page's JS
 */
$id = (string) ($id ?? 'filterPills');
?>
<div class="d-flex flex-wrap align-items-center gap-1 mb-2" id="<?= esc($id, 'attr') ?>" aria-live="polite" aria-label="Applied filters"></div>
