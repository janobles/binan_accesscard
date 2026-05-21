(function (window) {
    if (typeof window.registerDashboardModal !== 'function') {
        return;
    }

    window.registerDashboardModal({
        namespace: 'sectors',
        triggerSelector: '.js-open-sectors-modal',
        defaultTitle: 'Sector Management',
        loadingMarkup: '<div class="family-modal-loading" role="status" aria-live="polite"><div class="spinner-border text-primary" aria-hidden="true"></div><span>Loading sector management...</span></div>',
        errorMarkup: '<div class="alert alert-danger mb-0">Unable to load sector management. Please try again.</div>',
        onLoaded: function (rootElement) {
            if (typeof window.initSectorManagementView === 'function') {
                window.initSectorManagementView(rootElement);
            }
        }
    });
})(window);
