(function (window) {
    if (typeof window.registerDashboardModal !== 'function') {
        return;
    }

    window.registerDashboardModal({
        namespace: 'family',
        triggerSelector: '.js-open-family-modal, .js-open-family-list, .js-open-family-view-modal, .js-open-family-edit-modal',
        defaultTitle: 'Manage Family',
        loadingMarkup: '<div class="family-modal-loading" role="status" aria-live="polite"><div class="spinner-border text-primary" aria-hidden="true"></div><span>Loading family form...</span></div>',
        errorMarkup: '<div class="alert alert-danger mb-0">Unable to load the family form. Please try again.</div>',
        onLoaded: function (container) {
            if (typeof window.initFamilyForm === 'function') {
                window.initFamilyForm(container);
            }
        }
    });
})(window);
