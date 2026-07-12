<?php
/**
 * Manage Categories body: search/toolbar rows + lookup table.
 * Rendered inside components/card by Lookups/categories.php (bodyData is that
 * view's get_defined_vars(), matching its existing extract() convention).
 */
?>
<?php /* Search toolbar lives in categories.php, above this card (Manage Records standard). */ ?>
	<?= view('components/table_controls', [
		'searchId' => 'categoryLocalSearch',
		'searchAria' => 'Search shown categories',
		'searchFormAttrs' => 'data-lookup-search',
		'searchInputAttrs' => 'data-lookup-search-input',
		'sizeId' => 'categoryPerPage',
		'sizeAction' => site_url($listRoute),
		'sizeHiddenHtml' => ($keyword !== '' ? '<input type="hidden" name="q" value="' . esc($keyword, 'attr') . '">' : '')
			. ($status !== 'active' ? '<input type="hidden" name="status" value="' . esc($status, 'attr') . '">' : ''),
		'perPage' => $perPage,
		'perPageOptions' => $perPageOptions,
	]) ?>

	<div class="table-responsive">
		<table class="table manage-record-table align-middle lookup-management-table lookup-management-table--categories">
			<thead>
				<tr>
					<th class="lookup-col-name">Name</th>
					<th class="lookup-col-code">Code</th>
					<th class="lookup-col-description">Description</th>
					<th class="lookup-col-actions text-end">Actions</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($categories as $category): ?>
					<?php
					$categoryId = (int) ($category['categoryID'] ?? 0);
					$isArchived = trim((string) ($category['dt_deleted'] ?? '')) !== '';
					?>
					<tr data-row-archived="<?= $isArchived ? '1' : '0' ?>">
						<td><span class="sector-name"><?= esc((string) ($category['name'] ?? '')) ?></span></td>
						<td><span class="badge bg-light text-dark border"><?= esc((string) ($category['code'] ?? '')) ?></span></td>
						<td><span class="text-trim d-inline-block"><?= esc((string) ($category['description'] ?? '')) ?></span></td>
						<td class="text-end">
							<div class="dropdown actions-menu">
								<button class="btn btn-outline-secondary btn-sm actions-menu-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false" aria-label="Category actions">
									<i class="bi bi-three-dots" aria-hidden="true"></i>
								</button>
								<div class="dropdown-menu dropdown-menu-end">
									<?php if (! $isArchived): ?>
										<button
											class="dropdown-item js-category-modal-open"
											type="button"
											data-category-mode="update"
											data-category-id="<?= esc((string) $categoryId) ?>"
											data-category-code="<?= esc((string) ($category['code'] ?? ''), 'attr') ?>"
											data-category-name="<?= esc((string) ($category['name'] ?? ''), 'attr') ?>"
											data-category-description="<?= esc((string) ($category['description'] ?? ''), 'attr') ?>">
											<i class="bi bi-pencil-square" aria-hidden="true"></i>Edit
										</button>
										<button
											class="dropdown-item text-danger js-category-modal-open"
											type="button"
											data-category-mode="archive"
											data-category-id="<?= esc((string) $categoryId) ?>"
											data-category-code="<?= esc((string) ($category['code'] ?? ''), 'attr') ?>"
											data-category-name="<?= esc((string) ($category['name'] ?? ''), 'attr') ?>"
											data-category-description="<?= esc((string) ($category['description'] ?? ''), 'attr') ?>">
											<i class="bi bi-archive" aria-hidden="true"></i>Archive
										</button>
									<?php else: ?>
										<button
											class="dropdown-item text-success js-category-modal-open"
											type="button"
											data-category-mode="restore"
											data-category-id="<?= esc((string) $categoryId) ?>"
											data-category-name="<?= esc((string) ($category['name'] ?? ''), 'attr') ?>">
											<i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>Restore
										</button>
									<?php endif; ?>
								</div>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
				<?php if ($categories === []): ?>
					<tr>
						<td colspan="4" class="sector-empty-state"><?= $keyword !== '' ? 'No categories match your search.' : 'No category records found.' ?></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
