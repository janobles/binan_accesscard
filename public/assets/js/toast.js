// Single reusable Bootstrap 5 toast (bottom-right). Styling comes from the
// vendored .toast/.toast-container rules in assets/sb-admin/css/styles.css;
// the DOM node itself is rendered once per layout by components/toast.php.
//
// Only one toast is ever on screen: window.showToast(message, variant, opts)
// swaps the same node's variant/text and (re)shows it instead of spawning a
// new instance each call — e.g. call showToast('Generating…', 'primary'),
// then later showToast('Cards generated.', 'success') to replace it.
//
// Server-rendered flash messages: Partials/flash-toasts.php drops a hidden
// [data-flash-toast-data] marker with the flash text; initFlashToasts() picks
// it up on DOMContentLoaded and shows it the same way.
(function (window, document) {
    var toastEl = null;
    var bodyEl = null;
    var instance = null;
    var hideTimer = null;
    var ready = false;

    function ensureToast() {
        if (ready) {
            return toastEl !== null;
        }

        ready = true;
        toastEl = document.querySelector('[data-app-toast]');

        if (!toastEl) {
            return false;
        }

        bodyEl = toastEl.querySelector('[data-app-toast-body]');

        if (window.bootstrap && window.bootstrap.Toast) {
            // autohide is managed by us (hideTimer) so a new call can always
            // replace the pending hide instead of racing Bootstrap's own timer.
            instance = new window.bootstrap.Toast(toastEl, { autohide: false });
        }

        var closeBtn = toastEl.querySelector('.btn-close');

        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                window.clearTimeout(hideTimer);
            });
        }

        return true;
    }

    function hideToast() {
        if (instance) {
            instance.hide();
        } else if (toastEl) {
            toastEl.classList.remove('show');
        }
    }

    function showToast(message, variant, opts) {
        opts = opts || {};

        if (!ensureToast()) {
            return;
        }

        window.clearTimeout(hideTimer);

        toastEl.className = 'toast align-items-center text-bg-' + (variant || 'success') + ' border-0';
        bodyEl.textContent = message;

        if (instance) {
            instance.show();
        } else {
            toastEl.classList.add('show');
        }

        if (opts.autohide !== false) {
            hideTimer = window.setTimeout(hideToast, opts.delay || 15000);
        }
    }

    function initFlashToasts(root) {
        (root || document).querySelectorAll('[data-flash-toast-data]').forEach(function (marker) {
            var success = marker.getAttribute('data-flash-success');
            var error = marker.getAttribute('data-flash-error');

            // Only one toast can show at once: an error takes priority over a
            // success on the same request (rare, but favors the actionable one).
            if (error) {
                showToast(error, 'danger');
            } else if (success) {
                showToast(success, 'success');
            }

            marker.remove();
        });
    }

    window.showToast = showToast;
    window.initFlashToasts = initFlashToasts;

    document.addEventListener('DOMContentLoaded', function () { initFlashToasts(document); });
})(window, document);
