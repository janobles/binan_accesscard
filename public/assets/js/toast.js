// Shared Bootstrap 5 toast helper (bottom-right stack). Styling comes from the
// vendored .toast/.toast-container rules in assets/sb-admin/css/styles.css.
//
// Two ways in:
//   - window.showToast(message, variant, opts) — call directly from any page
//     script for a JS-triggered notice (variant: success|danger|warning|primary...).
//     Returns { el, hide() } so a long-running action can swap a "working…"
//     toast for a result once it finishes.
//   - Server-rendered flash messages: Partials/flash-toasts.php drops a hidden
//     [data-flash-toast-data] marker with the flash text; initFlashToasts()
//     picks it up on DOMContentLoaded and shows it the same way.
(function (window, document) {
    var CONTAINER_ID = 'appToastContainer';

    function getContainer() {
        var el = document.getElementById(CONTAINER_ID);

        if (!el) {
            el = document.createElement('div');
            el.id = CONTAINER_ID;
            el.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            el.style.zIndex = '1090';
            document.body.appendChild(el);
        }

        return el;
    }

    function showToast(message, variant, opts) {
        opts = opts || {};

        var toast = document.createElement('div');
        toast.className = 'toast align-items-center text-bg-' + (variant || 'success') + ' border-0';
        toast.setAttribute('role', 'status');
        toast.setAttribute('aria-live', 'polite');
        toast.setAttribute('aria-atomic', 'true');
        toast.innerHTML =
            '<div class="d-flex">' +
            '<div class="toast-body"></div>' +
            '<button type="button" class="btn-close btn-close-white me-2 m-auto" aria-label="Close"></button>' +
            '</div>';
        toast.querySelector('.toast-body').textContent = message;

        getContainer().appendChild(toast);

        var closeBtn = toast.querySelector('.btn-close');
        var hasBootstrapToast = window.bootstrap && window.bootstrap.Toast;
        var instance = hasBootstrapToast
            ? new window.bootstrap.Toast(toast, {
                autohide: opts.autohide !== false,
                delay: opts.delay || 15000,
            })
            : null;

        function hide() {
            if (instance) {
                instance.hide();
            } else {
                toast.remove();
            }
        }

        toast.addEventListener('hidden.bs.toast', function () { toast.remove(); });
        closeBtn.addEventListener('click', hide);

        if (instance) {
            instance.show();
        } else {
            // Bootstrap JS unavailable: minimal fallback so the message still appears.
            toast.classList.add('show');
            if (opts.autohide !== false) {
                window.setTimeout(function () { toast.remove(); }, opts.delay || 15000);
            }
        }

        return { el: toast, hide: hide };
    }

    function initFlashToasts(root) {
        (root || document).querySelectorAll('[data-flash-toast-data]').forEach(function (marker) {
            var success = marker.getAttribute('data-flash-success');
            var error = marker.getAttribute('data-flash-error');

            if (success) { showToast(success, 'success'); }
            if (error) { showToast(error, 'danger'); }

            marker.remove();
        });
    }

    window.showToast = showToast;
    window.initFlashToasts = initFlashToasts;

    document.addEventListener('DOMContentLoaded', function () { initFlashToasts(document); });
})(window, document);
