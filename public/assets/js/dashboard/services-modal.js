(function (window) {
    if (typeof window.registerDashboardModal !== 'function') {
        return;
    }

    window.registerDashboardModal({
        namespace: 'services',
        triggerSelector: '.js-open-services-modal',
        defaultTitle: 'Service Management',
        loadingMarkup: '<div class="family-modal-loading" role="status" aria-live="polite"><div class="spinner-border text-primary" aria-hidden="true"></div><span>Loading service management...</span></div>',
        errorMarkup: '<div class="alert alert-danger mb-0">Unable to load service management. Please try again.</div>',
        onLoaded: function (rootElement) {
            if (typeof window.initServiceManagementView === 'function') {
                window.initServiceManagementView(rootElement);
            }
        }
    });
})(window);
