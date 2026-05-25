(function (window) {
    if (typeof window.registerDashboardModal !== 'function') {
        return;
    }

    window.registerDashboardModal({
        namespace: 'sectors',
        triggerSelector: '.js-open-sectors-modal',
        defaultTitle: 'Sector List',
        loadingMarkup: '<div class="family-modal-loading" role="status" aria-live="polite"><div class="spinner-border text-primary" aria-hidden="true"></div><span>Loading sectors...</span></div>',
        errorMarkup: '<div class="alert alert-danger mb-0">Unable to load sectors. Please try again.</div>'
    });
})(window);
