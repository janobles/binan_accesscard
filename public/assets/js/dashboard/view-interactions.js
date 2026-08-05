// Re-initialisable shared interaction bindings that work inside AJAX-loaded modal
// content (they cannot rely on DOMContentLoaded which fires only once):
//   - Account status forms: a styled #accountStatusModal (Accounts/status-confirm-modal.php)
//     confirms enable/disable before .js-account-status-form submits, falling back
//     to window.confirm when Bootstrap or the modal markup is unavailable.
//
// Connected to:
//   - Views  : Admin/layout.php (audit tab, accounts tab),
//              Accounts/status-confirm-modal.php - #accountStatusModal
//   - Backend: GET audit-trails, POST accounts/disable|enable
//   - Exposes: window.initViewInteractions(rootElement) for re-init after
//              AJAX-loaded content replaces the DOM
(function (window, document) {
    // Status form whose submission is paused while the confirmation modal is open.
    var pendingStatusForm = null;

    function accountStatusModalEl() {
        return document.getElementById('accountStatusModal');
    }
    function bindAccountStatusForms(root) {
        root.querySelectorAll('.js-account-status-form').forEach(function (form) {
            if (form.dataset.statusFormBound === '1') {
                return;
            }

            form.dataset.statusFormBound = '1';
            form.addEventListener('submit', function (event) {
                const message = form.dataset.confirmMessage || 'Update this account status?';
                const modalEl = accountStatusModalEl();

                // Graceful fallback when the styled modal isn't available.
                if (!modalEl || !window.bootstrap || !window.bootstrap.Modal) {
                    if (!window.confirm(message)) {
                        event.preventDefault();
                    }

                    return;
                }

                event.preventDefault();
                pendingStatusForm = form;

                const normalized = message.trim().toLowerCase();
                const isDisable = normalized.indexOf('disable') === 0;
                const isEnable = normalized.indexOf('enable') === 0;
                const title = modalEl.querySelector('#accountStatusModalLabel');
                const messageEl = modalEl.querySelector('.js-account-status-message');
                const confirmBtn = modalEl.querySelector('.js-account-status-confirm');

                if (title) {
                    title.textContent = isDisable ? 'Disable Account' : (isEnable ? 'Enable Account' : 'Update Account Status');
                }

                if (messageEl) {
                    messageEl.textContent = message;
                }

                if (confirmBtn) {
                    confirmBtn.textContent = isDisable ? 'Disable' : (isEnable ? 'Enable' : 'Confirm');
                    confirmBtn.classList.toggle('btn-danger', isDisable);
                    confirmBtn.classList.toggle('btn-success', isEnable);
                    confirmBtn.classList.toggle('btn-primary', !isDisable && !isEnable);
                }

                window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
            });
        });
    }

    // Confirm button inside #accountStatusModal: submit the stashed form. Native
    // submit() does not re-fire the delegated submit listener, so the modal does
    // not reopen and no second dialog appears. Bound once at script load.
    document.addEventListener('click', function (event) {
        if (!event.target.closest('.js-account-status-confirm')) {
            return;
        }

        const form = pendingStatusForm;
        const modalEl = accountStatusModalEl();

        pendingStatusForm = null;

        if (modalEl && window.bootstrap && window.bootstrap.Modal) {
            const instance = window.bootstrap.Modal.getInstance(modalEl);

            if (instance) {
                instance.hide();
            }
        }

        if (form) {
            form.submit();
        }
    });

    // Drop the stashed form if the user dismisses the modal (Cancel / backdrop / Esc).
    document.addEventListener('hidden.bs.modal', function (event) {
        if (event.target && event.target.id === 'accountStatusModal') {
            pendingStatusForm = null;
        }
    });

    // Bootstrap tooltips are opt-in. Sector shortcode badges carry the full sector
    // name as their tooltip, so every page that prints them needs this; tables that
    // re-render rows call it again for the new markup.
    function bindTooltips(root) {
        if (!window.bootstrap || typeof window.bootstrap.Tooltip !== 'function') {
            return;
        }

        root.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (element) {
            window.bootstrap.Tooltip.getOrCreateInstance(element);
        });
    }

    // The desktop sidebar toggle is a disclosure control, and an icon cannot say
    // whether the thing it controls is currently open.
    //
    // The state is read from body.sb-sidenav-toggled, which sb-admin's own
    // script flips. Watching the class rather than listening for the same click
    // is deliberate: DOMContentLoaded fires on document before it reaches
    // window, and sb-admin binds on window, so a click listener added here runs
    // BEFORE the class it wants to read has been changed.
    function bindSidebarToggle() {
        const toggle = document.getElementById('sidebarToggle');
        if (!toggle) {
            return;
        }

        function syncState() {
            const collapsed = document.body.classList.contains('sb-sidenav-toggled');

            toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            toggle.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
        }

        syncState();

        if (typeof MutationObserver === 'function') {
            new MutationObserver(syncState).observe(document.body, {
                attributes: true,
                attributeFilter: ['class'],
            });
        }
    }

    function initViewInteractions(rootElement) {
        const root = rootElement instanceof HTMLElement ? rootElement : document;

        bindAccountStatusForms(root);
        bindTooltips(root);
    }

    window.initViewInteractions = initViewInteractions;
    window.initTooltips = bindTooltips;

    document.addEventListener('DOMContentLoaded', function () {
        initViewInteractions(document);
        // Not part of initViewInteractions: that runs again for every modal
        // fragment, and the sidebar is on the page once.
        bindSidebarToggle();
    });
})(window, document);
