<?php
/**
 * Shared Add, Edit, Archive and Restore modal for services and programs, included
 * once by the service list page (Admin > Reference Data > Services). The category
 * options and code maps come from that page's scope, built by
 * service_management_view_data().
 *
 * One form serves all four actions: services-modal.js swaps the form action between
 * the four data-*-action URLs. Two maps ride along on the form: data-existing-codes
 * for the browser-side duplicate check, and data-next-code-map so picking a category
 * can prefill the next free service code for that category's prefix. Both are
 * conveniences; Lookups\ServiceController re-checks server side.
 */
?>
<div class="modal fade" id="serviceActionModal" tabindex="-1" aria-labelledby="serviceActionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                        <form
                                method="post"
                                data-create-action="<?= site_url('reference-data/services/create') ?>"
                                data-update-action="<?= site_url('reference-data/services/update') ?>"
                                data-archive-action="<?= site_url('reference-data/services/archive') ?>"
                                data-restore-action="<?= site_url('reference-data/services/restore') ?>"
                                data-next-code-map="<?= esc(json_encode((object) ($serviceNextCodeMap ?? [])), 'attr') ?>"
                                data-existing-codes="<?= esc(json_encode(array_values($existingShortcodes ?? [])), 'attr') ?>">
                                <?= csrf_field() ?>
                                <div class="modal-header">
                                        <h5 class="modal-title" id="serviceActionModalLabel">Service or Program</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                        <div class="js-service-form-fields">
                                                <div class="mb-3">
                                                        <label class="form-label" for="serviceModalShortcode">Code</label>
                                                        <input class="form-control text-uppercase" id="serviceModalShortcode" name="shortcode" maxlength="30" placeholder="e.g. FA1, SWPS1, EDA1, 4PS" required>
                                                        <div class="form-text">Short code used when importing families from Excel. Must be unique.</div>
                                                        <div class="invalid-feedback d-block d-none js-service-code-error">Duplicate code - please enter another code.</div>
                                                </div>
                                                <div class="mb-3">
                                                        <label class="form-label" for="serviceModalCategory">Category</label>
                                                        <select class="form-select js-management-other-select" id="serviceModalCategory" name="category" data-other-input="#serviceModalCategoryOther" required>
                                                                <option value="">Select</option>
                                                                <?php foreach (($serviceCategoryOptions ?? []) as $category): ?>
                                                                        <option value="<?= esc((string) $category) ?>"><?= esc((string) $category) ?></option>
                                                                <?php endforeach; ?>
                                                                <option value="__other__">Others</option>
                                                        </select>
                                                        <input class="form-control mt-2 d-none" id="serviceModalCategoryOther" name="category_other" placeholder="Type new category">
                                                </div>
                                                <div class="mb-3">
                                                        <label class="form-label" for="serviceModalName">Name</label>
                                                        <input class="form-control" id="serviceModalName" name="name" required>
                                                </div>
                                                <div class="mb-0">
                                                        <label class="form-label" for="serviceModalDescription">Description</label>
                                                        <textarea class="form-control" id="serviceModalDescription" name="description" rows="3"></textarea>
                                                </div>
                                        </div>
                                        <div class="alert alert-warning mb-0 d-none js-service-archive-message">
                                                Archive <strong class="js-service-archive-name">this service or program</strong>? This will be blocked if records are already using it.
                                        </div>
                                        <div class="alert alert-info mb-0 d-none js-service-restore-message">
                                                Restore <strong class="js-service-restore-name">this service or program</strong>? It will become active again and available for assignment.
                                        </div>
                                </div>
                                <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-success js-service-modal-submit">Add</button>
                                </div>
                        </form>
                </div>
        </div>
</div>