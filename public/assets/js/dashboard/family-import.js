// Registers the "Import from Excel" modal and submits the uploaded .xlsx to
// FamilyController::import(), which QUEUES a background job. As soon as the job is
// queued the modal CLOSES and progress is tracked in a small toast in a stack pinned
// to the bottom-right. Each import gets its OWN toast, so several files can import at
// once and all show progress while the user keeps working.
//
// Active jobs are remembered in localStorage, so every in-flight toast resumes polling
// even if the user navigates to another dashboard page before the imports finish.
//
// Connected to:
//   - dashboard-modal-loader.js : window.registerDashboardModal()
//   - family-datatable.js       : window.reloadFamilyDataTable()
//   - Views   : Family/import-modal.php, the #familyModal shell, Family/list.php button
//   - Backend : POST {role}/manage-family/import   -> { status:'queued', statusUrl }
//               GET  {role}/manage-family/import/status/(:num)
(function (window, document) {
    'use strict';

    var POLL_MS = 1500;
    var STORAGE_KEY = 'binanFamilyImport';
    // statusUrl -> { toast: Element, timer: number|null }
    var tracked = {};

    function escapeHtml(value) {
        var div = document.createElement('div');
        div.textContent = String(value == null ? '' : value);

        return div.innerHTML;
    }

    // Refreshes the import form's CSRF token so a second import in the same session
    // works. The modal form's only hidden input is the csrf_field(). Polling is a GET
    // (CodeIgniter does not rotate the token on GET), so it needs no refresh.
    function updateCsrfForm(form, hash) {
        if (!hash || !form) {
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

    // -- localStorage list of in-flight jobs (so they resume after navigation) -

    function getStored() {
        try {
            var raw = window.localStorage.getItem(STORAGE_KEY);
            var list = raw ? JSON.parse(raw) : [];

            return Array.isArray(list) ? list : [];
        } catch (e) {
            return [];
        }
    }

    function saveStored(list) {
        try {
            if (list.length) {
                window.localStorage.setItem(STORAGE_KEY, JSON.stringify(list));
            } else {
                window.localStorage.removeItem(STORAGE_KEY);
            }
        } catch (e) { /* private mode / quota - tracking still works this session */ }
    }

    function addStored(statusUrl) {
        var list = getStored();

        if (list.indexOf(statusUrl) === -1) {
            list.push(statusUrl);
            saveStored(list);
        }
    }

    function removeStored(statusUrl) {
        saveStored(getStored().filter(function (u) { return u !== statusUrl; }));
    }

    // -- the bottom-right stack of progress toasts -----------------------------

    function getContainer() {
        var el = document.getElementById('familyImportToasts');

        if (!el) {
            el = document.createElement('div');
            el.className = 'family-import-toasts';
            el.id = 'familyImportToasts';
            document.body.appendChild(el);
        }

        return el;
    }

    function buildToast(statusUrl) {
        var toast = document.createElement('div');
        toast.className = 'family-import-toast';
        toast.dataset.statusUrl = statusUrl;
        toast.setAttribute('role', 'status');
        toast.setAttribute('aria-live', 'polite');
        toast.innerHTML =
            '<div class="fit-header">' +
            '<span class="fit-title">Importing families</span>' +
            '<button type="button" class="fit-close" aria-label="Dismiss" hidden>&times;</button>' +
            '</div>' +
            '<div class="fit-msg"></div>' +
            '<div class="progress" role="progressbar" aria-label="Import progress" aria-valuemin="0" aria-valuemax="100">' +
            '<div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 100%;"></div>' +
            '</div>' +
            '<div class="fit-errors"></div>';

        getContainer().appendChild(toast);

        toast.querySelector('.fit-close').addEventListener('click', function () {
            stopTracking(statusUrl);
            removeToast(toast);
        });

        return toast;
    }

    function removeToast(toast) {
        if (!toast) {
            return;
        }

        toast.classList.add('fit-hide');
        window.setTimeout(function () {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }

            // Drop the empty container once the last toast is gone.
            var container = document.getElementById('familyImportToasts');

            if (container && !container.children.length) {
                container.parentNode.removeChild(container);
            }
        }, 260);
    }

    function setToastState(toast, kind) {
        toast.classList.remove('fit-success', 'fit-warning', 'fit-error');

        if (kind) {
            toast.classList.add('fit-' + kind);
        }
    }

    function errorTable(errors) {
        if (!errors || !errors.length) {
            return '';
        }

        var rows = errors.map(function (error) {
            var where = error.sheetRow ? ('Row ' + error.sheetRow) : 'File';

            if (error.familyNo) {
                where += ' &middot; ' + escapeHtml(error.familyNo);
            }

            return '<tr><td class="text-nowrap">' + where + '</td><td>' + escapeHtml(error.message) + '</td></tr>';
        }).join('');

        return '<table class="table table-sm table-bordered">' +
            '<thead><tr><th>Where</th><th>Details</th></tr></thead><tbody>' + rows + '</tbody></table>';
    }

    function renderProgress(toast, data) {
        var progress = data.progress || { total: 0, percent: 0 };
        var bar = toast.querySelector('.progress-bar');
        var animated = progress.total === 0; // unknown total yet -> indeterminate

        setToastState(toast, null);
        toast.querySelector('.fit-title').textContent = 'Importing families';
        toast.querySelector('.fit-close').hidden = true;
        toast.querySelector('.fit-errors').innerHTML = '';

        var label;

        if (data.status === 'pending') {
            label = 'Queued - waiting for the worker...';
        } else if (progress.total > 0) {
            var extra = [];

            if (progress.failed) {
                extra.push(progress.failed + ' failed');
            }

            if (progress.skipped) {
                extra.push(progress.skipped + ' skipped');
            }

            label = 'Imported ' + progress.imported + ' of ' + progress.total + ' families' +
                (extra.length ? ' (' + extra.join(', ') + ')' : '') + '...';
        } else {
            label = 'Reading and validating...';
        }

        toast.querySelector('.fit-msg').textContent = label;

        bar.className = 'progress-bar' + (animated ? ' progress-bar-striped progress-bar-animated' : '');
        bar.style.width = (progress.total > 0 ? progress.percent : 100) + '%';
        bar.textContent = progress.total > 0 ? progress.percent + '%' : '';
    }

    // The upload only STAGES the file now; a finished review-phase job is not "imported",
    // it is ready for the operator to inspect. Show an "Open review" call to action.
    function renderReviewReady(toast, data) {
        var bar = toast.querySelector('.progress-bar');
        var closeBtn = toast.querySelector('.fit-close');

        bar.className = 'progress-bar';
        bar.style.width = '100%';
        bar.textContent = '';
        bar.classList.add('bg-info');

        setToastState(toast, 'success');
        toast.querySelector('.fit-title').textContent = 'Ready to review';
        toast.querySelector('.fit-msg').textContent = data.message || 'Your file is ready to review.';

        var errorsEl = toast.querySelector('.fit-errors');
        errorsEl.innerHTML = '';
        var link = document.createElement('a');
        link.className = 'btn btn-sm btn-primary mt-2';
        link.href = data.reviewUrl;
        link.textContent = 'Open review';
        errorsEl.appendChild(link);

        closeBtn.hidden = false;
    }

    function renderFinal(toast, data) {
        // A staged file that finished cleanly routes to the review screen, not "done".
        if (data.reviewUrl) {
            renderReviewReady(toast, data);

            return;
        }

        var bar = toast.querySelector('.progress-bar');
        var closeBtn = toast.querySelector('.fit-close');

        bar.className = 'progress-bar';
        bar.style.width = '100%';
        bar.textContent = '';
        toast.querySelector('.fit-msg').textContent = data.message || '';

        if (data.status === 'done') {
            var skipped = (data.progress && data.progress.skipped) || 0;

            setToastState(toast, 'success');
            toast.querySelector('.fit-title').textContent = skipped ? 'Import complete (some skipped)' : 'Import complete';
            bar.classList.add('bg-success');

            // Nothing was skipped: clean success, auto-dismiss. When families were
            // skipped, keep the toast open with the list so the user can see which
            // records already existed.
            if (!skipped) {
                closeBtn.hidden = true;
                window.setTimeout(function () { removeToast(toast); }, 6000);

                return;
            }

            toast.querySelector('.fit-errors').innerHTML = errorTable(data.errors);
            closeBtn.hidden = false;

            return;
        }

        // partial or failed: keep the toast open with the per-row details + a close X.
        var isPartial = data.status === 'partial';
        setToastState(toast, isPartial ? 'warning' : 'error');
        toast.querySelector('.fit-title').textContent = isPartial ? 'Finished with errors' : 'Import failed';
        bar.classList.add(isPartial ? 'bg-warning' : 'bg-danger');
        toast.querySelector('.fit-errors').innerHTML = errorTable(data.errors);
        closeBtn.hidden = false;
    }

    // -- per-job tracking lifecycle --------------------------------------------

    function startTracking(statusUrl) {
        if (!statusUrl || tracked[statusUrl]) {
            return; // already tracking this job
        }

        tracked[statusUrl] = { toast: buildToast(statusUrl), timer: null };
        addStored(statusUrl);
        poll(statusUrl);
    }

    // Stops polling a job and forgets it (the toast itself is left to the caller).
    function stopTracking(statusUrl) {
        var entry = tracked[statusUrl];

        if (entry && entry.timer) {
            window.clearTimeout(entry.timer);
        }

        delete tracked[statusUrl];
        removeStored(statusUrl);
    }

    function poll(statusUrl) {
        var entry = tracked[statusUrl];

        if (!entry) {
            return; // dismissed/stopped
        }

        window.fetch(statusUrl, {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json().then(function (data) {
                return { code: response.status, data: data };
            }).catch(function () {
                return { code: response.status, data: {} };
            });
        }).then(function (result) {
            if (!tracked[statusUrl]) {
                return; // dismissed mid-request
            }

            var data = result.data || {};
            var toast = tracked[statusUrl].toast;

            // Job gone (e.g. cleared) - stop quietly.
            if (result.code === 404) {
                stopTracking(statusUrl);
                removeToast(toast);

                return;
            }

            if (!data.finished) {
                renderProgress(toast, data);
                tracked[statusUrl].timer = window.setTimeout(function () { poll(statusUrl); }, POLL_MS);

                return;
            }

            // Terminal state: render, then forget the job (toast stays for errors).
            renderFinal(toast, data);
            stopTracking(statusUrl);

            // Only a WRITE-phase job changed records; a review-phase job wrote nothing.
            if ((data.status === 'done' || data.status === 'partial') &&
                data.phase !== 'review' &&
                typeof window.reloadFamilyDataTable === 'function') {
                window.reloadFamilyDataTable();
            }
        }).catch(function () {
            // Transient network error - the job keeps running server-side; retry.
            if (tracked[statusUrl]) {
                tracked[statusUrl].timer = window.setTimeout(function () { poll(statusUrl); }, POLL_MS * 2);
            }
        });
    }

    // Resume every still-running import after a page navigation/reload.
    function resumeTracking() {
        getStored().forEach(function (statusUrl) {
            startTracking(statusUrl);
        });
    }

    // -- the modal form --------------------------------------------------------

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
                submit.textContent = 'Uploading...';
            }

            results.innerHTML = '<div class="text-muted small">Uploading your file...</div>';

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

                if (submit) {
                    submit.disabled = false;
                    submit.textContent = originalLabel;
                }

                // Queued: reset the form, close the modal, hand off to its own toast.
                // form.reset() must run BEFORE updateCsrfForm(), since reset() would
                // otherwise wipe the just-refreshed token back to its page-load value.
                if (result.ok && data.status === 'queued' && data.statusUrl) {
                    results.innerHTML = '';
                    form.reset();
                    updateCsrfForm(form, data.csrf);
                    closeImportModal();
                    startTracking(data.statusUrl);

                    return;
                }

                updateCsrfForm(form, data.csrf);

                // Upload rejected before queuing (bad file, permission, etc.) - keep
                // the modal open so the user can fix and retry.
                results.innerHTML = '<div class="alert alert-danger mb-0">' +
                    escapeHtml(data.message || 'The file could not be queued for import.') + '</div>';
            }).catch(function () {
                if (submit) {
                    submit.disabled = false;
                    submit.textContent = originalLabel;
                }

                results.innerHTML = '<div class="alert alert-danger mb-0">A network error occurred. Please try again.</div>';
            });
        });
    }

    if (typeof window.registerDashboardModal === 'function') {
        window.registerDashboardModal({
            namespace: 'familyImport',
            triggerSelector: '.js-open-family-import-modal',
            defaultTitle: 'Import from Excel',
            loadingMarkup: '<div class="family-modal-loading" role="status" aria-live="polite"><div class="spinner-border text-primary" aria-hidden="true"></div><span>Loading...</span></div>',
            errorMarkup: '<div class="alert alert-danger mb-0">Unable to load the import form. Please try again.</div>',
            onLoaded: initImportModal
        });
    }

    // Pick up imports that were still running when this page loaded.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', resumeTracking);
    } else {
        resumeTracking();
    }
})(window, document);
