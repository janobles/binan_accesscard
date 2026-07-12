<?php
/**
 * Services and Programs body: search/toolbar rows + lookup table.
 * Rendered inside components/card by Lookups/services.php (bodyData is that
 * view's get_defined_vars(), matching its existing extract() convention).
 */
?>
<?php /* Search toolbar lives in services.php, above this card (Manage Records standard). */ ?>
	<?php /* Controls row, Manage Records standard: page search left, show-entries right. */ ?>
	<div class="table-meta">
		<div class="records-table-controls">
			<form class="records-table-search-form" role="search" data-lookup-search aria-label="Search shown services">
				<div class="input-group input-group-sm">
					<input class="form-control" type="search" id="serviceLocalSearch" data-lookup-search-input placeholder="Search this page..." autocomplete="off" aria-label="Search this page">
					<button class="btn btn-primary" type="submit" aria-label="Search this page"><i class="bi bi-search" aria-hidden="true"></i></button>
				</div>
			</form>
			<form class="records-page-size-form" method="get" action="<?= esc(site_url($listRoute), 'attr') ?>">
				<?php if ($keyword !== ''): ?><input type="hidden" name="q" value="<?= esc($keyword, 'attr') ?>"><?php endif; ?>
				<?php if ($status !== 'active'): ?><input type="hidden" name="status" value="<?= esc($status, 'attr') ?>"><?php endif; ?>
				<label for="servicePerPage">Show</label>
				<select class="form-select form-select-sm" id="servicePerPage" name="per_page" onchange="this.form.submit()">
					<?php foreach ($perPageOptions as $option): ?>
						<option value="<?= esc((string) $option, 'attr') ?>" <?= $perPage === (int) $option ? 'selected' : '' ?>><?= esc((string) $option) ?></option>
					<?php endforeach; ?>
				</select>
				<span>entries</span>
			</form>
		</div>
	</div>

	<div class="table-responsive">
		<table class="table manage-record-table align-middle lookup-management-table lookup-management-table--services">
			<thead>
				<tr>
					<th class="lookup-col-name">Name</th>
					<th class="lookup-col-code">Code</th>
					<th class="lookup-col-category">Category</th>
					<th class="lookup-col-description">Description</th>
					<th class="lookup-col-status">Status</th>
					<?php if ($canManage): ?><th class="lookup-col-actions text-end">Actions</th><?php endif; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($services as $service): ?>
					<?php $serviceId = (int) ($service['serviceID'] ?? 0); ?>
					<?php $isArchived = trim((string) ($service['dt_deleted'] ?? '')) !== ''; ?>
					<tr data-row-archived="<?= $isArchived ? '1' : '0' ?>">
						<td><span class="sector-name"><?= esc((string) ($service['name'] ?? '')) ?></span></td>
						<td><span class="badge bg-primary-subtle text-dark border fw-semibold"><?= esc((string) ($service['shortcode'] ?? '')) ?></span></td>
						<td><span class="badge bg-light text-dark border"><?= esc((string) ($service['category'] ?? '')) ?></span></td>
						<td><span class="text-trim d-inline-block"><?= esc((string) ($service['description'] ?? '')) ?></span></td>
						<td><span class="sector-status-badge <?= $isArchived ? 'sector-status-archived' : 'sector-status-active' ?>"><?= $isArchived ? 'Archived' : 'Active' ?></span></td>
						<?php if ($canManage): ?><td class="text-end">
							<div class="dropdown actions-menu">
								<button class="btn btn-outline-secondary btn-sm actions-menu-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false" aria-label="Service actions">
									<i class="bi bi-three-dots" aria-hidden="true"></i>
								</button>
								<div class="dropdown-menu dropdown-menu-end">
									<?php if (! $isArchived): ?>
										<button
											class="dropdown-item js-service-modal-open"
											type="button"
											data-service-mode="update"
											data-service-shortcode="<?= esc((string) ($service['shortcode'] ?? ''), 'attr') ?>"
											data-service-id="<?= esc((string) $serviceId) ?>"
											data-service-category="<?= esc((string) ($service['category'] ?? ''), 'attr') ?>"
											data-service-name="<?= esc((string) ($service['name'] ?? ''), 'attr') ?>"
											data-service-description="<?= esc((string) ($service['description'] ?? ''), 'attr') ?>">
											<i class="bi bi-pencil-square" aria-hidden="true"></i>Edit
										</button>
										<button
											class="dropdown-item text-danger js-service-modal-open"
											type="button"
											data-service-mode="archive"
											data-service-id="<?= esc((string) $serviceId) ?>"
											data-service-category="<?= esc((string) ($service['category'] ?? ''), 'attr') ?>"
											data-service-name="<?= esc((string) ($service['name'] ?? ''), 'attr') ?>"
											data-service-description="<?= esc((string) ($service['description'] ?? ''), 'attr') ?>">
											<i class="bi bi-archive" aria-hidden="true"></i>Archive
										</button>
									<?php else: ?>
										<button
											class="dropdown-item text-success js-service-modal-open"
											type="button"
											data-service-mode="restore"
											data-service-id="<?= esc((string) $serviceId) ?>"
											data-service-name="<?= esc((string) ($service['name'] ?? ''), 'attr') ?>">
											<i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>Restore
										</button>
									<?php endif; ?>
								</div>
							</div>
						</td>
						<?php endif; ?>
					</tr>
				<?php endforeach; ?>
				<?php if ($services === []): ?>
					<tr>
						<td colspan="<?= $canManage ? 6 : 5 ?>" class="sector-empty-state"><?= $keyword !== '' ? 'No services match your search.' : 'No service or program records found.' ?></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
