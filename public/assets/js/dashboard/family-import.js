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

    function renderProgress(data) {
        var progress = data.progress || { total: 0, percent: 0 };
        
        var label;
        if (data.status === 'pending') {
            label = 'Preparing your file...';
        } else if (progress.total > 0) {
            var extra = [];
            if (progress.failed) extra.push(progress.failed + ' failed');
            if (progress.skipped) extra.push(progress.skipped + ' skipped');

            label = 'Saved ' + progress.imported + ' of ' + progress.total + ' families' +
                (extra.length ? ' (' + extra.join(', ') + ')' : '') + '...';
        } else {
            label = 'Scanning your file for errors...';
        }

        if (window.showToast) {
            window.showToast(label, 'primary', { autohide: false });
        }
    }

    function renderReviewReady(data) {
        var msg = data.message || 'We\'ve scanned your file. Please review the records before saving.';
        var html = '<div>' + escapeHtml(msg) + '</div><a class="btn btn-sm btn-light mt-2 fw-bold" href="' + escapeHtml(data.reviewUrl) + '">Review and Fix</a>';
        
        if (window.showToast) {
            window.showToast(html, 'success', { html: true, autohide: false });
        }
    }

    function renderFinal(data) {
        if (data.reviewUrl) {
            renderReviewReady(data);
            return;
        }

        var skipped = (data.progress && data.progress.skipped) || 0;
        var isPartial = data.status === 'partial';
        
        var alertClass = data.status === 'done' ? 'success' : (isPartial ? 'warning' : 'danger');
        var title = data.status === 'done' ? (skipped ? 'Import completed (some skipped)' : 'Import completed successfully') : (isPartial ? 'Import finished with errors' : 'Failed to import file');
        
        var html = '<strong>' + escapeHtml(title) + '</strong><br>' + escapeHtml(data.message || '');
        if (window.showToast) {
            window.showToast(html, alertClass, { html: true });
        }
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
                if (window.showToast) {
                    window.showToast('Import job no longer found.', 'warning');
                }
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
        var submit = root.querySelector('[data-import-submit]');

        if (!form) return;

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            var fileInput = form.querySelector('input[type="file"]');
            if (fileInput && fileInput.files.length === 0) {
                if (window.showToast) {
                    window.showToast('Please choose a .xlsx file first.', 'warning');
                }
                return;
            }

            var originalLabel = submit ? submit.textContent : '';

            if (submit) {
                submit.disabled = true;
                submit.textContent = 'Uploading...';
            }

            if (window.showToast) {
                window.showToast('Uploading file...', 'primary', { autohide: false });
            }

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
                if (window.showToast) {
                    window.showToast(data.message || 'We could not process your file at this time.', 'danger');
                }
            }).catch(function () {
                if (submit) {
                    submit.disabled = false;
                    submit.textContent = originalLabel;
                }
                if (window.showToast) {
                    window.showToast('We had trouble connecting. Please check your network and try again.', 'danger');
                }
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

