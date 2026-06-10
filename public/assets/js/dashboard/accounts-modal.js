// Account management page behaviour:
//   1. Registers the Account Management panel with the shared dashboard modal
//      loader so clicking .js-open-accounts-modal loads it via AJAX.
//      Account status confirmations are handled by view-interactions.js so they
//      work in both full-page and AJAX-loaded account views.
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

})(window, document);
