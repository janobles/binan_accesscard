// Account management page behaviour:
//   1. Registers the Account Management panel with the shared dashboard modal
//      loader so clicking .js-open-accounts-modal loads it via AJAX.
//   2. Shows a confirmation dialog before any .js-account-status-form is submitted
//      (enable / disable account actions).
//
// Connected to:
//   - dashboard-modal-loader.js : window.registerDashboardModal()
//   - Views  : Admin/accounts.php — .js-account-status-form buttons
//   - Backend: POST admin/accounts/disable|enable
//              (Accounts\AccountController::disableEmployee, ::enableEmployee)
// Account management page behavior.
(function (window, document) {
    if (typeof window.registerDashboardModal === 'function') {
        window.registerDashboardModal({
            namespace: 'accounts',
            triggerSelector: '.js-open-accounts-modal',
            defaultTitle: 'Account Management',
            loadingMarkup: '<div class="family-modal-loading" role="status" aria-live="polite"><div class="spinner-border text-primary" aria-hidden="true"></div><span>Loading account management...</span></div>',
            errorMarkup: '<div class="alert alert-danger mb-0">Unable to load account management. Please try again.</div>'
        });
    }

    document.addEventListener('submit', function (event) {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || !form.classList.contains('js-account-status-form')) {
            return;
        }

        const message = String(form.dataset.confirmMessage || 'Update this account status?').trim();

        if (!window.confirm(message)) {
            event.preventDefault();
        }
    });
})(window, document);
