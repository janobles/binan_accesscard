<?php
helper('dashboard_view');
extract(service_management_view_data(get_defined_vars()), EXTR_OVERWRITE);

$status = (string) ($status ?? 'active') === 'archived' ? 'archived' : 'active';
$canRestore = (bool) ($canRestore ?? false);
$isArchivedView = $status === 'archived';

$defaultServiceCategoryOptions = \App\Support\FamilyProfilingFormV2::SERVICE_CATEGORIES;
$serviceCategoryOptions = array_values(array_unique(array_filter(array_map(
    static fn (array $service): string => trim((string) ($service['category'] ?? '')),
    $services
))));
$serviceCategoryOptions = array_values(array_unique(array_merge($defaultServiceCategoryOptions, $serviceCategoryOptions)));
?>

<div class="panel mb-3" data-service-management-root>
	<div class="section-title mt-0">
		<span><?= $isArchivedView ? 'Archived Services and Programs' : 'Services and Programs Management' ?></span>
		<?php if (! $isArchivedView): ?>
			<button class="btn btn-primary btn-sm js-service-modal-open" type="button" data-service-mode="create">Add Service or Program</button>
		<?php endif; ?>
	</div>

	<?php if ($canRestore): ?>
		<div class="d-flex gap-2 mb-3">
			<a class="btn btn-sm <?= $status === 'active' ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= esc(site_url('admin/services'), 'attr') ?>">Active</a>
			<a class="btn btn-sm <?= $status === 'archived' ? 'btn-primary' : 'btn-outline-secondary' ?>" href="<?= esc(site_url('admin/services?status=archived'), 'attr') ?>">Archived</a>
		</div>
	<?php endif; ?>

	<div class="table-responsive">
		<table class="table table-sm align-middle management-table">
			<thead>
				<tr>
					<th>Category</th>
					<th>Name</th>
					<th>Description</th>
					<?php if ($isArchivedView): ?>
						<th>Archived On</th>
					<?php endif; ?>
					<th class="text-end">Actions</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($services as $service): ?>
					<?php $serviceId = (int) ($service['serviceID'] ?? 0); ?>
					<tr>
						<td><?= esc((string) ($service['category'] ?? '')) ?></td>
						<td><?= esc((string) ($service['name'] ?? '')) ?></td>
						<td><?= esc((string) ($service['description'] ?? '')) ?></td>
						<?php if ($isArchivedView): ?>
							<?php $archivedOn = (string) ($service['dt_deleted'] ?? ''); ?>
							<td class="text-muted small"><?= $archivedOn !== '' ? esc(date('M j, Y g:i A', strtotime($archivedOn))) : '—' ?></td>
						<?php endif; ?>
						<td class="text-end">
							<div class="management-row-actions">
								<?php if ($isArchivedView): ?>
									<form class="d-inline js-management-delete-form" method="post" action="<?= site_url('admin/services/restore/' . $serviceId) ?>" data-confirm-message="Restore &quot;<?= esc((string) ($service['name'] ?? 'this service or program'), 'attr') ?>&quot; to the active list?">
										<?= csrf_field() ?>
										<button class="btn btn-outline-success btn-sm" type="submit">Restore</button>
									</form>
								<?php else: ?>
									<button
										class="btn btn-outline-primary btn-sm js-service-modal-open"
										type="button"
										data-service-mode="update"
										data-service-id="<?= esc((string) $serviceId) ?>"
										data-service-category="<?= esc((string) ($service['category'] ?? ''), 'attr') ?>"
										data-service-name="<?= esc((string) ($service['name'] ?? ''), 'attr') ?>"
										data-service-description="<?= esc((string) ($service['description'] ?? ''), 'attr') ?>">
										Edit
									</button>
									<button
										class="btn btn-outline-danger btn-sm js-service-modal-open"
										type="button"
										data-service-mode="archive"
										data-service-id="<?= esc((string) $serviceId) ?>"
										data-service-category="<?= esc((string) ($service['category'] ?? ''), 'attr') ?>"
										data-service-name="<?= esc((string) ($service['name'] ?? ''), 'attr') ?>"
										data-service-description="<?= esc((string) ($service['description'] ?? ''), 'attr') ?>">
										Archive
									</button>
								<?php endif; ?>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
				<?php if ($services === []): ?>
					<tr>
						<td colspan="<?= $isArchivedView ? 5 : 4 ?>" class="text-center text-muted"><?= $isArchivedView ? 'No archived services or programs found.' : 'No service or program records found.' ?></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

<?php if (! $isArchivedView): ?>
	<?= view('Dashboard/Sectors and Services/service-modal', [
		'serviceCategoryOptions' => $serviceCategoryOptions,
	]) ?>
<?php endif; ?>
