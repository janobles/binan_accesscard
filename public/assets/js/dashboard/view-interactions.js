// Re-initialisable shared interaction bindings that work inside AJAX-loaded modal
// content (they cannot rely on DOMContentLoaded which fires only once):
//   - Audit filters: auto-submit .js-audit-filter-form when the action select changes
//   - Account status forms: a styled #accountStatusModal (Accounts/status-confirm-modal.php)
//     confirms enable/disable before .js-account-status-form submits, falling back
//     to window.confirm when Bootstrap or the modal markup is unavailable.
//
// Connected to:
//   - Views  : Admin/layout.php (audit tab, accounts tab),
//              Accounts/status-confirm-modal.php - #accountStatusModal
//   - Backend: GET admin/audit-trails, POST admin/accounts/disable|enable
//   - Exposes: window.initViewInteractions(rootElement) for re-init after
//              AJAX-loaded content replaces the DOM
(function (window, document) {
    // Status form whose submission is paused while the confirmation modal is open.
    var pendingStatusForm = null;

    function accountStatusModalEl() {
        return document.getElementById('accountStatusModal');
    }
    function bindDashboardSidebar() {
        const sidebar = document.getElementById('dashboard-sidebar');
        const toggles = document.querySelectorAll('#sidebarToggle, #sidebarToggleTop');

        if (!sidebar || toggles.length === 0 || sidebar.dataset.sidebarToggleBound === '1') {
            return;
        }

        sidebar.dataset.sidebarToggleBound = '1';

        const isMobile = function () {
            return window.matchMedia('(max-width: 48rem)').matches;
        };

        const setExpandedState = function () {
            const expanded = isMobile() ? sidebar.classList.contains('toggled') : !sidebar.classList.contains('toggled');

            toggles.forEach(function (toggle) {
                toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            });
        };

        const closeMobileSidebar = function () {
            if (!isMobile()) {
                return;
            }

            sidebar.classList.remove('toggled');
            document.body.classList.remove('sidebar-toggled');
            setExpandedState();
        };

        toggles.forEach(function (toggle) {
            toggle.addEventListener('click', function (event) {
                event.stopPropagation();
                sidebar.classList.toggle('toggled');
                document.body.classList.toggle('sidebar-toggled');
                setExpandedState();
            });
        });

        document.addEventListener('click', function (event) {
            if (
                isMobile()
                && sidebar.classList.contains('toggled')
                && !sidebar.contains(event.target)
                && !event.target.closest('#sidebarToggle, #sidebarToggleTop')
            ) {
                closeMobileSidebar();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeMobileSidebar();
            }
        });

        sidebar.querySelectorAll('a[href]').forEach(function (link) {
            link.addEventListener('click', function () {
                closeMobileSidebar();
            });
        });

        window.addEventListener('resize', function () {
            if (!isMobile()) {
                document.body.classList.remove('sidebar-toggled');
            }

            setExpandedState();
        });

        setExpandedState();
    }

    function bindAuditFilters(root) {
        root.querySelectorAll('.js-audit-action-filter').forEach(function (select) {
            if (select.dataset.auditFilterBound === '1') {
                return;
            }

            select.dataset.auditFilterBound = '1';
            select.addEventListener('change', function () {
                const form = select.closest('.js-audit-filter-form');

                if (form) {
                    form.submit();
                }
            });
        });
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

    function initViewInteractions(rootElement) {
        const root = rootElement instanceof HTMLElement ? rootElement : document;

        bindAuditFilters(root);
        bindAccountStatusForms(root);
    }

    window.initViewInteractions = initViewInteractions;

    document.addEventListener('DOMContentLoaded', function () {
        bindDashboardSidebar();
        initViewInteractions(document);
    });
})(window, document);
