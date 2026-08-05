<?php
/**
 * Import upload (Family Records > Import Excel, step 1). Takes a filled .xlsx saved from
 * our own template and queues it for staging; nothing is written to member here.
 *
 * The form markup is the same [data-family-import] contract family-import.js has always
 * driven, so the upload posts over AJAX and its progress toast carries the operator to
 * step 2 when the staging job finishes.
 *
 * Its endpoints come from FamilyImportController::importForm(), which builds them
 * from the flat record routes.
 */
$action      = (string) ($action ?? site_url('records/import'));
$templateUrl = (string) ($templateUrl ?? site_url('records/template'));
?>
<?php /* Centred: this step is one narrow card on an otherwise empty page, and left
         against the title it read as a fragment of a wider layout that never
         arrives. The review step after it stays full width, since it is a table. */ ?>
<div data-family-import class="family-import-upload">
    <?= view('components/stepper', [
        'orientation' => 'horizontal',
        'label'       => 'Import progress',
        'steps'       => [
            ['label' => 'Upload', 'state' => 'current'],
            ['label' => 'Review and Fix'],
        ],
    ]) ?>

    <div class="row justify-content-center mt-3">
        <div class="col-lg-8 col-xl-7">
            <div class="card border">
                <div class="card-body p-4">
                    <p class="text-muted mb-4">
                        Please fill out the provided template with the family records. To group members into one household, assign them the same <strong>Family Number</strong> and make sure to indicate the <strong>Head</strong> of the family in the Relationship column.
                    </p>

                    <a class="btn btn-outline-secondary mb-4" href="<?= esc($templateUrl, 'attr') ?>">
                        Download Excel template
                    </a>

                    <form data-import-form action="<?= esc($action, 'attr') ?>" method="post" enctype="multipart/form-data">
                        <?= csrf_field() ?>

                        <div class="mb-4">
                            <label class="form-label fw-bold" for="familyImportFile">Excel file (.xlsx)</label>
                            <input
                                class="form-control"
                                type="file"
                                id="familyImportFile"
                                name="import_file"
                                accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                                required
                            >
                            <div class="form-text">
                                We will safely scan your file for errors first. You will be able to review the records before they are saved to the system.
                            </div>
                        </div>

                        <div>
                            <div class="d-flex flex-wrap justify-content-end gap-2 mt-2">
                                <a class="btn btn-outline-secondary" href="<?= esc(site_url('records'), 'attr') ?>">Cancel</a>
                                <button class="btn btn-primary" type="submit" data-import-submit>
                                    Upload and scan file
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
