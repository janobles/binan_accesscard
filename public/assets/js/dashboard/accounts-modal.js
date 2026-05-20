(function (window) {
    if (typeof window.registerDashboardModal !== 'function') {
        return;
    }

    window.registerDashboardModal({
        namespace: 'accounts',
        triggerSelector: '.js-open-accounts-modal',
        defaultTitle: 'Account Management',
        loadingMarkup: '<div class="family-modal-loading" role="status" aria-live="polite"><div class="spinner-border text-primary" aria-hidden="true"></div><span>Loading account management...</span></div>',
        errorMarkup: '<div class="alert alert-danger mb-0">Unable to load account management. Please try again.</div>'
    });
})(window);
