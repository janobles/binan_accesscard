<?php
helper('family_modal');
extract(family_modal_prepare(get_defined_vars()), EXTR_OVERWRITE);

// Members already on the record (Update mode); empty for a new record. Rendered
// server-side so an edit re-posts them — FamilyController::update() rebuilds the
// member list from the submission, so omitting them would drop existing members.
$existingMembers = (array) ($existingMembers ?? []);
$personFieldOptions = compact(
    'suffixOptions',
    'sexOptions',
    'civilOptions',
    'religionOptions',
    'educationOptions',
    'jobOptions',
    'incomeOptions'
);

/**
 * Renders one repeatable family-member row. $index is an int for a pre-filled
 * existing member, or the literal '__INDEX__' placeholder in the <template>;
 * manage-family-modal.js swaps the placeholder for the next counter on Add.
 * Field names post as members[$index][...] to match FamilyController::store()/update().
 */
$renderMemberRow = static function ($index, array $m = []) use (
    $personFields,
    $personFieldOptions,
    $selectOptions,
    $sectorLabel,
    $serviceLabel,
    $relationshipOptions,
    $sectorCatalog,
    $servicesByCategory
): string {
    $i = (string) $index;
    $field = static fn (string $name): string => 'members[' . $i . '][' . $name . ']';
    $val = static fn (string $key): string => (string) ($m[$key] ?? '');
    $selectedSectors = array_map('strval', (array) ($m['sector_ids'] ?? []));
    $selectedServices = array_map('strval', (array) ($m['service_ids'] ?? []));

    ob_start();
    ?>
    <div class="family-member-card" data-family-member-row>
        <div class="family-member-card-header">
            <?php /* Filled with the member's name in a later pass; renders as nothing today. */ ?>
            <span class="text-muted small" data-family-member-name></span>
            <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-primary" data-family-set-head>Set as Head</button>
                <button type="button" class="btn btn-outline-danger" data-family-member-remove>Remove</button>
            </div>
        </div>
        <div class="row g-3">
            <?= family_modal_render_person_fields([
                'personFields' => $personFields,
                'optionsByKey' => $personFieldOptions,
                'selectOptions' => $selectOptions,
                'field' => $field,
                'value' => $val,
                // Members require the same personal fields as the head (Address/Barangay are
                // head-only — members inherit them, so they are not part of this component).
                'required' => true,
            ]) ?>
            <div class="col-12 col-md-6 col-xl-3">
                <label class="form-label">Relationship</label>
                <select class="form-select js-other-select" data-other-field="relationship" data-initial-value="<?= esc($val('relationship'), 'attr') ?>" name="<?= esc($field('relationship'), 'attr') ?>"><?= $selectOptions($relationshipOptions, $val('relationship'), 'Select') ?></select>
                <input class="form-control mt-2 js-other-input family-form-hidden" data-other-for="relationship" placeholder="Enter relationship">
            </div>
        </div>

        <div class="row g-4 mt-1">
            <div class="col-12 col-lg-5">
                <h5 class="family-column-title">Sectors</h5>
                <div class="family-option-box family-option-box--sm">
                    <?php if ($sectorCatalog === []): ?>
                        <p class="text-muted mb-0">No sectors available.</p>
                    <?php endif; ?>
                    <?php foreach ($sectorCatalog as $sectorGroup): ?>
                        <?php
                        $sectorGroup = array_values((array) $sectorGroup);
                        if ($sectorGroup === []) { continue; }
                        ?>
                        <div class="family-option-group">
                            <?php foreach ($sectorGroup as $sector): ?>
                                <?php
                                $sector = (array) $sector;
                                $sectorId = (string) ($sector['sectorID'] ?? $sector['id'] ?? '');
                                $label = $sectorLabel($sector);
                                $isArchived = ! empty($sector['is_archived']);
                                ?>
                                <?php if ($sectorId !== '' && $label !== ''): ?>
                                    <label class="form-check family-choice<?= $isArchived ? ' family-choice--archived' : '' ?>">
                                        <input type="checkbox" name="<?= esc($field('sector_ids') . '[]', 'attr') ?>" value="<?= esc($sectorId, 'attr') ?>" data-label="<?= esc($label, 'attr') ?>" data-sector-code="<?= esc((string) ($sector['shortcode'] ?? $sector['code'] ?? ''), 'attr') ?>" data-sector-name="<?= esc((string) ($sector['name'] ?? ''), 'attr') ?>"<?= $isArchived ? ' data-archived="1"' : '' ?> <?= in_array($sectorId, $selectedSectors, true) ? 'checked' : '' ?>>
                                        <span class="form-check-label"><?= esc($label) ?><?php if ($isArchived): ?> <span class="family-choice-badge">Archived</span><?php endif; ?></span>
                                    </label>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-12 col-lg-7">
                <h5 class="family-column-title">Services and Programs Available</h5>
                <div class="family-option-box family-option-box--sm">
                    <div class="family-suggested" data-family-suggested hidden aria-live="polite">
                        <p class="family-suggested-title">Suggested for this person</p>
                        <p class="family-suggested-reason" data-family-suggested-reason></p>
                        <div data-family-suggested-groups></div>
                    </div>
                    <?php if ($servicesByCategory === []): ?>
                        <p class="text-muted mb-0">No services available.</p>
                    <?php endif; ?>
                    <?php foreach ($servicesByCategory as $category => $services): ?>
                        <div class="family-option-group" data-service-category="<?= esc((string) $category, 'attr') ?>">
                            <p class="family-option-group-title"><?= esc((string) $category) ?></p>
                            <?php foreach ((array) $services as $service): ?>
                                <?php
                                $service = (array) $service;
                                $serviceId = (string) ($service['serviceID'] ?? $service['id'] ?? '');
                                $label = $serviceLabel($service);
                                $isArchived = ! empty($service['is_archived']);
                                ?>
                                <?php if ($serviceId !== '' && $label !== ''): ?>
                                    <label class="form-check family-choice<?= $isArchived ? ' family-choice--archived' : '' ?>">
                                        <input type="checkbox" name="<?= esc($field('service_ids') . '[]', 'attr') ?>" value="<?= esc($serviceId, 'attr') ?>" data-label="<?= esc($label, 'attr') ?>"<?= $isArchived ? ' data-archived="1"' : '' ?> <?= in_array($serviceId, $selectedServices, true) ? 'checked' : '' ?>>
                                        <span class="form-check-label"><?= esc($label) ?><?php if ($isArchived): ?> <span class="family-choice-badge">Archived</span><?php endif; ?></span>
                                    </label>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
    return (string) ob_get_clean();
};
?>

<?php $importFieldIssues = (array) ($importFieldIssues ?? []); ?>
<?php /* The modal header already shows the title, so the form no longer renders its own
         heading. manage-family-modal.js copies this attribute into the header instead. */ ?>
<div class="family-entry-form" data-family-entry-form data-family-modal-title="<?= esc($modalTitle, 'attr') ?>"<?php if ($importFieldIssues !== []): ?> data-family-import-field-issues="<?= esc(json_encode($importFieldIssues), 'attr') ?>"<?php endif; ?>>
    <?php /* Import-fix context: the staged errors/warnings for this family, so the worker sees
             exactly what to correct. Only rendered when opened from the Import Review screen. */ ?>
    <?php
    $importIssues   = (array) ($importIssues ?? []);
    $blockingIssues = array_values(array_filter($importIssues, static fn (array $i): bool => ($i['severity'] ?? 'blocking') === 'blocking'));
    $warningIssues  = array_values(array_filter($importIssues, static fn (array $i): bool => ($i['severity'] ?? 'blocking') !== 'blocking'));
    $renderIssue    = static function (array $issue): string {
        $person = trim((string) ($issue['person'] ?? ''));
        $column = trim((string) ($issue['column'] ?? ''));
        $lead   = $person !== '' ? $person . ($column !== '' ? ' · ' . $column : '') : $column;

        return '<li>' . ($lead !== '' ? '<strong>' . esc($lead) . ':</strong> ' : '') . esc((string) ($issue['message'] ?? '')) . '</li>';
    };
    ?>
    <?php if ($blockingIssues !== [] || $warningIssues !== []): ?>
        <div class="family-import-issues" data-family-import-issues>
            <?php if ($blockingIssues !== []): ?>
                <div class="alert alert-danger">
                    <div class="fw-semibold mb-1"><i class="bi bi-exclamation-octagon me-1" aria-hidden="true"></i><?= count($blockingIssues) ?> issue<?= count($blockingIssues) === 1 ? '' : 's' ?> to fix before this family can import</div>
                    <ul class="mb-0 ps-3"><?php foreach ($blockingIssues as $issue): ?><?= $renderIssue($issue) ?><?php endforeach; ?></ul>
                </div>
            <?php endif; ?>
            <?php if ($warningIssues !== []): ?>
                <div class="alert alert-warning">
                    <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle me-1" aria-hidden="true"></i><?= count($warningIssues) ?> warning<?= count($warningIssues) === 1 ? '' : 's' ?> — imports as typed unless you change it</div>
                    <ul class="mb-0 ps-3"><?php foreach ($warningIssues as $issue): ?><?= $renderIssue($issue) ?><?php endforeach; ?></ul>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= esc($action, 'attr') ?>" autocomplete="off">
        <?= csrf_field() ?>
        <input type="hidden" name="entry_type" value="head">
        <input type="hidden" name="form_mode" value="<?= esc($modalMode, 'attr') ?>">
        <?php if ($headId > 0): ?>
            <input type="hidden" name="head_id" value="<?= esc((string) $headId, 'attr') ?>">
        <?php endif; ?>
        <?php /* Truncation guard: manage-family-modal.js sets this to the live member-row
                 count just before submit. The server compares it against the members it
                 actually received to catch a POST silently clipped by max_input_vars. */ ?>
        <input type="hidden" name="members_meta_count" value="0" data-members-count>
        <?php /* Import-fix context only: tells the staging-save endpoint which QR group this
                 modal is replacing (see FamilyImportController::reviewFamilySave). */ ?>
        <?php if (($importFamilyNo ?? '') !== ''): ?>
            <input type="hidden" name="import_family_no" value="<?= esc((string) $importFamilyNo, 'attr') ?>">
        <?php endif; ?>
        <?php /* Import-fix context for a blank-QR row: keyed by its sheet row, not a QR. */ ?>
        <?php if ((int) ($importRow ?? 0) > 0): ?>
            <input type="hidden" name="import_row" value="<?= esc((string) $importRow, 'attr') ?>">
        <?php endif; ?>

        <div class="family-entry-content">
                <section class="family-entry-section family-entry-personal">
                    <?php $qrLocked = ! empty($qrLocked ?? false); ?>
                    <div class="row g-3 mb-4">
                        <div class="col-12 col-xl-3">
                            <label class="form-label" for="<?= esc($fieldPrefix, 'attr') ?>HeadQr">QR Number</label>
                            <input id="<?= esc($fieldPrefix, 'attr') ?>HeadQr" name="qr_control_no" type="text"
                                inputmode="numeric" pattern="0*[1-9][0-9]{0,6}"
                                title="QR number must be numeric and should not exceed 9,999,999 "
                                data-qr-check-url="<?= esc($qrCheckUrl, 'attr') ?>"
                                value="<?= esc($oldValue('qr_control_no'), 'attr') ?>"
                                <?= $qrLocked ? 'readonly' : 'required' ?>>
                            <?php if ($qrLocked): ?>
                                <small class="text-muted">Locked: subsidy already recorded under this number.</small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="row g-3">
                        <?= family_modal_render_person_fields([
                            'personFields' => $personFields,
                            'optionsByKey' => $personFieldOptions,
                            'selectOptions' => $selectOptions,
                            'field' => static fn (string $name): string => 'head_' . $name,
                            'value' => static fn (string $name): string => $oldValue('head_' . $name),
                            'idPrefix' => $fieldPrefix . 'Head',
                            'summary' => true,
                            'required' => true,
                        ]) ?>

                        <div class="col-12 col-xl-9">
                            <label class="form-label" for="<?= esc($fieldPrefix, 'attr') ?>HeadAddress">Address</label>
                            <input id="<?= esc($fieldPrefix, 'attr') ?>HeadAddress" name="head_address" type="text" value="<?= esc($oldValue('head_address'), 'attr') ?>" data-summary="address" minlength="2" required>
                        </div>
                        <div class="col-12 col-xl-3">
                            <label class="form-label" for="<?= esc($fieldPrefix, 'attr') ?>HeadBarangay">Barangay</label>
                            <select id="<?= esc($fieldPrefix, 'attr') ?>HeadBarangay" name="head_barangay" data-summary="barangay" required>
                                <?= $selectOptions($barangayOptions, $oldValue('head_barangay'), 'Barangay') ?>
                            </select>
                        </div>
                    </div>
                </section>

                <section class="family-entry-section family-sector-service">
                    <div class="row g-4">
                        <div class="col-12 col-lg-5">
                            <h4 class="family-column-title">Sectors</h4>
                            <div class="family-option-box">
                                <?php if ($sectorCatalog === []): ?>
                                    <p class="text-muted mb-0">No sectors available.</p>
                                <?php endif; ?>
                                <?php foreach ($sectorCatalog as $sectorGroup): ?>
                                    <?php
                                    $sectorGroup = array_values((array) $sectorGroup);
                                    if ($sectorGroup === []) { continue; }
                                    ?>
                                    <div class="family-option-group">
                                        <?php foreach ($sectorGroup as $sector): ?>
                                            <?php
                                            $sector = (array) $sector;
                                            $sectorId = (string) ($sector['sectorID'] ?? $sector['id'] ?? '');
                                            $label = $sectorLabel($sector);
                                            
                                            
                                            
                                            
                                            $isArchived = ! empty($sector['is_archived']);
                                            ?>
                                            <?php if ($sectorId !== '' && $label !== ''): ?>
                                                <label class="form-check family-choice<?= $isArchived ? ' family-choice--archived' : '' ?>">
                                                    <input type="checkbox" name="sector_ids[]" value="<?= esc($sectorId, 'attr') ?>" data-label="<?= esc($label, 'attr') ?>" data-sector-code="<?= esc((string) ($sector['shortcode'] ?? $sector['code'] ?? ''), 'attr') ?>" data-sector-name="<?= esc((string) ($sector['name'] ?? ''), 'attr') ?>"<?= $isArchived ? ' data-archived="1"' : '' ?> <?= in_array($sectorId, $selectedSectorIds, true) ? 'checked' : '' ?>>
                                                    <span class="form-check-label"><?= esc($label) ?><?php if ($isArchived): ?> <span class="family-choice-badge">Archived</span><?php endif; ?></span>
                                                </label>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="col-12 col-lg-7">
                            <h4 class="family-column-title">Services and Programs Available</h4>
                            <div class="family-option-box">
                                <div class="family-suggested" data-family-suggested hidden aria-live="polite">
                                    <p class="family-suggested-title">Suggested for this person</p>
                                    <p class="family-suggested-reason" data-family-suggested-reason></p>
                                    <div data-family-suggested-groups></div>
                                </div>
                                <?php if ($servicesByCategory === []): ?>
                                    <p class="text-muted mb-0">No services available.</p>
                                <?php endif; ?>
                                <?php foreach ($servicesByCategory as $category => $services): ?>
                                    <div class="family-option-group" data-service-category="<?= esc((string) $category, 'attr') ?>">
                                        <p class="family-option-group-title"><?= esc((string) $category) ?></p>
                                        <?php foreach ((array) $services as $service): ?>
                                            <?php
                                            $service = (array) $service;
                                            $serviceId = (string) ($service['serviceID'] ?? $service['id'] ?? '');
                                            $label = $serviceLabel($service);
                                            $isArchived = ! empty($service['is_archived']);
                                            ?>
                                            <?php if ($serviceId !== '' && $label !== ''): ?>
                                                <label class="form-check family-choice<?= $isArchived ? ' family-choice--archived' : '' ?>">
                                                    <input type="checkbox" name="service_ids[]" value="<?= esc($serviceId, 'attr') ?>" data-label="<?= esc($label, 'attr') ?>"<?= $isArchived ? ' data-archived="1"' : '' ?> <?= in_array($serviceId, $selectedServiceIds, true) ? 'checked' : '' ?>>
                                                    <span class="form-check-label"><?= esc($label) ?><?php if ($isArchived): ?> <span class="family-choice-badge">Archived</span><?php endif; ?></span>
                                                </label>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="family-entry-section family-members-section">
                    <p class="text-muted small mb-3">Family members in this household. Leave empty if there are none.</p>

                    <div data-family-members>
                        <?php foreach (array_values($existingMembers) as $i => $member): ?>
                            <?= $renderMemberRow($i, (array) $member) ?>
                        <?php endforeach; ?>
                    </div>

                    <template data-family-member-template>
                        <?= $renderMemberRow('__INDEX__') ?>
                    </template>

                    <div class="btn-toolbar family-member-toolbar" role="toolbar" aria-label="Family member actions">
                        <div class="btn-group" role="group" aria-label="Member actions">
                            <button class="btn btn-success" type="button" data-family-add-member data-next-index="<?= esc((string) count($existingMembers), 'attr') ?>">Add Member</button>
                        </div>
                    </div>
                </section>
        </div>

        <footer class="family-entry-actions d-flex flex-wrap align-items-center gap-2" aria-label="Family form actions">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Close</button>
                <button class="btn btn-danger" type="reset" data-family-clear>Clear</button>
                <?php if ($headId > 0): ?>
                    <a class="btn btn-link btn-sm px-1"
                       href="<?= site_url('admin/cards/card/' . $headId) ?>"
                       target="_blank" rel="noopener">Print QR card</a>
                <?php endif; ?>
            </div>
            <?php /* States the guarantee that Close is safe, instead of asking the worker to
                     infer it from a button color. manage-family-modal.js fills it on each draft save. */ ?>
            <span class="text-muted small ms-auto" data-family-draft-status aria-live="polite"></span>
            <button class="btn btn-primary" type="submit" data-family-save <?= $saveDisabled ? 'disabled aria-disabled="true"' : '' ?>><?= esc($submitLabel) ?></button>
        </footer>

        <?php /* Truncation sentinel — MUST stay the last named field in the form. A POST
                 clipped by PHP's max_input_vars drops trailing vars first, so if this does
                 not arrive the server knows member data was cut and refuses to save. */ ?>
        <input type="hidden" name="_form_end" value="1">
    </form>
</div>
