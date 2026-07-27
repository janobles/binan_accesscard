<?php
/**
 * Cross-role fragment: emits a hidden marker carrying the current request's
 * success/error flashdata. assets/js/toast.js reads it on DOMContentLoaded and
 * shows each as a real Bootstrap toast (bottom-right), then removes the
 * marker. No visible markup here — do not add a fallback <noscript> alert;
 * every layout that includes this partial also loads toast.js.
 */
$flashSuccess = (string) session()->getFlashdata('success');
$flashError = (string) session()->getFlashdata('error');
?>
<?php if ($flashSuccess !== '' || $flashError !== ''): ?>
<div data-flash-toast-data hidden
    <?php if ($flashSuccess !== ''): ?>data-flash-success="<?= esc($flashSuccess, 'attr') ?>"<?php endif; ?>
    <?php if ($flashError !== ''): ?>data-flash-error="<?= esc($flashError, 'attr') ?>"<?php endif; ?>
></div>
<?php endif; ?>
