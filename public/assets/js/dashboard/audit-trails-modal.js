(function (window) {
    if (typeof window.registerDashboardModal !== 'function') {
        return;
    }

    window.registerDashboardModal({
        namespace: 'audit',
        triggerSelector: '.js-open-audit-modal',
        defaultTitle: 'Audit Trails',
        loadingMarkup: '<div class="family-modal-loading" role="status" aria-live="polite"><div class="spinner-border text-primary" aria-hidden="true"></div><span>Loading audit trails...</span></div>',
        errorMarkup: '<div class="alert alert-danger mb-0">Unable to load audit trails. Please try again.</div>'
    });
})(window);
