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
    <p>
        Upload an Excel file. Mark each head as <strong>Relationship = Head</strong>
        and use the same <strong>FamilyNo</strong> for family members.
    </p>

    <a class="btn btn-sm btn-outline-secondary mb-2" href="<?= esc($templateUrl, 'attr') ?>">
        Download Excel template
    </a>

    <form data-import-form action="<?= esc($action, 'attr') ?>" method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <div class="mb-2">
            <label class="form-label" for="familyImportFile">Excel file (.xlsx)</label>
            <input
                class="form-control form-control-sm"
                type="file"
                id="familyImportFile"
                name="import_file"
                accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                required
            >
        </div>

        <div data-import-results aria-live="polite"></div>

        <div class="d-flex justify-content-end gap-2 mt-3">
            <button class="btn btn-sm btn-secondary" type="button" data-bs-dismiss="modal">Close</button>
            <button class="btn btn-sm btn-primary" type="submit" data-import-submit>Import</button>
        </div>
    </form>
</div>
