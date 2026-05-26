(function (window) {
    if (typeof window.registerDashboardModal !== 'function') {
        return;
    }

    window.registerDashboardModal({
        namespace: 'services',
        triggerSelector: '.js-open-services-modal',
        defaultTitle: 'Services and Programs Management',
        loadingMarkup: '<div class="family-modal-loading" role="status" aria-live="polite"><div class="spinner-border text-primary" aria-hidden="true"></div><span>Loading services and programs...</span></div>',
        errorMarkup: '<div class="alert alert-danger mb-0">Unable to load services and programs. Please try again.</div>'
    });
})(window);
