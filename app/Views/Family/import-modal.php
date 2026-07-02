<?php
/**
 * Excel import modal fragment, loaded into the shared dashboard modal
 * (#familyModalBody) by family-import.js. Lets a worker upload a filled .xlsx
 * (from the downloadable template) to bulk-create family records.
 *
 * @var string $action      POST endpoint for the import (role-aware).
 * @var string $templateUrl GET endpoint that streams the blank template.
 */
$action      = (string) ($action ?? '');
$templateUrl = (string) ($templateUrl ?? '');
?>
<div data-family-import>
    <p class="mb-2">
        Bulk-import families from Excel. Each row is one person; mark every head of
        family with <strong>Relationship = Head</strong> and give a family's members the
        same <strong>FamilyNo</strong>.
    </p>

    <a class="btn btn-sm btn-outline-secondary mb-3" href="<?= esc($templateUrl, 'attr') ?>">
        <i class="bi bi-download" aria-hidden="true"></i> Download Excel template
    </a>

    <form data-import-form action="<?= esc($action, 'attr') ?>" method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <div class="mb-3">
            <label class="form-label" for="familyImportFile">Excel file (.xlsx)</label>
            <input
                class="form-control"
                type="file"
                id="familyImportFile"
                name="import_file"
                accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                required
            >
        </div>

        <div data-import-results aria-live="polite"></div>

        <div class="d-flex justify-content-end gap-2">
            <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Close</button>
            <button class="btn btn-primary" type="submit" data-import-submit>Import</button>
        </div>
    </form>
</div>
