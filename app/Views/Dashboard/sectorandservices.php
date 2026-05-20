<?php
$servicesByCategory = $servicesByCategory ?? [];
$sectorOptions = $sectorOptions ?? [];
?>

<div class="form-section family-step-panel" data-step="2">
	<div class="row g-3 mb-3">
		<div class="col-md-4 col-lg-3">
			<label class="form-label" for="sectorID">Sector</label>
			<select class="form-select" id="sectorID" name="sectorID" required>
				<option value="">Select</option>
				<?php foreach ($sectorOptions as $sector): ?>
					<option value="<?= esc((string) ($sector['sectorID'] ?? '')) ?>"><?= esc((string) ($sector['name'] ?? '')) ?></option>
				<?php endforeach; ?>
			</select>
		</div>
	</div>

	<div class="section-title">
		<span>Services and Programs</span>
	</div>
	<div class="row g-3">
		<?php foreach ($servicesByCategory as $category => $services): ?>
			<div class="col-lg-4">
				<div class="assistance-box">
					<h6 class="mb-2"><?= esc((string) $category) ?></h6>
					<div class="service-check-list">
						<?php foreach ($services as $service): ?>
							<?php $serviceId = (string) ($service['serviceID'] ?? ''); ?>
							<?php $serviceInputId = 'service_' . preg_replace('/[^a-zA-Z0-9_\-]/', '_', strtolower((string) $category)) . '_' . $serviceId; ?>
							<label class="form-check" for="<?= esc($serviceInputId) ?>">
								<input class="form-check-input" id="<?= esc($serviceInputId) ?>" type="checkbox" name="service_ids[]" value="<?= esc($serviceId) ?>">
								<span class="form-check-label"><?= esc((string) ($service['name'] ?? '')) ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
		<?php endforeach; ?>
		<?php if ($servicesByCategory === []): ?>
			<div class="col-12">
				<p class="text-muted mb-0">No services available.</p>
			</div>
		<?php endif; ?>
	</div>
</div>
