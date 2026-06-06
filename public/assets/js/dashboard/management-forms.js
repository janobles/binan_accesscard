// Handles inline-edit rows and "other" select inputs on the admin management pages.
//   - Inline edit: clicking Edit enables the row's fields; Cancel restores originals
//   - "Other" selects: shows a freetext input when the user picks __other__
//   - Delete forms: shows a confirm dialog before the form is submitted
//
// Connected to:
//   - Views  : Admin/accounts.php — [data-inline-edit-row] rows,
//              .js-management-other-select, .js-management-delete-form
//   - Exposes: window.initManagementForms(rootElement) so AJAX-loaded modal
//              content can re-initialise without waiting for DOMContentLoaded
(function (window, document) {
    function isOtherValue(value) {
        return String(value || '').trim().toLowerCase() === '__other__';
    }

    function otherInputFor(select, root) {
        const selector = select.dataset.otherInput || '';

        if (selector === '') {
            return null;
        }

        return (root || document).querySelector(selector) || document.querySelector(selector);
    }

    function syncOtherSelect(select, root) {
        const input = otherInputFor(select, root);

        if (!input) {
            return;
        }

        const show = isOtherValue(select.value);

        input.classList.toggle('d-none', !show);
        input.disabled = !show || select.disabled;
        input.required = show && !select.disabled;

        if (!show) {
            input.value = '';
        }
    }

    function setRowEditing(row, editing) {
        row.querySelectorAll('[data-inline-edit-field]').forEach(function (field) {
            field.disabled = !editing;
        });

        row.querySelectorAll('.js-inline-edit').forEach(function (button) {
            button.classList.toggle('d-none', editing);
        });

        row.querySelectorAll('.js-inline-save, .js-inline-cancel').forEach(function (button) {
            button.classList.toggle('d-none', !editing);
        });

        row.querySelectorAll('.js-management-other-select').forEach(function (select) {
            syncOtherSelect(select, row);
        });
    }

    function rememberOriginalValues(row) {
        row.querySelectorAll('[data-inline-edit-field]').forEach(function (field) {
            field.dataset.originalValue = field.value || '';
        });
    }

    function restoreOriginalValues(row) {
        row.querySelectorAll('[data-inline-edit-field]').forEach(function (field) {
            field.value = field.dataset.originalValue || '';
        });
    }

    function initManagementForms(rootElement) {
        const root = rootElement instanceof HTMLElement ? rootElement : document;

        root.querySelectorAll('.js-management-other-select').forEach(function (select) {
            if (select.dataset.otherSelectBound === '1') {
                syncOtherSelect(select, root);

                return;
            }

            select.dataset.otherSelectBound = '1';
            select.addEventListener('change', function () {
                syncOtherSelect(select, root);
            });
            syncOtherSelect(select, root);
        });

        root.querySelectorAll('[data-inline-edit-row]').forEach(function (row) {
            if (row.dataset.inlineEditBound === '1') {
                return;
            }

            row.dataset.inlineEditBound = '1';
            rememberOriginalValues(row);
            setRowEditing(row, false);

            const editButton = row.querySelector('.js-inline-edit');
            const cancelButton = row.querySelector('.js-inline-cancel');

            if (editButton) {
                editButton.addEventListener('click', function () {
                    rememberOriginalValues(row);
                    setRowEditing(row, true);
                });
            }

            if (cancelButton) {
                cancelButton.addEventListener('click', function () {
                    restoreOriginalValues(row);
                    setRowEditing(row, false);
                });
            }
        });

        root.querySelectorAll('.js-management-delete-form').forEach(function (form) {
            if (form.dataset.deleteFormBound === '1') {
                return;
            }

            form.dataset.deleteFormBound = '1';
            form.addEventListener('submit', function (event) {
                const message = String(form.dataset.confirmMessage || 'Delete this item? This is permanent.').trim();

                if (!window.confirm(message)) {
                    event.preventDefault();
                }
            });
        });
    }

    window.initManagementForms = initManagementForms;

    document.addEventListener('DOMContentLoaded', function () {
        initManagementForms(document);
    });
})(window, document);
