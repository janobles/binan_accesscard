<?php
/**
 * Import upload (Family Records > Import Excel, step 1). Takes a filled .xlsx saved from
 * our own template and queues it for staging; nothing is written to member here.
 *
 * The form markup is the same [data-family-import] contract family-import.js has always
 * driven, so the upload posts over AJAX and its progress toast carries the operator to
 * step 2 when the staging job finishes.
 *
 * @var string $action      POST endpoint that queues the upload.
 * @var string $templateUrl GET endpoint that streams the blank template.
 */
$action      = (string) ($action ?? site_url('records/import'));
$templateUrl = (string) ($templateUrl ?? site_url('records/template'));
?>
<div data-family-import>
    <ul class="nav nav-pills segmented-tabs mb-4" role="tablist">
        <li class="nav-item"><span class="nav-link active">1. Upload</span></li>
        <li class="nav-item"><span class="nav-link disabled">2. Review and Fix</span></li>
        <li class="nav-item"><span class="nav-link disabled">3. Done</span></li>
    </ul>

    <div class="row">
        <div class="col-lg-7">
            <div class="card shadow-sm">
                <div class="card-body">
                    <p>
                        Fill the template, mark each head as <strong>Relationship = Head</strong>,
                        and give every person of one household the same <strong>FamilyNo</strong>.
                    </p>

                    <a class="btn btn-outline-secondary mb-3" href="<?= esc($templateUrl, 'attr') ?>">
                        <i class="bi bi-file-earmark-arrow-down me-1" aria-hidden="true"></i>Download Excel template
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
                            <div class="form-text">
                                Nothing is saved yet. The file is checked first, then you review it.
                            </div>
                        </div>

                        <div data-import-results aria-live="polite"></div>

                        <div class="d-flex flex-wrap justify-content-end gap-2 mt-3">
                            <a class="btn btn-outline-secondary" href="<?= esc(site_url('records'), 'attr') ?>">Cancel</a>
                            <button class="btn btn-primary" type="submit" data-import-submit>
                                <i class="bi bi-file-earmark-arrow-up me-1" aria-hidden="true"></i>Upload and check
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
