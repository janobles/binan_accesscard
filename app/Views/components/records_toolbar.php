<?php
/**
 * Manage-records toolbar: one database-wide keyword search, one multi-column
 * filter panel (live-apply, no Apply/Reset buttons by design; see the
 * 2026-07-12 manage-records UI spec), and two button groups split by meaning:
 * search actions (Search, Clear) and record actions (Add, Import).
 *
 * Props-only component. Behavior lives in assets/js/dashboard/family-datatable.js,
 * wired to the data-records-* hooks below. Button classes come from btn()
 * (app/Helpers/ui_helper.php).
 *
 * Variables (all defaulted defensively):
 * - $routeBase         string   role route base, e.g. 'admin/manage-family'
 * - $keyword           string   current database keyword
 * - $status            string   'all' | 'active' | 'archived'
 * - $sectorOptions     array    sector rows (sectorID + shortcode/sector_name)
 * - $barangayOptions   array    barangay name strings
 * - $selectedSectorIds string[] checked sector ids
 * - $selectedBarangays string[] checked barangay names
 * - $sectorOptionLabel callable array -> display label
 * - $canEdit           bool     shows Add/Import when true
 */
$routeBase = (string) ($routeBase ?? 'admin/manage-family');
$keyword = trim((string) ($keyword ?? ''));
$status = in_array((string) ($status ?? 'all'), ['all', 'active', 'archived'], true) ? (string) $status : 'all';
$sectorOptions = (array) ($sectorOptions ?? []);
$barangayOptions = (array) ($barangayOptions ?? []);
$selectedSectorIds = array_map('strval', (array) ($selectedSectorIds ?? []));
$selectedBarangays = array_map('strval', (array) ($selectedBarangays ?? []));
$sectorOptionLabel = $sectorOptionLabel ?? static fn (array $sector): string => (string) ($sector['sector_name'] ?? '');
$canEdit = (bool) ($canEdit ?? true);
?>
<form class="row g-2 align-items-center mb-2" id="familyDataTableFilters" aria-label="Family records search and filters">
    <div class="col-12 col-lg">
        <input
            class="form-control"
            type="search"
            name="q"
            value="<?= esc($keyword, 'attr') ?>"
            aria-label="Search entire database"
            placeholder="Search entire database (incl. members)..."
            autocomplete="off"
            data-records-database-keyword
        >
    </div>

    <div class="col-auto">
        <div class="dropdown" data-records-panel>
            <button class="<?= btn('filter') ?> dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                <i class="bi bi-funnel" aria-hidden="true"></i> Filters
            </button>
            <div class="dropdown-menu dropdown-menu-end records-filter-panel p-3">
                <div class="row g-3">
                    <div class="col-12 col-md-4" data-records-filter="sector">
                        <div class="fw-semibold small text-uppercase text-muted mb-1">Sector</div>
                        <div class="records-filter-list overflow-auto">
                            <?php foreach ($sectorOptions as $sector): ?>
                                <?php
                                $sectorId = (string) ($sector['sectorID'] ?? $sector['id'] ?? '');
                                $sectorName = $sectorOptionLabel((array) $sector);
                                ?>
                                <?php if ($sectorId !== '' && $sectorName !== ''): ?>
                                    <label class="form-check d-flex align-items-center gap-2 py-1" data-records-option>
                                        <input class="form-check-input m-0" type="checkbox" name="sectorID[]" value="<?= esc($sectorId, 'attr') ?>" data-records-pill-label="<?= esc($sectorName, 'attr') ?>" <?= in_array($sectorId, $selectedSectorIds, true) ? 'checked' : '' ?>>
                                        <span class="form-check-label text-wrap small"><?= esc($sectorName) ?></span>
                                    </label>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="col-12 col-md-4" data-records-filter="barangay">
                        <div class="fw-semibold small text-uppercase text-muted mb-1">Barangay</div>
                        <input class="form-control form-control-sm mb-1" type="search" placeholder="Type to narrow list..." aria-label="Narrow barangay list" data-records-narrow>
                        <div class="records-filter-list overflow-auto">
                            <?php foreach ($barangayOptions as $barangay): ?>
                                <?php $barangayName = trim((string) $barangay); ?>
                                <?php if ($barangayName !== ''): ?>
                                    <label class="form-check d-flex align-items-center gap-2 py-1" data-records-option>
                                        <input class="form-check-input m-0" type="checkbox" name="barangay[]" value="<?= esc($barangayName, 'attr') ?>" data-records-pill-label="<?= esc($barangayName, 'attr') ?>" <?= in_array($barangayName, $selectedBarangays, true) ? 'checked' : '' ?>>
                                        <span class="form-check-label text-wrap small"><?= esc($barangayName) ?></span>
                                    </label>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="col-12 col-md-4" data-records-filter="status">
                        <div class="fw-semibold small text-uppercase text-muted mb-1">Status</div>
                        <?php foreach (['all' => 'All', 'active' => 'Active', 'archived' => 'Archived'] as $value => $label): ?>
                            <label class="form-check d-flex align-items-center gap-2 py-1">
                                <input class="form-check-input m-0" type="radio" name="status" value="<?= esc($value, 'attr') ?>" data-records-pill-label="<?= esc($label, 'attr') ?>" <?= $status === $value ? 'checked' : '' ?>>
                                <span class="form-check-label small"><?= esc($label) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-auto d-flex flex-wrap gap-2">
        <div class="btn-group" role="group" aria-label="Search actions">
            <button class="<?= btn('search') ?>" type="submit">Search</button>
            <button class="<?= btn('clear') ?>" type="button" data-records-clear>Clear</button>
        </div>
        <?php if ($canEdit): ?>
        <div class="btn-group" role="group" aria-label="Record actions">
            <button class="<?= btn('add') ?> js-open-family-add-modal" type="button" data-family-add-record data-modal-url="<?= esc(site_url($routeBase . '/create?partial=1'), 'attr') ?>" data-modal-title="New Family Record">Add</button>
            <button class="<?= btn('import') ?> js-open-family-import-modal" type="button" data-modal-url="<?= esc(site_url($routeBase . '/import'), 'attr') ?>" data-modal-title="Import from Excel" title="Bulk-import families from an Excel file">Import</button>
        </div>
        <?php endif; ?>
    </div>
</form>
<?= view('components/filter_pills', [
    'id' => 'familyFilterPills',
]) ?>
