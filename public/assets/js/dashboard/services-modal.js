// Drives the Services & Programs management UI across two contexts:
//   1. Dashboard modal: registers the Services panel with dashboard-modal-loader.js
//      so clicking .js-open-services-modal loads it via AJAX.
//   2. Services admin page (#serviceActionModal): handles create / update / archive /
//      restore in a single shared modal. Syncs the "Other (custom)" category freetext
//      input when the category select switches to/from __other__. Submission is
//      blocked when the typed shortcode already exists (data-existing-codes).
//      Third IIFE manages the Active / Archived row toggle on the lookups page.
//
// Connected to:
//   - dashboard-modal-loader.js : window.registerDashboardModal()
//   - Backend : POST admin/services/create|update|archive|restore
//               (Lookups\ServiceController, via the modal's data-*-action attributes)
//   - Views   : Views/Lookups/service-modal.php — #serviceActionModal, .js-service-modal-open
//               buttons carry data-service-mode, data-service-id, data-service-name, etc.
//   - Data    : PHP embeds data-existing-codes on the <form>
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
    function parseJson(value, fallback) {
        try {
            const parsed = JSON.parse(value || '');

            return parsed === null ? fallback : parsed;
        } catch (error) {
            return fallback;
        }
    }

    // Auto-fills the Code from the selected category's next suggested code
    // (data-next-code-map is keyed by category NAME, e.g. "Bata (Children)" => "B4").
    // Only while ADDING — never renumbers an existing service on edit. Picking the
    // blank/"Others" option clears the code so a custom category code can be typed.
    function autofillServiceCode(select, modal) {
        const form = modal ? modal.querySelector('form') : null;
        const code = modal ? modal.querySelector('#serviceModalShortcode') : null;

        if (!form || !code || String(form.dataset.serviceMode || 'create') !== 'create') {
            return;
        }

        const value = String(select.value || '');

        if (value === '' || value === '__other__') {
            code.value = '';

            return;
        }

        const map = parseJson(form.dataset.nextCodeMap, {});

        if (Object.prototype.hasOwnProperty.call(map, value)) {
            code.value = String(map[value] || '');
        }
    }

    // Inline duplicate check: compares the typed code against existing codes,
    // excluding the service's own code while editing. Toggles the error message
    // and the submit button accordingly. Mirrors sectors-modal.js's validateCode.
    function validateCode(modal) {
        const form = modal.querySelector('form');
        const codeInput = modal.querySelector('#serviceModalShortcode');
        const errorEl = modal.querySelector('.js-service-code-error');
        const submit = modal.querySelector('.js-service-modal-submit');

        if (!form || !codeInput || codeInput.disabled) {
            return;
        }

        const existing = parseJson(form.dataset.existingCodes, []).map(function (code) {
            return String(code || '').toUpperCase();
        });
        const ownCode = String(form.dataset.currentCode || '').toUpperCase();
        const value = String(codeInput.value || '').trim().toUpperCase();
        const isDuplicate = value !== '' && value !== ownCode && existing.indexOf(value) !== -1;

        if (errorEl) {
            errorEl.classList.toggle('d-none', !isDuplicate);
        }

        codeInput.classList.toggle('is-invalid', isDuplicate);

        if (submit) {
            submit.disabled = isDuplicate;
        }
    }

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
        const restoreMessage = modal.querySelector('.js-service-restore-message');
        const title = modal.querySelector('#serviceActionModalLabel');
        const submit = modal.querySelector('.js-service-modal-submit');
        const category = modal.querySelector('#serviceModalCategory');
        const categoryOther = modal.querySelector('#serviceModalCategoryOther');
        const shortcode = modal.querySelector('#serviceModalShortcode');
        const name = modal.querySelector('#serviceModalName');
        const description = modal.querySelector('#serviceModalDescription');
        const archiveName = modal.querySelector('.js-service-archive-name');
        const restoreName = modal.querySelector('.js-service-restore-name');
        const serviceId = String(trigger.dataset.serviceId || '').trim();
        const isArchive = mode === 'archive';
        const isRestore = mode === 'restore';
        const isAction = isArchive || isRestore;
        const existingCode = mode === 'update' ? String(trigger.dataset.serviceShortcode || '') : '';

        form.reset();
        form.action = form.dataset.createAction || '';
        form.dataset.serviceMode = mode;
        form.dataset.currentCode = existingCode;

        if (mode === 'update') {
            form.action = (form.dataset.updateAction || '').replace(/\/$/, '') + '/' + serviceId;
        } else if (isArchive) {
            form.action = (form.dataset.archiveAction || '').replace(/\/$/, '') + '/' + serviceId;
        } else if (isRestore) {
            form.action = (form.dataset.restoreAction || '').replace(/\/$/, '') + '/' + serviceId;
        }

        if (title) {
            title.textContent = mode === 'update' ? 'Update Service or Program' : (isArchive ? 'Archive Service or Program' : (isRestore ? 'Restore Service or Program' : 'Add Service or Program'));
        }

        if (submit) {
            submit.textContent = mode === 'update' ? 'Update Service or Program' : (isArchive ? 'Archive Service or Program' : (isRestore ? 'Restore Service or Program' : 'Add Service or Program'));
            submit.classList.toggle('btn-danger', isArchive);
            submit.classList.toggle('btn-success', isRestore);
            submit.classList.toggle('btn-primary', !isAction);
            submit.disabled = false;
        }

        if (fields) {
            fields.classList.toggle('d-none', isAction);
        }

        if (archiveMessage) {
            archiveMessage.classList.toggle('d-none', !isArchive);
        }

        if (restoreMessage) {
            restoreMessage.classList.toggle('d-none', !isRestore);
        }

        [category, shortcode, name, description].forEach(function (field) {
            if (field) {
                field.disabled = isAction;
                field.required = !isAction && field.hasAttribute('required');
            }
        });

        if (categoryOther) {
            categoryOther.disabled = true;
            categoryOther.required = false;
            categoryOther.classList.add('d-none');
        }

        if (!isAction) {
            setCategory(category, categoryOther, mode === 'update' ? trigger.dataset.serviceCategory : '');

            if (shortcode) {
                shortcode.value = mode === 'update' ? String(trigger.dataset.serviceShortcode || '') : '';
            }

            if (name) {
                name.value = mode === 'update' ? String(trigger.dataset.serviceName || '') : '';
            }

            if (description) {
                description.value = mode === 'update' ? String(trigger.dataset.serviceDescription || '') : '';
            }

            validateCode(modal);
        }

        if (archiveName) {
            archiveName.textContent = String(trigger.dataset.serviceName || 'this service or program');
        }

        if (restoreName) {
            restoreName.textContent = String(trigger.dataset.serviceName || 'this service or program');
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
            const modal = document.getElementById('serviceActionModal');
            syncOtherSelect(event.target, modal);
            autofillServiceCode(event.target, modal);
            validateCode(modal);
        }
    });

    // Live duplicate feedback as the user edits the Code field.
    document.addEventListener('input', function (event) {
        if (event.target.matches('#serviceModalShortcode')) {
            validateCode(document.getElementById('serviceActionModal'));
        }
    });
})(window, document);

// NOTE: the Active/Archived row toggle that used to live here is gone — status is
// now server-driven (the #service-status-select dropdown reloads the page via
// lookup-search.js, and the server renders only the matching 50-row page).
