(function (window) {
    if (typeof window.registerDashboardModal !== 'function') {
        return;
    }

    window.registerDashboardModal({
        namespace: 'services',
        triggerSelector: '.js-open-services-modal',
        defaultTitle: 'Service List',
        loadingMarkup: '<div class="family-modal-loading" role="status" aria-live="polite"><div class="spinner-border text-primary" aria-hidden="true"></div><span>Loading services...</span></div>',
        errorMarkup: '<div class="alert alert-danger mb-0">Unable to load services. Please try again.</div>'
    });
})(window);

(function (window, document) {
    function selectedService() {
        return document.querySelector('.js-service-select:checked');
    }

    function setServiceActionState(enabled) {
        document.querySelectorAll('.js-service-update-button, .js-service-archive-button').forEach(function (button) {
            button.disabled = !enabled;
        });
    }

    document.addEventListener('change', function (event) {
        if (event.target.matches('.js-service-select')) {
            setServiceActionState(true);
        }
    });

    document.addEventListener('click', function (event) {
        const updateButton = event.target.closest('.js-service-update-button');
        const archiveButton = event.target.closest('.js-service-archive-button');

        if (!updateButton && !archiveButton) {
            return;
        }

        const selected = selectedService();

        if (!selected) {
            return;
        }

        if (updateButton) {
            const form = document.querySelector('#serviceUpdateModal form');

            if (!form) {
                return;
            }

            form.action = form.dataset.actionBase + '/' + selected.dataset.serviceId;
            document.getElementById('serviceUpdateCategory').value = selected.dataset.category || '';
            document.getElementById('serviceUpdateName').value = selected.dataset.name || '';
            document.getElementById('serviceUpdateDescription').value = selected.dataset.description || '';
            window.bootstrap.Modal.getOrCreateInstance(document.getElementById('serviceUpdateModal')).show();
            return;
        }

        const form = document.querySelector('#serviceArchiveModal form');
        const label = document.querySelector('#serviceArchiveModal .js-service-archive-name');

        if (!form) {
            return;
        }

        form.action = form.dataset.actionBase + '/' + selected.dataset.serviceId;

        if (label) {
            label.textContent = selected.dataset.name || 'this service';
        }

        window.bootstrap.Modal.getOrCreateInstance(document.getElementById('serviceArchiveModal')).show();
    });
})(window, document);
