// Registers the read-only family record modal with the shared dashboard loader.
(function (window) {
    'use strict';

    if (typeof window.registerDashboardModal !== 'function') {
        return;
    }

    window.registerDashboardModal({
        namespace: 'family',
        triggerSelector: '.js-open-family-view-modal',
        defaultTitle: 'View Record',
        loadingMarkup: '<div class="family-modal-loading" role="status" aria-live="polite"><div class="spinner-border text-primary" aria-hidden="true"></div><span>Loading record...</span></div>',
        errorMarkup: '<div class="alert alert-danger mb-0">Unable to load the record. Please try again.</div>'
    });

    window.registerDashboardModal({
        namespace: 'familyAdd',
        triggerSelector: '.js-open-family-add-modal',
        defaultTitle: 'New Family Record',
        loadingMarkup: '<div class="family-modal-loading" role="status" aria-live="polite"><div class="spinner-border text-primary" aria-hidden="true"></div><span>Loading form...</span></div>',
        errorMarkup: '<div class="alert alert-danger mb-0">Unable to load the form. Please try again.</div>'
    });
})(window);
