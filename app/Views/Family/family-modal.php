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
 * Sectors is a fixed list of ten, so it needs no scroll region: a two-column grid
 * shows all of them at once. $fieldName is the posted name (head vs member row) and
 * $idPrefix keeps checkbox ids unique between the head and each member row.
 */
$renderSectorGrid = static function (string $fieldName, array $selectedIds, string $idPrefix) use ($sectorCatalog, $sectorLabel): string {
    ob_start();
    ?>
    <div class="row row-cols-1 row-cols-sm-2 g-1" data-family-sector-grid>
        <?php if ($sectorCatalog === []): ?>
            <div class="col"><p class="text-muted mb-0">No sectors available.</p></div>
        <?php endif; ?>
        <?php foreach ($sectorCatalog as $sectorGroup): ?>
            <?php foreach (array_values((array) $sectorGroup) as $sector): ?>
                <?php
                $sector = (array) $sector;
                $sectorId = (string) ($sector['sectorID'] ?? $sector['id'] ?? '');
                $label = $sectorLabel($sector);

                if ($sectorId === '' || $label === '') {
                    continue;
                }

                $isArchived = ! empty($sector['is_archived']);
                $choiceId = $idPrefix . 'Sector' . $sectorId;
                ?>
                <div class="col">
                    <div class="form-check family-choice<?= $isArchived ? ' family-choice--archived' : '' ?>">
                        <input class="form-check-input" type="checkbox" id="<?= esc($choiceId, 'attr') ?>" name="<?= esc($fieldName, 'attr') ?>" value="<?= esc($sectorId, 'attr') ?>" data-label="<?= esc($label, 'attr') ?>" data-sector-code="<?= esc((string) ($sector['shortcode'] ?? $sector['code'] ?? ''), 'attr') ?>" data-sector-name="<?= esc((string) ($sector['name'] ?? ''), 'attr') ?>"<?= $isArchived ? ' data-archived="1"' : '' ?> <?= in_array($sectorId, $selectedIds, true) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="<?= esc($choiceId, 'attr') ?>"><?= esc($label) ?><?php if ($isArchived): ?> <span class="family-choice-badge">Archived</span><?php endif; ?></label>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
    <?php
    return (string) ob_get_clean();
};

/**
 * Services span several categories, so each category is an accordion item.
 * data-bs-parent is deliberately omitted so more than one can stay open;
 * manage-family-modal.js opens the categories that match the ticked sectors or
 * already hold a tick, and closes them again when that stops being true. Nothing
 * is hidden: a category matching no sector stays collapsed but present, because
 * programs like Financial Assistance apply regardless of sector.
 */
$renderServiceAccordion = static function (string $fieldName, array $selectedIds, string $accordionId, string $idPrefix) use ($servicesByCategory, $serviceLabel): string {
    ob_start();
    ?>
    <div class="mb-2">
        <input type="search" class="form-control form-control-sm" placeholder="Filter programs..." aria-label="Filter programs" data-family-service-filter>
    </div>
    <div class="accordion" id="<?= esc($accordionId, 'attr') ?>" data-family-service-accordion>
        <?php if ($servicesByCategory === []): ?>
            <p class="text-muted mb-0">No services available.</p>
        <?php endif; ?>
        <?php $categoryIndex = 0; ?>
        <?php foreach ($servicesByCategory as $category => $services): ?>
            <?php
            $categoryIndex++;
            $panelId = $accordionId . 'Panel' . $categoryIndex;
            ?>
            <div class="accordion-item" data-family-service-item>
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#<?= esc($panelId, 'attr') ?>" aria-expanded="false" aria-controls="<?= esc($panelId, 'attr') ?>"><?= esc((string) $category) ?></button>
                </h2>
                <div id="<?= esc($panelId, 'attr') ?>" class="accordion-collapse collapse" data-family-service-panel data-service-category="<?= esc((string) $category, 'attr') ?>">
                    <div class="accordion-body py-2">
                        <div class="row row-cols-1 row-cols-sm-2 g-1">
                            <?php foreach ((array) $services as $service): ?>
                                <?php
                                $service = (array) $service;
                                $serviceId = (string) ($service['serviceID'] ?? $service['id'] ?? '');
                                $label = $serviceLabel($service);

                                if ($serviceId === '' || $label === '') {
                                    continue;
                                }

                                $isArchived = ! empty($service['is_archived']);
                                $choiceId = $idPrefix . 'Service' . $serviceId;
                                ?>
                                <div class="col">
                                    <div class="form-check family-choice<?= $isArchived ? ' family-choice--archived' : '' ?>">
                                        <input class="form-check-input" type="checkbox" id="<?= esc($choiceId, 'attr') ?>" name="<?= esc($fieldName, 'attr') ?>" value="<?= esc($serviceId, 'attr') ?>" data-label="<?= esc($label, 'attr') ?>"<?= $isArchived ? ' data-archived="1"' : '' ?> <?= in_array($serviceId, $selectedIds, true) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="<?= esc($choiceId, 'attr') ?>"><?= esc($label) ?><?php if ($isArchived): ?> <span class="family-choice-badge">Archived</span><?php endif; ?></label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    return (string) ob_get_clean();
};

/**
 * The sectors-and-programs block, identical for the head and for every member.
 * It collapses to a one-line summary of what is ticked, expanded while creating
 * (the worker is ticking boxes off a paper form) and collapsed while editing,
 * where it is reference information.
 */
$renderChoicesBlock = static function (
    string $blockId,
    string $sectorFieldName,
    array $selectedSectors,
    string $serviceFieldName,
    array $selectedServices,
    string $idPrefix,
    bool $open
) use ($renderSectorGrid, $renderServiceAccordion): string {
    ob_start();
    ?>
    <div class="family-choices" data-family-choices-block>
        <button class="family-choices-toggle" type="button"
                data-bs-toggle="collapse" data-bs-target="#<?= esc($blockId, 'attr') ?>"
                aria-expanded="<?= $open ? 'true' : 'false' ?>" aria-controls="<?= esc($blockId, 'attr') ?>">
            <i class="bi bi-chevron-down" aria-hidden="true"></i>
            <span data-family-choices-summary>Sectors and programs</span>
        </button>
        <div class="collapse<?= $open ? ' show' : '' ?>" id="<?= esc($blockId, 'attr') ?>" data-family-choices>
            <div class="row g-3 pt-2">
                <div class="col-12 col-lg-5">
                    <h5 class="family-column-title">Sectors</h5>
                    <?= $renderSectorGrid($sectorFieldName, $selectedSectors, $idPrefix) ?>
                </div>
                <div class="col-12 col-lg-7">
                    <h5 class="family-column-title">Services and Programs</h5>
                    <?= $renderServiceAccordion($serviceFieldName, $selectedServices, $blockId . 'Services', $idPrefix) ?>
                </div>
            </div>
        </div>
    </div>
    <?php
    return (string) ob_get_clean();
};

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
    $relationshipOptions,
    $renderChoicesBlock,
    $modalMode
): string {
    $i = (string) $index;
    $field = static fn (string $name): string => 'members[' . $i . '][' . $name . ']';
    $val = static fn (string $key): string => (string) ($m[$key] ?? '');
    $selectedSectors = array_map('strval', (array) ($m['sector_ids'] ?? []));
    $selectedServices = array_map('strval', (array) ($m['service_ids'] ?? []));

    ob_start();
    ?>
    <div class="family-person-card" data-family-member-row>
        <div class="family-person-card-header">
            <?php /* manage-family-modal.js keeps the number and name current as rows are
                     added, removed, or renamed. */ ?>
            <h3 class="family-person-card-title" data-family-member-title>Member</h3>
            <?php /* Row actions live in the same actions menu the records table uses, rather
                     than two competing outline buttons in colors that carry no role. */ ?>
            <div class="dropdown">
                <button class="btn btn-outline-secondary btn-sm actions-menu-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Member actions">
                    <i class="bi bi-three-dots" aria-hidden="true"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><button class="dropdown-item" type="button" data-family-set-head>Set as head</button></li>
                    <li><button class="dropdown-item text-danger" type="button" data-family-member-remove>Remove</button></li>
                </ul>
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
                <input class="form-control mt-2 js-other-input d-none" data-other-for="relationship" placeholder="Enter relationship">
                <div class="invalid-feedback" data-family-field-error></div>
            </div>
        </div>

        <?= $renderChoicesBlock(
            'familyMember' . $i . 'Choices',
            $field('sector_ids') . '[]',
            $selectedSectors,
            $field('service_ids') . '[]',
            $selectedServices,
            'familyMember' . $i,
            $modalMode !== 'update'
        ) ?>
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

    <?php /* novalidate suppresses the browser's own bubbles so Bootstrap's .invalid-feedback
             is what the worker sees. manage-family-modal.js drives the validation. */ ?>
    <form class="needs-validation" method="post" action="<?= esc($action, 'attr') ?>" autocomplete="off" novalidate>
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
                <?php $qrLocked = ! empty($qrLocked ?? false); ?>
                <?php /* The head is a person card with the same chrome as every member card, so
                         the two read as the same kind of thing. */ ?>
                <div class="family-person-card">
                    <div class="family-person-card-header">
                        <h3 class="family-person-card-title">Head of Family</h3>
                    </div>
                    <div class="row g-3">
                        <?php /* The QR is the first field of the record, so it leads the head's
                                 grid rather than sitting in a strip or a header of its own. */ ?>
                        <div class="col-12 col-md-6 col-xl-3">
                            <label class="form-label" for="<?= esc($fieldPrefix, 'attr') ?>HeadQr">QR Number</label>
                            <div class="input-group has-validation">
                                <input id="<?= esc($fieldPrefix, 'attr') ?>HeadQr" name="qr_control_no" class="form-control" type="text"
                                    inputmode="numeric" pattern="0*[1-9][0-9]{0,6}"
                                    title="QR number must be numeric and should not exceed 9,999,999"
                                    aria-describedby="<?= esc($fieldPrefix, 'attr') ?>HeadQrFeedback"
                                    data-qr-check-url="<?= esc($qrCheckUrl, 'attr') ?>"
                                    value="<?= esc($oldValue('qr_control_no'), 'attr') ?>"
                                    <?= $qrLocked ? 'readonly' : 'required' ?>>
                                <?php /* Makes the async uniqueness check legible: spinner while checking,
                                         tick or cross once it lands. Driven by manage-family-modal.js. */ ?>
                                <span class="input-group-text d-none" data-family-qr-status aria-live="polite"></span>
                                <div class="invalid-feedback" id="<?= esc($fieldPrefix, 'attr') ?>HeadQrFeedback" data-family-field-error></div>
                            </div>
                            <?php if ($qrLocked): ?>
                                <small class="text-muted">Locked: subsidy already recorded under this number.</small>
                            <?php endif; ?>
                        </div>

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

                        <?php /* Every record is a Biñan family, so the address stops at the barangay:
                                 house/unit no., street, and subdivision go in Address, and Barangay
                                 gets the room its longest option name needs. The break keeps the
                                 pair on one row of its own, so Barangay never wraps away from the
                                 address it belongs to. */ ?>
                        <div class="w-100 d-none d-xl-block"></div>
                        <div class="col-12 col-xl-8">
                            <label class="form-label" for="<?= esc($fieldPrefix, 'attr') ?>HeadAddress">Address</label>
                            <input id="<?= esc($fieldPrefix, 'attr') ?>HeadAddress" name="head_address" class="form-control" type="text" value="<?= esc($oldValue('head_address'), 'attr') ?>" aria-describedby="<?= esc($fieldPrefix, 'attr') ?>HeadAddressFeedback" minlength="2" required>
                            <div class="invalid-feedback" id="<?= esc($fieldPrefix, 'attr') ?>HeadAddressFeedback" data-family-field-error></div>
                        </div>
                        <div class="col-12 col-xl-4">
                            <label class="form-label" for="<?= esc($fieldPrefix, 'attr') ?>HeadBarangay">Barangay</label>
                            <select id="<?= esc($fieldPrefix, 'attr') ?>HeadBarangay" name="head_barangay" class="form-select" aria-describedby="<?= esc($fieldPrefix, 'attr') ?>HeadBarangayFeedback" required>
                                <?= $selectOptions($barangayOptions, $oldValue('head_barangay'), 'Barangay') ?>
                            </select>
                            <div class="invalid-feedback" id="<?= esc($fieldPrefix, 'attr') ?>HeadBarangayFeedback" data-family-field-error></div>
                        </div>
                    </div>

                    <?= $renderChoicesBlock(
                        $fieldPrefix . 'HeadChoices',
                        'sector_ids[]',
                        $selectedSectorIds,
                        'service_ids[]',
                        $selectedServiceIds,
                        $fieldPrefix . 'Head',
                        $modalMode !== 'update'
                    ) ?>
                </div>

                <section class="family-members-section">
                    <div class="alert alert-info d-flex align-items-center gap-2 py-2" role="alert">
                        <i class="bi bi-people" aria-hidden="true"></i>
                        <span>Family members in this household. Leave empty if there are none.</span>
                    </div>

                    <div data-family-members>
                        <?php foreach (array_values($existingMembers) as $i => $member): ?>
                            <?= $renderMemberRow($i, (array) $member) ?>
                        <?php endforeach; ?>
                    </div>

                    <template data-family-member-template>
                        <?= $renderMemberRow('__INDEX__') ?>
                    </template>

                    <?php /* Full width: adding the next member is the only action in this section
                             and the one the worker reaches for on every household. */ ?>
                    <div class="family-member-toolbar">
                        <button class="btn btn-success w-100" type="button" data-family-add-member data-next-index="<?= esc((string) count($existingMembers), 'attr') ?>">
                            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Add Member
                        </button>
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
