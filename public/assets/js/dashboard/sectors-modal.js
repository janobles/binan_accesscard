(function (window) {
    if (typeof window.registerDashboardModal !== 'function') {
        return;
    }

    window.registerDashboardModal({
        namespace: 'sectors',
        triggerSelector: '.js-open-sectors-modal',
        defaultTitle: 'Sector Management',
        loadingMarkup: '<div class="family-modal-loading" role="status" aria-live="polite"><div class="spinner-border text-primary" aria-hidden="true"></div><span>Loading sectors...</span></div>',
        errorMarkup: '<div class="alert alert-danger mb-0">Unable to load sectors. Please try again.</div>'
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

    function setShortcode(select, otherInput, value) {
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

    function openSectorModal(trigger) {
        const modal = document.getElementById('sectorActionModal');
        const form = modal ? modal.querySelector('form') : null;

        if (!modal || !form || !window.bootstrap) {
            return;
        }

        const mode = String(trigger.dataset.sectorMode || 'create');
        const fields = modal.querySelector('.js-sector-form-fields');
        const archiveMessage = modal.querySelector('.js-sector-archive-message');
        const title = modal.querySelector('#sectorActionModalLabel');
        const submit = modal.querySelector('.js-sector-modal-submit');
        const shortcode = modal.querySelector('#sectorModalShortcode');
        const shortcodeOther = modal.querySelector('#sectorModalShortcodeOther');
        const name = modal.querySelector('#sectorModalName');
        const description = modal.querySelector('#sectorModalDescription');
        const archiveName = modal.querySelector('.js-sector-archive-name');
        const sectorId = String(trigger.dataset.sectorId || '').trim();
        const isArchive = mode === 'archive';

        form.reset();
        form.action = form.dataset.createAction || '';

        if (mode === 'update') {
            form.action = (form.dataset.updateAction || '').replace(/\/$/, '') + '/' + sectorId;
        } else if (isArchive) {
            form.action = (form.dataset.archiveAction || '').replace(/\/$/, '') + '/' + sectorId;
        }

        if (title) {
            title.textContent = mode === 'update' ? 'Update Sector' : (isArchive ? 'Archive Sector' : 'Add Sector');
        }

        if (submit) {
            submit.textContent = mode === 'update' ? 'Update Sector' : (isArchive ? 'Archive Sector' : 'Add Sector');
            submit.classList.toggle('btn-danger', isArchive);
            submit.classList.toggle('btn-primary', !isArchive);
        }

        if (fields) {
            fields.classList.toggle('d-none', isArchive);
        }

        if (archiveMessage) {
            archiveMessage.classList.toggle('d-none', !isArchive);
        }

        [shortcode, name, description].forEach(function (field) {
            if (field) {
                field.disabled = isArchive;
                field.required = !isArchive && field.hasAttribute('required');
            }
        });

        if (shortcodeOther) {
            shortcodeOther.disabled = true;
            shortcodeOther.required = false;
            shortcodeOther.classList.add('d-none');
        }

        if (!isArchive) {
            setShortcode(shortcode, shortcodeOther, mode === 'update' ? trigger.dataset.sectorShortcode : '');

            if (name) {
                name.value = mode === 'update' ? String(trigger.dataset.sectorName || '') : '';
            }

            if (description) {
                description.value = mode === 'update' ? String(trigger.dataset.sectorDescription || '') : '';
            }
        }

        if (archiveName) {
            archiveName.textContent = String(trigger.dataset.sectorName || 'this sector');
        }

        window.bootstrap.Modal.getOrCreateInstance(modal).show();
    }

    document.addEventListener('click', function (event) {
        const trigger = event.target.closest('.js-sector-modal-open');

        if (!trigger) {
            return;
        }

        openSectorModal(trigger);
    });

    document.addEventListener('change', function (event) {
        if (event.target.matches('#sectorModalShortcode')) {
            syncOtherSelect(event.target, document.getElementById('sectorActionModal'));
        }
    });
})(window, document);
