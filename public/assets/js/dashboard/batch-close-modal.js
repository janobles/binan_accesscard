// Intercepts the "Close batch" form and confirms via the styled #batchCloseModal
// (Admin/batch-close-modal.php) instead of the native window.confirm, falling
// back to window.confirm when Bootstrap or the modal markup is unavailable.
//
// Connected to:
//   - View   : Admin/distribution-batches-body.php - .js-batch-close-form
//              Admin/batch-close-modal.php - #batchCloseModal
//   - Backend: POST admin/batches/close/:id
(function (window, document) {
    // Form whose submission is paused while the confirmation modal is open.
    var pendingForm = null;

    function modalEl() {
        return document.getElementById('batchCloseModal');
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;

        if (!(form instanceof HTMLFormElement) || !form.classList.contains('js-batch-close-form')) {
            return;
        }

        var modal = modalEl();

        // Graceful fallback when the styled modal isn't available.
        if (!modal || !window.bootstrap || !window.bootstrap.Modal) {
            if (!window.confirm('Close this batch? Statistics reset for the next batch.')) {
                event.preventDefault();
            }

            return;
        }

        event.preventDefault();
        pendingForm = form;
        window.bootstrap.Modal.getOrCreateInstance(modal).show();
    });

    document.addEventListener('click', function (event) {
        if (!event.target.closest('.js-batch-close-confirm')) {
            return;
        }

        var form = pendingForm;
        var modal = modalEl();

        pendingForm = null;

        if (modal && window.bootstrap && window.bootstrap.Modal) {
            var instance = window.bootstrap.Modal.getInstance(modal);

            if (instance) {
                instance.hide();
            }
        }

        if (form) {
            // Native submit() does not re-fire the delegated submit listener, so
            // the modal does not reopen and no second dialog appears.
            form.submit();
        }
    });

    // Drop the stashed form if the user dismisses the modal (Cancel / backdrop / Esc).
    document.addEventListener('hidden.bs.modal', function (event) {
        if (event.target && event.target.id === 'batchCloseModal') {
            pendingForm = null;
        }
    });
})(window, document);
