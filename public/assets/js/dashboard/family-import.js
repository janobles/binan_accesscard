// Drives step 1 of the import wizard (the upload page) and submits the uploaded .xlsx to
// FamilyImportController::import(), which QUEUES a background job. Progress is tracked
// inline on the page.
//
// Active jobs are remembered in localStorage, so if the user navigates away and comes back,
// it resumes polling and updates the inline UI.
//
// Connected to:
//   - family-datatable.js : window.reloadFamilyDataTable()
//   - Views   : Family/import-upload.php ([data-family-import]), Family/list.php button
//   - Backend : POST records/import   -> { status:'queued', statusUrl }
//               GET  records/import/status/(:num)
(function (window, document) {
    'use strict';

    var POLL_MS = 1500;
    var STORAGE_KEY = 'binanFamilyImport';
    var tracked = {}; // statusUrl -> timer: number|null

    function escapeHtml(value) {
        var div = document.createElement('div');
        div.textContent = String(value == null ? '' : value);
        return div.innerHTML;
    }

    function updateCsrfForm(form, hash) {
        if (!hash || !form) return;
        var input = form.querySelector('input[type="hidden"]');
        if (input) input.value = hash;
    }

    // -- localStorage list of in-flight jobs -----------------------------------

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
        } catch (e) { }
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

    // -- Inline UI Rendering ---------------------------------------------------

    function getResultsContainer() {
        return document.querySelector('[data-import-results]');
    }

    function getActionsContainer() {
        return document.querySelector('[data-import-actions]');
    }

    function renderProgress(data) {
        var results = getResultsContainer();
        var actions = getActionsContainer();
        if (!results) return; // Not on the upload page

        if (actions) actions.hidden = true; // Hide upload/cancel buttons

        var progress = data.progress || { total: 0, percent: 0 };
        var animated = progress.total === 0;

        var label;
        if (data.status === 'pending') {
            label = 'Queued - waiting for the worker...';
        } else if (progress.total > 0) {
            var extra = [];
            if (progress.failed) extra.push(progress.failed + ' failed');
            if (progress.skipped) extra.push(progress.skipped + ' skipped');

            label = 'Imported ' + progress.imported + ' of ' + progress.total + ' families' +
                (extra.length ? ' (' + extra.join(', ') + ')' : '') + '...';
        } else {
            label = 'Reading and validating...';
        }

        var barClass = 'progress-bar' + (animated ? ' progress-bar-striped progress-bar-animated' : '');
        var width = (progress.total > 0 ? progress.percent : 100) + '%';
        var text = progress.total > 0 ? progress.percent + '%' : '';

        results.innerHTML = 
            '<div class="mb-2 text-muted small fw-bold">' + escapeHtml(label) + '</div>' +
            '<div class="progress mb-3" style="height: 20px;">' +
                '<div class="' + barClass + '" role="progressbar" style="width: ' + width + ';">' + text + '</div>' +
            '</div>';
    }

    function errorTable(errors) {
        if (!errors || !errors.length) return '';
        var rows = errors.map(function (error) {
            var where = error.sheetRow ? ('Row ' + error.sheetRow) : 'File';
            if (error.familyNo) where += ' &middot; ' + escapeHtml(error.familyNo);
            return '<tr><td class="text-nowrap">' + where + '</td><td>' + escapeHtml(error.message) + '</td></tr>';
        }).join('');

        return '<table class="table table-sm table-bordered mt-2">' +
            '<thead><tr><th>Where</th><th>Details</th></tr></thead><tbody>' + rows + '</tbody></table>';
    }

    function renderReviewReady(data) {
        var results = getResultsContainer();
        var actions = getActionsContainer();
        if (!results) return;

        if (actions) actions.hidden = true;

        var msg = data.message || 'Your file is ready to review.';
        results.innerHTML = 
            '<div class="alert alert-success d-flex flex-column align-items-start">' +
                '<div class="mb-2"><i class="bi bi-check-circle me-2" aria-hidden="true"></i>' + escapeHtml(msg) + '</div>' +
                '<a class="btn btn-primary" href="' + escapeHtml(data.reviewUrl) + '">Review and Fix</a>' +
            '</div>';
    }

    function renderFinal(data) {
        if (data.reviewUrl) {
            renderReviewReady(data);
            return;
        }

        var results = getResultsContainer();
        var actions = getActionsContainer();
        if (!results) return;

        if (actions) actions.hidden = true;

        var skipped = (data.progress && data.progress.skipped) || 0;
        var isPartial = data.status === 'partial';
        
        var alertClass = data.status === 'done' ? 'alert-success' : (isPartial ? 'alert-warning' : 'alert-danger');
        var title = data.status === 'done' ? (skipped ? 'Import complete (some skipped)' : 'Import complete') : (isPartial ? 'Finished with errors' : 'Import failed');
        var icon = data.status === 'done' ? 'bi-check-circle' : 'bi-exclamation-triangle';
        
        var html = '<div class="alert ' + alertClass + '">' +
            '<div class="fw-bold mb-1"><i class="bi ' + icon + ' me-2" aria-hidden="true"></i>' + escapeHtml(title) + '</div>' +
            '<div class="small">' + escapeHtml(data.message || '') + '</div>';

        if (skipped || data.errors) {
            html += errorTable(data.errors);
        }
        
        html += '<div class="mt-3"><a class="btn btn-outline-secondary btn-sm" href="' + escapeHtml(window.location.href) + '">Upload another file</a></div>';
        html += '</div>';

        results.innerHTML = html;
    }

    // -- per-job tracking lifecycle --------------------------------------------

    function startTracking(statusUrl) {
        if (!statusUrl || tracked[statusUrl]) return;
        tracked[statusUrl] = { timer: null };
        addStored(statusUrl);
        poll(statusUrl);
    }

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
        if (!entry) return;

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
            if (!tracked[statusUrl]) return;

            var data = result.data || {};

            if (result.code === 404) {
                stopTracking(statusUrl);
                var results = getResultsContainer();
                if (results) results.innerHTML = '<div class="alert alert-warning">Import job no longer found.</div>';
                var actions = getActionsContainer();
                if (actions) actions.hidden = false;
                return;
            }

            if (!data.finished) {
                renderProgress(data);
                tracked[statusUrl].timer = window.setTimeout(function () { poll(statusUrl); }, POLL_MS);
                return;
            }

            renderFinal(data);
            stopTracking(statusUrl);

            if ((data.status === 'done' || data.status === 'partial') &&
                data.phase !== 'review' &&
                typeof window.reloadFamilyDataTable === 'function') {
                window.reloadFamilyDataTable();
            }
        }).catch(function () {
            if (tracked[statusUrl]) {
                tracked[statusUrl].timer = window.setTimeout(function () { poll(statusUrl); }, POLL_MS * 2);
            }
        });
    }

    function resumeTracking() {
        getStored().forEach(function (statusUrl) {
            startTracking(statusUrl);
        });
    }

    // -- the upload form -------------------------------------------------------

    function initImportForm(container) {
        var root = container.querySelector('[data-family-import]');
        if (!root || root.dataset.familyImportReady === '1') return;
        root.dataset.familyImportReady = '1';

        var form = root.querySelector('[data-import-form]');
        var results = root.querySelector('[data-import-results]');
        var submit = root.querySelector('[data-import-submit]');
        var actions = getActionsContainer();

        if (!form || !results) return;

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

            results.innerHTML = '<div class="text-muted small mb-3">Uploading your file...</div>';
            if (actions) actions.hidden = true;

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

                if (result.ok && data.status === 'queued' && data.statusUrl) {
                    form.reset();
                    updateCsrfForm(form, data.csrf);
                    startTracking(data.statusUrl);
                    return;
                }

                updateCsrfForm(form, data.csrf);
                if (actions) actions.hidden = false;

                results.innerHTML = '<div class="alert alert-danger mb-3">' +
                    escapeHtml(data.message || 'The file could not be queued for import.') + '</div>';
            }).catch(function () {
                if (submit) {
                    submit.disabled = false;
                    submit.textContent = originalLabel;
                }
                if (actions) actions.hidden = false;
                results.innerHTML = '<div class="alert alert-danger mb-3">A network error occurred. Please try again.</div>';
            });
        });
    }

    function start() {
        initImportForm(document);
        resumeTracking();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})(window, document);

