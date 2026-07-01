// Drives the Sector management UI across two contexts:
//   1. Dashboard modal: registers the Sectors panel with dashboard-modal-loader.js
//      so clicking .js-open-sectors-modal loads it via AJAX.
//   2. Sectors admin page (#sectorActionModal): handles create / update / archive /
//      restore in a single shared modal. Sectors are flat classifications, so the
//      code (SC, PWD, ...) is typed directly. Submission is blocked when the typed
//      shortcode already exists (data-existing-codes).
//      Third IIFE manages the Active / Archived row toggle on the lookups page.
//
// Connected to:
//   - dashboard-modal-loader.js : window.registerDashboardModal()
//   - Backend : POST admin/sectors/create|update|archive|restore
//               (Lookups\SectorController, via the modal's data-*-action attributes)
//   - Views   : Views/Lookups/sector-modal.php — #sectorActionModal, .js-sector-modal-open
//               buttons carry data-sector-mode, data-sector-id, data-sector-name, etc.
//   - Data    : PHP embeds data-existing-codes on the <form>
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
    function parseJson(value, fallback) {
        try {
            const parsed = JSON.parse(value || '');

            return parsed === null ? fallback : parsed;
        } catch (error) {
            return fallback;
        }
    }

    // Inline duplicate check: compares the typed code against existing codes,
    // excluding the sector's own code while editing. Toggles the error message
    // and the submit button accordingly.
    function validateCode(modal) {
        const form = modal.querySelector('form');
        const codeInput = modal.querySelector('#sectorModalShortcode');
        const errorEl = modal.querySelector('.js-sector-code-error');
        const submit = modal.querySelector('.js-sector-modal-submit');

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

    function openSectorModal(trigger) {
        const modal = document.getElementById('sectorActionModal');
        const form = modal ? modal.querySelector('form') : null;

        if (!modal || !form || !window.bootstrap) {
            return;
        }

        const mode = String(trigger.dataset.sectorMode || 'create');
        const fields = modal.querySelector('.js-sector-form-fields');
        const archiveMessage = modal.querySelector('.js-sector-archive-message');
        const restoreMessage = modal.querySelector('.js-sector-restore-message');
        const title = modal.querySelector('#sectorActionModalLabel');
        const submit = modal.querySelector('.js-sector-modal-submit');
        const shortcode = modal.querySelector('#sectorModalShortcode');
        const name = modal.querySelector('#sectorModalName');
        const description = modal.querySelector('#sectorModalDescription');
        const archiveName = modal.querySelector('.js-sector-archive-name');
        const restoreName = modal.querySelector('.js-sector-restore-name');
        const sectorId = String(trigger.dataset.sectorId || '').trim();
        const isArchive = mode === 'archive';
        const isRestore = mode === 'restore';
        const isAction = isArchive || isRestore;
        const existingCode = mode === 'update' ? String(trigger.dataset.sectorShortcode || '') : '';

        form.reset();
        form.action = form.dataset.createAction || '';
        form.dataset.currentCode = existingCode;

        if (mode === 'update') {
            form.action = (form.dataset.updateAction || '').replace(/\/$/, '') + '/' + sectorId;
        } else if (isArchive) {
            form.action = (form.dataset.archiveAction || '').replace(/\/$/, '') + '/' + sectorId;
        } else if (isRestore) {
            form.action = (form.dataset.restoreAction || '').replace(/\/$/, '') + '/' + sectorId;
        }

        if (title) {
            title.textContent = mode === 'update' ? 'Update Sector' : (isArchive ? 'Archive Sector' : (isRestore ? 'Restore Sector' : 'Add Sector'));
        }

        if (submit) {
            submit.textContent = mode === 'update' ? 'Update Sector' : (isArchive ? 'Archive Sector' : (isRestore ? 'Restore Sector' : 'Add Sector'));
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

        [shortcode, name, description].forEach(function (field) {
            if (field) {
                field.disabled = isAction;
                field.required = !isAction && field.hasAttribute('required');
            }
        });

        if (!isAction) {
            if (shortcode) {
                shortcode.value = existingCode;
            }

            if (name) {
                name.value = mode === 'update' ? String(trigger.dataset.sectorName || '') : '';
            }

            if (description) {
                description.value = mode === 'update' ? String(trigger.dataset.sectorDescription || '') : '';
            }

            validateCode(modal);
        }

        if (archiveName) {
            archiveName.textContent = String(trigger.dataset.sectorName || 'this sector');
        }

        if (restoreName) {
            restoreName.textContent = String(trigger.dataset.sectorName || 'this sector');
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

    // Live duplicate feedback as the user edits the Code field.
    document.addEventListener('input', function (event) {
        if (event.target.matches('#sectorModalShortcode')) {
            validateCode(document.getElementById('sectorActionModal'));
        }
    });
})(window, document);

// NOTE: the Active/Archived row toggle that used to live here is gone — status is
// now server-driven (the #sector-status-select dropdown reloads the page via
// lookup-search.js, and the server renders only the matching 50-row page).
