// Registers family management screens with the shared dashboard modal loader.
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

(function (window, document) {
    document.addEventListener('submit', function (event) {
        const form = event.target.closest('.js-family-record-action-form');

        if (!form) {
            return;
        }

        const familyName = (form.dataset.familyName || 'this family record').trim();
        const actionLabel = (form.dataset.actionLabel || 'Archive').trim();
        const actionPast = (form.dataset.actionPast || 'archived').trim();
        const fallback = actionLabel + ' ' + familyName + '? This keeps the record in the database, marks it as ' + actionPast + ', and hides it from active lists.';
        const message = (form.dataset.confirmMessage || '').trim() || fallback;

        if (!window.confirm(message)) {
            event.preventDefault();
        }
    });
})(window, document);
