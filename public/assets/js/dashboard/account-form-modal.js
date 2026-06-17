// Registers topbar My Account triggers with the shared dashboard modal loader.
(function (window) {
    if (typeof window.registerDashboardModal !== 'function') {
        return;
    }

    window.registerDashboardModal({
        namespace: 'account-form',
        triggerSelector: '.js-open-my-account-modal',
        defaultTitle: 'My Account',
        loadingMarkup: '<div class="family-modal-loading" role="status" aria-live="polite"><div class="spinner-border text-primary" aria-hidden="true"></div><span>Loading your account...</span></div>',
        errorMarkup: '<div class="alert alert-danger mb-0">Unable to load your account. Please try again.</div>'
    });
})(window);
