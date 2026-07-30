<?php
/**
 * Family Data Entry page (Family Records > Add Family). Creates a new household.
 *
 * The control number resolves before anything else renders: it is checked against
 * qr_control over the network, and gating on it means a pending or failed check
 * cannot wedge a save the way it did when the check ran alongside the other fields.
 *
 * Head and members then render together on one page rather than as wizard steps,
 * because each member's relationship is defined against the head and the officer is
 * transcribing from a single paper sheet where both are visible.
 */
?>
<div class="container-fluid px-4 py-4">
    <div class="row mb-4" data-control-number-gate data-qr-check-url="<?= esc(site_url('records/qr-check'), 'attr') ?>">
        <div class="col-md-6 col-lg-4">
            <label class="form-label" for="controlNumber">Control Number</label>
            <div class="input-group">
                <span class="input-group-text"><i class="bi bi-qr-code" aria-hidden="true"></i></span>
                <input type="text" class="form-control" id="controlNumber" name="control_no" required
                       inputmode="numeric" autocomplete="off">
            </div>
            <div class="form-text" data-control-number-status role="status" aria-live="polite"></div>
        </div>
    </div>

    <div class="row d-none" data-entry-body data-family-entry-form>
        <div class="col-lg-3 mb-4">
            <div class="list-group sticky-top family-entry-rail" id="entryRail">
                <a class="list-group-item list-group-item-action" href="#section-head">Head of Family</a>
                <a class="list-group-item list-group-item-action" href="#section-members">Household Members</a>
                <a class="list-group-item list-group-item-action" href="#section-sectors">Sectors</a>
                <a class="list-group-item list-group-item-action" href="#section-services">Services and Programs</a>
            </div>
            <button class="<?= btn('save') ?> w-100 mt-3" type="submit" form="familyEntryForm" data-family-save>
                <i class="bi bi-check2-circle me-1" aria-hidden="true"></i>Save Record
            </button>
        </div>

        <div class="col-lg-9" data-bs-spy="scroll" data-bs-target="#entryRail" data-bs-smooth-scroll="true" tabindex="0">
            <form id="familyEntryForm" method="post" action="<?= esc(site_url('records'), 'attr') ?>" novalidate>
                <?= csrf_field() ?>
                <?= view('Family/_fields', [
                    'head'        => $head,
                    'members'     => $members,
                    'readOnly'    => $readOnly,
                    'sectors'     => $sectors,
                    'services'    => $services,
                    'categories'  => $categories,
                    'formOptions' => $formOptions,
                ]) ?>
                <?php /* Truncation sentinel - MUST stay the last named field in the form. A
                         POST clipped by PHP's max_input_vars drops trailing vars first, so if
                         this does not arrive the server knows member data was cut and refuses
                         to save (FamilyController::submissionWasTruncated()). */ ?>
                <input type="hidden" name="members_meta_count" value="0" data-members-count>
                <input type="hidden" name="_form_end" value="1">
            </form>
        </div>
    </div>
</div>
