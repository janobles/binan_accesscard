// Drives the "Manage Categories" admin page (#categoryActionModal): handles
// create / update / archive / restore / delete in a single shared modal, blocks
// submission when the typed code already exists (data-existing-codes), and
// manages the Active / Archived row toggle.
//
// Connected to:
//   - Backend : POST admin/categories/create|update|archive|restore|delete
//               (Lookups\CategoryController, via the modal's data-*-action attributes)
//   - Views   : Views/Lookups/category-modal.php — #categoryActionModal,
//               .js-category-modal-open buttons carry data-category-mode,
//               data-category-id, data-category-code, data-category-name, etc.
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
    // excluding the category's own code while editing.
    function validateCode(modal) {
        const form = modal.querySelector('form');
        const codeInput = modal.querySelector('#categoryModalCode');
        const errorEl = modal.querySelector('.js-category-code-error');
        const submit = modal.querySelector('.js-category-modal-submit');

        if (!form || !codeInput || codeInput.disabled || codeInput.readOnly) {
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

    function openCategoryModal(trigger) {
        const modal = document.getElementById('categoryActionModal');
        const form = modal ? modal.querySelector('form') : null;

        if (!modal || !form || !window.bootstrap) {
            return;
        }

        const mode = String(trigger.dataset.categoryMode || 'create');
        const fields = modal.querySelector('.js-category-form-fields');
        const archiveMessage = modal.querySelector('.js-category-archive-message');
        const deleteMessage = modal.querySelector('.js-category-delete-message');
        const restoreMessage = modal.querySelector('.js-category-restore-message');
        const title = modal.querySelector('#categoryActionModalLabel');
        const submit = modal.querySelector('.js-category-modal-submit');
        const code = modal.querySelector('#categoryModalCode');
        const name = modal.querySelector('#categoryModalName');
        const archiveName = modal.querySelector('.js-category-archive-name');
        const deleteName = modal.querySelector('.js-category-delete-name');
        const restoreName = modal.querySelector('.js-category-restore-name');
        const categoryId = String(trigger.dataset.categoryId || '').trim();
        const isArchive = mode === 'archive';
        const isRestore = mode === 'restore';
        const isDelete = mode === 'delete';
        const isAction = isArchive || isRestore || isDelete;
        const existingCode = mode === 'update' ? String(trigger.dataset.categoryCode || '') : '';

        form.reset();
        form.action = form.dataset.createAction || '';
        form.dataset.currentCode = existingCode;

        if (mode === 'update') {
            form.action = (form.dataset.updateAction || '').replace(/\/$/, '') + '/' + categoryId;
        } else if (isArchive) {
            form.action = (form.dataset.archiveAction || '').replace(/\/$/, '') + '/' + categoryId;
        } else if (isRestore) {
            form.action = (form.dataset.restoreAction || '').replace(/\/$/, '') + '/' + categoryId;
        } else if (isDelete) {
            form.action = (form.dataset.deleteAction || '').replace(/\/$/, '') + '/' + categoryId;
        }

        if (title) {
            title.textContent = mode === 'update'
                ? 'Update Category'
                : (isArchive ? 'Archive Category' : (isRestore ? 'Restore Category' : (isDelete ? 'Delete Category' : 'Add Category')));
        }

        if (submit) {
            submit.textContent = mode === 'update'
                ? 'Update Category'
                : (isArchive ? 'Archive Category' : (isRestore ? 'Restore Category' : (isDelete ? 'Delete Category' : 'Add Category')));
            submit.classList.toggle('btn-danger', isArchive || isDelete);
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

        if (deleteMessage) {
            deleteMessage.classList.toggle('d-none', !isDelete);
        }

        if (restoreMessage) {
            restoreMessage.classList.toggle('d-none', !isRestore);
        }

        [code, name].forEach(function (field) {
            if (field) {
                field.disabled = isAction;
                field.required = !isAction && field.hasAttribute('required');
            }
        });

        if (!isAction) {
            if (code) {
                code.value = existingCode;
            }

            if (name) {
                name.value = mode === 'update' ? String(trigger.dataset.categoryName || '') : '';
            }

            validateCode(modal);
        }

        if (archiveName) {
            archiveName.textContent = String(trigger.dataset.categoryName || 'this category');
        }

        if (deleteName) {
            deleteName.textContent = String(trigger.dataset.categoryName || 'this category');
        }

        if (restoreName) {
            restoreName.textContent = String(trigger.dataset.categoryName || 'this category');
        }

        window.bootstrap.Modal.getOrCreateInstance(modal).show();
    }

    document.addEventListener('click', function (event) {
        const trigger = event.target.closest('.js-category-modal-open');

        if (!trigger) {
            return;
        }

        openCategoryModal(trigger);
    });

    document.addEventListener('input', function (event) {
        if (event.target.matches('#categoryModalCode')) {
            validateCode(document.getElementById('categoryActionModal'));
        }
    });
})(window, document);

// NOTE: the Active/Archived row toggle that used to live here is gone — status is
// now server-driven (the #category-status-select dropdown reloads the page via
// lookup-search.js, and the server renders only the matching 50-row page).
