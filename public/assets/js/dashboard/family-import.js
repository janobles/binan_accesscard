// Registers the "Import from Excel" modal with the shared dashboard loader and
// submits the uploaded .xlsx over AJAX to FamilyController::import(). On success it
// refreshes the records DataTable and shows a toast; on a validation failure it
// renders the per-row error list returned by the server (the import is all-or-
// nothing, so nothing is saved when errors are shown).
//
// Connected to:
//   - dashboard-modal-loader.js : window.registerDashboardModal()
//   - family-datatable.js       : window.reloadFamilyDataTable()
//   - Views   : Family/import-modal.php, the #familyModal shell, Family/list.php button
//   - Backend : POST {role}/manage-family/import
(function (window, document) {
    'use strict';

    if (typeof window.registerDashboardModal !== 'function') {
        return;
    }

    function escapeHtml(value) {
        var div = document.createElement('div');
        div.textContent = String(value == null ? '' : value);

        return div.innerHTML;
    }

    function updateCsrf(form, hash) {
        if (!hash) {
            return;
        }

        var input = form.querySelector('input[type="hidden"]');

        if (input) {
            input.value = hash;
        }
    }

    function closeImportModal() {
        var modalEl = document.getElementById('familyModal');

        if (modalEl && window.bootstrap && window.bootstrap.Modal) {
            modalEl.dataset.familyCloseConfirmed = '1';
            var instance = window.bootstrap.Modal.getInstance(modalEl);

            if (instance) {
                instance.hide();
            }
        }
    }

    function showImportToast(message, isError) {
        var toast = document.createElement('div');
        toast.className = 'alert ' + (isError ? 'alert-danger' : 'alert-success') + ' family-toast shadow';
        toast.setAttribute('role', 'status');
        toast.textContent = message;
        document.body.appendChild(toast);

        window.setTimeout(function () {
            toast.style.transition = 'opacity 200ms ease';
            toast.style.opacity = '0';
            window.setTimeout(function () { toast.remove(); }, 220);
        }, 3600);
    }

    function renderErrors(results, errors) {
        if (!errors || !errors.length) {
            results.innerHTML = '<div class="alert alert-danger">The file could not be imported.</div>';

            return;
        }

        var rows = errors.map(function (error) {
            var where = error.sheetRow ? ('Row ' + error.sheetRow) : 'File';

            if (error.familyNo) {
                where += ' &middot; Family ' + escapeHtml(error.familyNo);
            }

            return '<tr><td class="text-nowrap">' + where + '</td><td>' + escapeHtml(error.message) + '</td></tr>';
        }).join('');

        results.innerHTML =
            '<div class="alert alert-danger mb-2">Nothing was imported. Fix these and try again:</div>' +
            '<div class="table-responsive" style="max-height: 14rem; overflow: auto;">' +
            '<table class="table table-sm table-bordered mb-0">' +
            '<thead><tr><th>Where</th><th>Problem</th></tr></thead><tbody>' + rows + '</tbody></table></div>';
    }

    function initImportModal(container) {
        var root = container.querySelector('[data-family-import]');

        if (!root || root.dataset.familyImportReady === '1') {
            return;
        }

        root.dataset.familyImportReady = '1';

        var form = root.querySelector('[data-import-form]');
        var results = root.querySelector('[data-import-results]');
        var submit = root.querySelector('[data-import-submit]');

        if (!form || !results) {
            return;
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            var fileInput = form.querySelector('input[type="file"]');

            if (fileInput && fileInput.files.length === 0) {
                results.innerHTML = '<div class="alert alert-warning mb-0">Please choose a .xlsx file first.</div>';

                return;
            }

            var originalLabel = submit ? submit.textContent : '';

            if (submit) {
                submit.disabled = true;
                submit.textContent = 'Importing...';
            }

            results.innerHTML = '';

            window.fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            }).then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                }).catch(function () {
                    return { ok: response.ok, data: {} };
                });
            }).then(function (result) {
                var data = result.data || {};

                updateCsrf(form, data.csrf);

                if (submit) {
                    submit.disabled = false;
                    submit.textContent = originalLabel;
                }

                if (result.ok && data.status === 'success') {
                    closeImportModal();

                    if (typeof window.reloadFamilyDataTable === 'function') {
                        window.reloadFamilyDataTable();
                    }

                    showImportToast(data.message || 'Import complete.', false);

                    return;
                }

                if (data.errors) {
                    renderErrors(results, data.errors);

                    return;
                }

                results.innerHTML = '<div class="alert alert-danger mb-0">' + escapeHtml(data.message || 'The file could not be imported.') + '</div>';
            }).catch(function () {
                if (submit) {
                    submit.disabled = false;
                    submit.textContent = originalLabel;
                }

                results.innerHTML = '<div class="alert alert-danger mb-0">A network error occurred. Please try again.</div>';
            });
        });
    }

    window.registerDashboardModal({
        namespace: 'familyImport',
        triggerSelector: '.js-open-family-import-modal',
        defaultTitle: 'Import from Excel',
        loadingMarkup: '<div class="family-modal-loading" role="status" aria-live="polite"><div class="spinner-border text-primary" aria-hidden="true"></div><span>Loading...</span></div>',
        errorMarkup: '<div class="alert alert-danger mb-0">Unable to load the import form. Please try again.</div>',
        onLoaded: initImportModal
    });
})(window, document);
