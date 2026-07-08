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
(function (document) {
    function hasFieldValue(field) {
        return String(field.value || '').trim() !== '';
    }

    function syncRequiredMarker(field) {
        if (!field.id || !field.required) {
            return;
        }

        var fieldGroup = field.closest('.account-field');
        if (!fieldGroup) {
            return;
        }

        var marker = fieldGroup.querySelector('.account-required-marker');
        if (!marker) {
            return;
        }

        marker.hidden = hasFieldValue(field);
    }

    function syncAccountRequiredMarkers(root) {
        var scope = root || document;
        var fields = scope.querySelectorAll('.edit-account-modal [required]');
        fields.forEach(syncRequiredMarker);
    }

    document.addEventListener('input', function (event) {
        if (event.target.closest('.edit-account-modal')) {
            syncRequiredMarker(event.target);
        }
    });

    document.addEventListener('change', function (event) {
        if (event.target.closest('.edit-account-modal')) {
            syncRequiredMarker(event.target);
        }
    });

    document.addEventListener('shown.bs.modal', function (event) {
        syncAccountRequiredMarkers(event.target);
    });

    document.addEventListener('DOMContentLoaded', function () {
        syncAccountRequiredMarkers(document);
    });
})(document);
