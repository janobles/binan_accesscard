<?php
/**
 * Single reusable Bootstrap toast shell (props-only, same pattern as
 * components/modal). Rendered once per layout; assets/js/toast.js targets
 * this one node for every notification on the page — each showToast() call
 * swaps its variant/text and (re)shows it rather than creating a new
 * instance, so only one toast is ever on screen at a time.
 */
?>
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1090" data-app-toast-container>
    <div class="toast align-items-center text-bg-success border-0" role="status" aria-live="polite" aria-atomic="true" data-app-toast>
        <div class="d-flex">
            <div class="toast-body" data-app-toast-body></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>
