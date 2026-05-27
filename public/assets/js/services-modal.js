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
        const addButton = event.target.closest('.js-service-add-button');
        const updateButton = event.target.closest('.js-service-update-button');
        const archiveButton = event.target.closest('.js-service-archive-button');

        if (!addButton && !updateButton && !archiveButton) {
            return;
        }

        if (addButton) {
            const modal = document.getElementById('serviceEditorModal');
            const form = modal?.querySelector('form');

            if (!modal || !form) {
                return;
            }

            form.action = form.dataset.createAction;
            form.reset();
            document.getElementById('serviceEditorModalLabel').textContent = 'Add Service';
            document.querySelector('.js-service-editor-submit').textContent = 'Add';
            window.bootstrap.Modal.getOrCreateInstance(modal).show();
            return;
        }

        const selected = selectedService();

        if (!selected) {
            return;
        }

        if (updateButton) {
            const modal = document.getElementById('serviceEditorModal');
            const form = modal?.querySelector('form');

            if (!modal || !form) {
                return;
            }

            form.action = form.dataset.updateAction + '/' + selected.dataset.serviceId;
            document.getElementById('serviceEditorCategory').value = selected.dataset.category || '';
            document.getElementById('serviceEditorName').value = selected.dataset.name || '';
            document.getElementById('serviceEditorDescription').value = selected.dataset.description || '';
            document.getElementById('serviceEditorModalLabel').textContent = 'Update Service';
            document.querySelector('.js-service-editor-submit').textContent = 'Update';
            window.bootstrap.Modal.getOrCreateInstance(modal).show();
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
