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

(function (window, document) {
    function syncOtherSelect(select, root) {
        if (!select) {
            return;
        }

        const input = (root || document).querySelector(select.dataset.otherInput || '');
        const isOther = String(select.value || '') === '__other__';

        if (!input) {
            return;
        }

        input.classList.toggle('d-none', !isOther);
        input.disabled = !isOther || select.disabled;
        input.required = isOther && !select.disabled;

        if (!isOther) {
            input.value = '';
        }
    }

    function setCategory(select, otherInput, value) {
        const normalized = String(value || '').trim();
        const hasOption = Array.from(select.options).some(function (option) {
            return option.value === normalized;
        });

        if (normalized === '' || hasOption) {
            select.value = normalized;

            if (otherInput) {
                otherInput.value = '';
            }

            syncOtherSelect(select, select.closest('.modal'));

            return;
        }

        select.value = '__other__';

        if (otherInput) {
            otherInput.value = normalized;
        }

        syncOtherSelect(select, select.closest('.modal'));
    }

    function openServiceModal(trigger) {
        const modal = document.getElementById('serviceActionModal');
        const form = modal ? modal.querySelector('form') : null;

        if (!modal || !form || !window.bootstrap) {
            return;
        }

        const mode = String(trigger.dataset.serviceMode || 'create');
        const fields = modal.querySelector('.js-service-form-fields');
        const archiveMessage = modal.querySelector('.js-service-archive-message');
        const title = modal.querySelector('#serviceActionModalLabel');
        const submit = modal.querySelector('.js-service-modal-submit');
        const category = modal.querySelector('#serviceModalCategory');
        const categoryOther = modal.querySelector('#serviceModalCategoryOther');
        const name = modal.querySelector('#serviceModalName');
        const description = modal.querySelector('#serviceModalDescription');
        const archiveName = modal.querySelector('.js-service-archive-name');
        const serviceId = String(trigger.dataset.serviceId || '').trim();
        const isArchive = mode === 'archive';

        form.reset();
        form.action = form.dataset.createAction || '';

        if (mode === 'update') {
            form.action = (form.dataset.updateAction || '').replace(/\/$/, '') + '/' + serviceId;
        } else if (isArchive) {
            form.action = (form.dataset.archiveAction || '').replace(/\/$/, '') + '/' + serviceId;
        }

        if (title) {
            title.textContent = mode === 'update' ? 'Update Service or Program' : (isArchive ? 'Archive Service or Program' : 'Add Service or Program');
        }

        if (submit) {
            submit.textContent = mode === 'update' ? 'Update Service or Program' : (isArchive ? 'Archive Service or Program' : 'Add Service or Program');
            submit.classList.toggle('btn-danger', isArchive);
            submit.classList.toggle('btn-primary', !isArchive);
        }

        if (fields) {
            fields.classList.toggle('d-none', isArchive);
        }

        if (archiveMessage) {
            archiveMessage.classList.toggle('d-none', !isArchive);
        }

        [category, name, description].forEach(function (field) {
            if (field) {
                field.disabled = isArchive;
                field.required = !isArchive && field.hasAttribute('required');
            }
        });

        if (categoryOther) {
            categoryOther.disabled = true;
            categoryOther.required = false;
            categoryOther.classList.add('d-none');
        }

        if (!isArchive) {
            setCategory(category, categoryOther, mode === 'update' ? trigger.dataset.serviceCategory : '');

            if (name) {
                name.value = mode === 'update' ? String(trigger.dataset.serviceName || '') : '';
            }

            if (description) {
                description.value = mode === 'update' ? String(trigger.dataset.serviceDescription || '') : '';
            }
        }

        if (archiveName) {
            archiveName.textContent = String(trigger.dataset.serviceName || 'this service or program');
        }

        window.bootstrap.Modal.getOrCreateInstance(modal).show();
    }

    document.addEventListener('click', function (event) {
        const trigger = event.target.closest('.js-service-modal-open');

        if (!trigger) {
            return;
        }

        openServiceModal(trigger);
    });

    document.addEventListener('change', function (event) {
        if (event.target.matches('#serviceModalCategory')) {
            syncOtherSelect(event.target, document.getElementById('serviceActionModal'));
        }
    });
})(window, document);
