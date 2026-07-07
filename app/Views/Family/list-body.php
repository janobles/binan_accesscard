<?php
/**
 * Family records list body: filter toolbar + AJAX DataTable.
 * Rendered inside components/card by Family/list.php — see that file for the
 * variable contract (routeBase, keyword, status, sectorOptions, barangayOptions,
 * selectedSectorIds, selectedBarangays, sectorOptionLabel, canEdit).
 */
?>
<form class="row g-2 align-items-stretch mb-2" id="familyDataTableFilters" aria-label="Database records search">
    <div class="col-12 col-xl-3 family-keyword-field">
        <input
            class="form-control h-100"
            type="search"
            name="q"
            value="<?= esc($keyword, 'attr') ?>"
            aria-label="Database keyword search"
            placeholder="Search family records..."
            autocomplete="off"
            data-records-database-keyword
        >
    </div>

    <div class="col-12 col-sm-6 col-xl family-filter-field">
        <div class="dropdown h-100" data-records-filter="sector">
            <button class="btn btn-outline-secondary dropdown-toggle w-100 h-100 text-start d-flex align-items-center justify-content-between" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                <span class="text-truncate" data-records-filter-label>-Select sector-</span>
            </button>
            <div class="dropdown-menu w-100 p-1 overflow-auto small">
                <?php foreach ($sectorOptions as $sector): ?>
                    <?php
                    $sectorId = (string) ($sector['sectorID'] ?? $sector['id'] ?? '');
                    $sectorName = $sectorOptionLabel((array) $sector);
                    ?>
                    <?php if ($sectorId !== '' && $sectorName !== ''): ?>
                        <label class="dropdown-item d-flex align-items-center gap-2 rounded py-1 px-2" data-records-option>
                            <input class="form-check-input m-0" type="checkbox" name="sectorID[]" value="<?= esc($sectorId, 'attr') ?>" <?= in_array($sectorId, $selectedSectorIds, true) ? 'checked' : '' ?>>
                            <span class="text-wrap"><?= esc($sectorName) ?></span>
                        </label>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl family-filter-field">
        <div class="dropdown h-100" data-records-filter="barangay">
            <button class="btn btn-outline-secondary dropdown-toggle w-100 h-100 text-start d-flex align-items-center justify-content-between" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                <span class="text-truncate" data-records-filter-label>-Select barangay-</span>
            </button>
            <div class="dropdown-menu w-100 p-1 overflow-auto small">
                <?php foreach ($barangayOptions as $barangay): ?>
                    <?php $barangayName = trim((string) $barangay); ?>
                    <?php if ($barangayName !== ''): ?>
                        <label class="dropdown-item d-flex align-items-center gap-2 rounded py-1 px-2" data-records-option>
                            <input class="form-check-input m-0" type="checkbox" name="barangay[]" value="<?= esc($barangayName, 'attr') ?>" <?= in_array($barangayName, $selectedBarangays, true) ? 'checked' : '' ?>>
                            <span class="text-wrap"><?= esc($barangayName) ?></span>
                        </label>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-xl family-filter-field">
        <select class="form-select h-100" name="status" aria-label="Record status">
            <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>-Select Status-</option>
            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>Archived</option>
        </select>
    </div>

    <div class="col-12 col-sm-6 col-xl-3 family-action-field">
        <div class="btn-toolbar h-100" role="toolbar" aria-label="Manage Records actions">
            <div class="btn-group w-100 h-100" role="group" aria-label="Search and record actions">
                <button class="btn btn-outline-success records-search-action" type="submit">Search</button>
                <button class="btn btn-outline-secondary records-search-action" type="button" data-records-clear>Clear</button>
                <?php if ($canEdit): ?>
                <button class="btn btn-primary records-search-action js-open-family-add-modal" type="button" data-family-add-record data-modal-url="<?= esc(site_url($routeBase . '/create?partial=1'), 'attr') ?>" data-modal-title="New Family Record">Add</button>
                <button class="btn btn-outline-primary records-search-action js-open-family-import-modal" type="button" data-modal-url="<?= esc(site_url($routeBase . '/import'), 'attr') ?>" data-modal-title="Import from Excel" title="Bulk-import families from an Excel file">Import</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</form>

<div class="table-responsive flex-grow-1 overflow-auto">
    <table
        class="table table-sm table-hover align-middle w-100 small"
        id="familyRecordsTable"
        data-ajax-url="<?= esc(site_url($routeBase . '/data'), 'attr') ?>"
    >
        <thead class="table-light">
        <tr>
            <th class="fw-semibold small">QR NO.</th>
            <th class="fw-semibold small">HEAD/MEMBER NAME</th>
            <th class="fw-semibold small">SECTOR</th>
            <th class="fw-semibold small">ADDRESS</th>
            <th class="fw-semibold small">BIRTHDAY</th>
            <th class="fw-semibold small text-end">ACTIONS</th>
        </tr>
        </thead>
        <tbody></tbody>
    </table>
</div>
