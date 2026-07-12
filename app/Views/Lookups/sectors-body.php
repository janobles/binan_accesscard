<?php
/**
 * Sector Management body: search/toolbar rows + lookup table.
 * Rendered inside components/card by Lookups/sectors.php (bodyData is that
 * view's get_defined_vars(), matching its existing extract() convention).
 */
?>
<div class="records-search-panel">
		<form class="records-search-row records-lookup-search" method="get" action="<?= esc(site_url($listRoute), 'attr') ?>" role="search" aria-label="Search the sector database" data-records-filter-form data-records-pills="sectorFilterPills">
			<input class="form-control" type="search" name="q" value="<?= esc($keyword, 'attr') ?>" placeholder="Search entire database..." aria-label="Search the sector database" autocomplete="off">
			<div class="dropdown" data-records-panel>
				<button class="<?= btn('filter') ?> dropdown-toggle w-100" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
					<i class="bi bi-funnel" aria-hidden="true"></i> Filters
				</button>
				<div class="dropdown-menu records-filter-panel p-3">
					<div data-records-filter="status" data-records-group-label="Status">
						<div class="fw-semibold small text-uppercase text-muted mb-1">Status</div>
						<label class="form-check d-flex align-items-center gap-2 py-1" data-records-option>
							<input class="form-check-input m-0" type="radio" name="status" value="active" data-records-default <?= $status === 'active' ? 'checked' : '' ?>>
							<span class="form-check-label small">Active (<?= esc((string) $activeSectorCount) ?>)</span>
						</label>
						<label class="form-check d-flex align-items-center gap-2 py-1" data-records-option>
							<input class="form-check-input m-0" type="radio" name="status" value="archived" data-records-pill-label="Archived" <?= $status === 'archived' ? 'checked' : '' ?>>
							<span class="form-check-label small">Archived (<?= esc((string) $archivedSectorCount) ?>)</span>
						</label>
						<label class="form-check d-flex align-items-center gap-2 py-1" data-records-option>
							<input class="form-check-input m-0" type="radio" name="status" value="all" data-records-pill-label="All" <?= $status === 'all' ? 'checked' : '' ?>>
							<span class="form-check-label small">All (<?= esc((string) $allSectorCount) ?>)</span>
						</label>
					</div>
				</div>
			</div>
			<?php if ($perPage !== 50): ?><input type="hidden" name="per_page" value="<?= esc((string) $perPage, 'attr') ?>"><?php endif; ?>
			<button class="<?= btn('search') ?> records-search-action" type="submit"><i class="bi bi-search" aria-hidden="true"></i><span>Search</span></button>
			<a class="<?= btn('clear') ?> records-search-action" href="<?= esc($sectorClearUrl(), 'attr') ?>"><i class="bi bi-x-lg" aria-hidden="true"></i><span>Clear</span></a>
			<?php if ($canManage): ?>
			<button class="<?= btn('add') ?> records-search-action js-sector-modal-open" type="button" data-sector-mode="create"><span>Add Sector</span></button>
			<?php endif; ?>
		</form>
		<?= view('components/filter_pills', ['id' => 'sectorFilterPills']) ?>
	</div>

	<?php /* Controls row: page size (server) + local "Search:" live filter (client-side, no reload). */ ?>
	<div class="table-meta">
		<div class="records-table-controls">
			<form class="records-page-size-form" method="get" action="<?= esc(site_url($listRoute), 'attr') ?>">
				<?php if ($keyword !== ''): ?><input type="hidden" name="q" value="<?= esc($keyword, 'attr') ?>"><?php endif; ?>
				<?php if ($status !== 'active'): ?><input type="hidden" name="status" value="<?= esc($status, 'attr') ?>"><?php endif; ?>
				<label for="sectorPerPage">Show</label>
				<select class="form-select form-select-sm" id="sectorPerPage" name="per_page" onchange="this.form.submit()">
					<?php foreach ($perPageOptions as $option): ?>
						<option value="<?= esc((string) $option, 'attr') ?>" <?= $perPage === (int) $option ? 'selected' : '' ?>><?= esc((string) $option) ?></option>
					<?php endforeach; ?>
				</select>
				<span>entries</span>
			</form>
			<form class="records-table-search-form" role="search" data-lookup-search aria-label="Filter shown sectors">
				<label for="sectorLocalSearch">Search:</label>
				<input class="form-control form-control-sm" type="search" id="sectorLocalSearch" data-lookup-search-input placeholder="Filter loaded results..." autocomplete="off" aria-label="Filter shown sectors">
			</form>
		</div>
	</div>

	<div class="table-responsive">
		<table class="table manage-record-table align-middle lookup-management-table lookup-management-table--sectors">
			<thead>
				<tr>
					<th class="lookup-col-name">Name</th>
					<th class="lookup-col-code">Shortcode</th>
					<th class="lookup-col-description">Description</th>
					<th class="lookup-col-status">Status</th>
					<?php if ($canManage): ?><th class="lookup-col-actions text-end">Actions</th><?php endif; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($sectors as $sector): ?>
					<?php $sectorId = (int) ($sector['sectorID'] ?? 0); ?>
					<?php $isArchived = trim((string) ($sector['dt_deleted'] ?? '')) !== ''; ?>
					<tr data-row-archived="<?= $isArchived ? '1' : '0' ?>">
						<td><span class="sector-name"><?= esc((string) ($sector['name'] ?? '')) ?></span></td>
						<td><span class="badge bg-light text-dark border"><?= esc((string) ($sector['shortcode'] ?? '')) ?></span></td>
						<td><span class="text-trim d-inline-block"><?= esc((string) ($sector['description'] ?? '')) ?></span></td>
						<td><span class="sector-status-badge <?= $isArchived ? 'sector-status-archived' : 'sector-status-active' ?>"><?= $isArchived ? 'Archived' : 'Active' ?></span></td>
						<?php if ($canManage): ?><td class="text-end">
							<div class="dropdown actions-menu">
								<button class="btn btn-outline-secondary btn-sm actions-menu-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false" aria-label="Sector actions">
									<i class="bi bi-three-dots" aria-hidden="true"></i>
								</button>
								<div class="dropdown-menu dropdown-menu-end">
									<?php if (! $isArchived): ?>
										<button
											class="dropdown-item js-sector-modal-open"
											type="button"
											data-sector-mode="update"
											data-sector-id="<?= esc((string) $sectorId) ?>"
											data-sector-shortcode="<?= esc((string) ($sector['shortcode'] ?? ''), 'attr') ?>"
											data-sector-name="<?= esc((string) ($sector['name'] ?? ''), 'attr') ?>"
											data-sector-description="<?= esc((string) ($sector['description'] ?? ''), 'attr') ?>">
											<i class="bi bi-pencil-square" aria-hidden="true"></i>Edit
										</button>
										<button
											class="dropdown-item text-danger js-sector-modal-open"
											type="button"
											data-sector-mode="archive"
											data-sector-id="<?= esc((string) $sectorId) ?>"
											data-sector-shortcode="<?= esc((string) ($sector['shortcode'] ?? ''), 'attr') ?>"
											data-sector-name="<?= esc((string) ($sector['name'] ?? ''), 'attr') ?>"
											data-sector-description="<?= esc((string) ($sector['description'] ?? ''), 'attr') ?>">
											<i class="bi bi-archive" aria-hidden="true"></i>Archive
										</button>
									<?php else: ?>
										<button
											class="dropdown-item text-success js-sector-modal-open"
											type="button"
											data-sector-mode="restore"
											data-sector-id="<?= esc((string) $sectorId) ?>"
											data-sector-name="<?= esc((string) ($sector['name'] ?? ''), 'attr') ?>">
											<i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>Restore
										</button>
									<?php endif; ?>
								</div>
							</div>
						</td>
						<?php endif; ?>
					</tr>
				<?php endforeach; ?>
				<?php if ($sectors === []): ?>
					<tr>
						<td colspan="<?= $canManage ? 5 : 4 ?>" class="sector-empty-state"><?= $keyword !== '' ? 'No sectors match your search.' : 'No sector records found.' ?></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
