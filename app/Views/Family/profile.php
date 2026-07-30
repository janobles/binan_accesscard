<?php
/**
 * Family Profile page (Family Records > a household). The one surface for an
 * existing record: it both displays and edits. Data source:
 * FamilyController::profile(), documented as family_profile_view_data() in
 * app/Helpers/dashboard_view_helper.php.
 *
 * The head is the outer card and the members are nested inside it, which is the
 * containment the paper profiling form implies. Fields render as controls at all
 * times with one Save at the bottom; a Viewer session gets the same controls
 * disabled and no Save, and its update route is withheld by the manifest.
 */

use App\Libraries\Qr\ControlNumber;

$readOnly = (bool) ($readOnly ?? false);
$headId = (int) ($head['memberID'] ?? 0);
$formOptions = (array) ($formOptions ?? []);
?>
<div class="container-fluid px-4 py-4">
    <form method="post" action="<?= esc(site_url('records/' . $headId . '/update'), 'attr') ?>">
        <?= csrf_field() ?>

        <div class="card shadow-sm" data-head-card>
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <h2 class="h5 mb-0">Head of Family</h2>
                <span class="badge text-bg-secondary">
                    Control No. <?= esc(ControlNumber::format((int) ($controlNumber ?? 0))) ?>
                </span>
            </div>
            <div class="card-body">
                <?= view('Family/_fields', [
                    'head'        => $head,
                    'members'     => $members,
                    'readOnly'    => $readOnly,
                    'sectors'     => $sectors,
                    'services'    => $services,
                    'categories'  => $categories,
                    'formOptions' => $formOptions,
                ]) ?>
            </div>
            <?php if (! $readOnly): ?>
            <div class="card-footer text-end">
                <button class="<?= btn('save') ?>" type="submit" data-family-save>
                    <i class="bi bi-check2-circle me-1" aria-hidden="true"></i>Save Changes
                </button>
            </div>
            <?php endif; ?>
        </div>
    </form>
</div>
