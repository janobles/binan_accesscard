// Import Review, step 2 of the import wizard.
//
// One row per staged person. The flag sits on the person whose values are wrong, and the
// Family and Role columns carry the household grouping, so an operator reads down the
// column that is broken instead of opening a card per family. A flagged cell edits in
// place: the save restages the row, the server re-validates, and the table repaints from
// the fresh report, so a fixed cell loses its flag without a re-upload.
//
// The spreadsheet is still the source of truth for a badly structured file (no head, two
// addresses under one QR). Those problems carry no single bad cell, so they surface as a
// flagged row with a notice and are fixed in the file.
//
// Backend: POST records/import/review/:id/commit|cancel|family/cell
(function (window, document) {
    'use strict';

    // Must match STORAGE_KEY in family-import.js so the write job's toast resumes there.
    var IMPORT_TRACK_KEY = 'binanFamilyImport';

    var root = document.getElementById('importReview');

    if (!root) {
        return;
    }

    var commitUrl   = root.dataset.commitUrl;
    var cancelUrl   = root.dataset.cancelUrl;
    var cellUrl     = root.dataset.cellUrl;
    var redirectUrl = root.dataset.redirectUrl;

    var table      = document.getElementById('importReviewTable');
    var tbody      = table ? table.querySelector('tbody') : null;
    var countEl    = document.getElementById('importReviewCount');
    var noticesEl  = document.getElementById('importReviewNotices');
    var fileEl     = document.getElementById('reviewFileName');
    var statusEl   = document.getElementById('importReviewStatus');
    var confirmBtn = document.getElementById('importReviewConfirm');
    var cancelBtn  = document.getElementById('importReviewCancel');
    var problemsOnlyBox = document.getElementById('problemsOnly');

    // The two action buttons are not optional: without them there is no way to finish
    // or discard the staged import, so a page missing them is not a review page.
    if (!confirmBtn || !cancelBtn) {
        return;
    }

    var review = parseJson('importReviewData', { file: '', counts: {}, people: [] });
    // Dropdown option lists (field => [option strings]) for the columns that are dropdowns
    // in the Excel template, so an inline cell edit offers the same choices as the sheet.
    var fieldOptions = parseJson('importReviewFieldOptions', {});

    function parseJson(id, fallback) {
        var node = document.getElementById(id);

        try {
            var parsed = JSON.parse(node ? node.textContent : 'null');

            return (parsed && typeof parsed === 'object') ? parsed : fallback;
        } catch (e) {
            return fallback;
        }
    }

    // -- small DOM helpers -----------------------------------------------------

    function el(tag, className, text) {
        var node = document.createElement(tag);

        if (className) {
            node.className = className;
        }

        if (text != null) {
            node.textContent = String(text);
        }

        return node;
    }

    function csrfField() {
        return document.getElementById('reviewCsrf');
    }

    function setStatus(message) {
        if (statusEl) {
            statusEl.textContent = message || '';
        }
    }

    // Promise-based confirm reusing the layout's #familyActionModal, so Cancel and Confirm
    // match the app's dialog instead of a native window.confirm. Resolves true on confirm.
    function confirmAction(opts) {
        opts = opts || {};

        var modalEl = document.getElementById('familyActionModal');
        var bs = window.bootstrap;

        if (!modalEl || !bs || !bs.Modal) {
            return Promise.resolve(window.confirm(opts.message || 'Are you sure?'));
        }

        var titleEl = modalEl.querySelector('#familyActionModalLabel');
        var msgEl = modalEl.querySelector('.js-family-action-message');
        var okBtn = modalEl.querySelector('.js-family-action-confirm');

        if (titleEl) {
            titleEl.textContent = opts.title || 'Please confirm';
        }
        if (msgEl) {
            msgEl.textContent = '';
            if (opts.node) {
                msgEl.appendChild(opts.node);
            } else {
                msgEl.textContent = opts.message || 'Are you sure?';
            }
        }
        if (okBtn) {
            okBtn.textContent = opts.confirmLabel || 'Confirm';
            okBtn.className = 'btn ' + (opts.confirmClass || 'btn-danger') + ' js-family-action-confirm';
        }

        var modal = bs.Modal.getOrCreateInstance(modalEl);

        return new Promise(function (resolve) {
            var settled = false;

            function cleanup() {
                okBtn.removeEventListener('click', onConfirm);
                modalEl.removeEventListener('hidden.bs.modal', onHidden);
            }

            function onConfirm() {
                settled = true;
                cleanup();
                modal.hide();
                resolve(true);
            }

            function onHidden() {
                cleanup();
                if (!settled) {
                    resolve(false);
                }
            }

            okBtn.addEventListener('click', onConfirm);
            modalEl.addEventListener('hidden.bs.modal', onHidden);
            modal.show();
        });
    }

    // -- the table -------------------------------------------------------------

    function render() {
        var counts = review.counts || {};
        var people = Array.isArray(review.people) ? review.people : [];

        if (fileEl) {
            fileEl.textContent = review.file || '';
        }

        renderNotices(review.fileNotices);

        if (tbody) {
            tbody.textContent = '';
            people.forEach(function (person) {
                tbody.appendChild(personRow(person));
            });
        }

        applyProblemsOnly();

        var blocking = Number(counts.blocking || 0);
        confirmBtn.disabled = blocking > 0;
        confirmBtn.title = blocking > 0
            ? 'Fix the flagged values first, or correct them in the spreadsheet and upload again.'
            : '';
    }

    // Whole-file problems (unreadable / empty workbook): there is no row to flag.
    function renderNotices(notices) {
        if (!noticesEl) {
            return;
        }

        noticesEl.textContent = '';

        (notices || []).forEach(function (message) {
            noticesEl.appendChild(el('div', 'alert alert-danger', message));
        });
    }

    function personRow(person) {
        var flagged = !!person.severity;
        var tr = el('tr', flagged ? (person.severity === 'blocking' ? 'table-danger' : 'table-warning') : '');
        tr.dataset.row = person.sheetRow;
        tr.dataset.flagged = flagged ? '1' : '0';

        tr.appendChild(statusCell(person.severity));
        tr.appendChild(el('td', null, person.family || ''));
        tr.appendChild(el('td', null, person.role || ''));

        ['lastname', 'firstname', 'birthday', 'sex'].forEach(function (field) {
            tr.appendChild(valueCell(person, field));
        });

        return tr;
    }

    function statusCell(severity) {
        var td = el('td', 'text-center');

        if (!severity) {
            return td;
        }

        var blocking = severity === 'blocking';
        var icon = el('i', 'bi ' + (blocking ? 'bi-exclamation-triangle-fill text-danger' : 'bi-exclamation-circle-fill text-warning'));
        icon.setAttribute('aria-hidden', 'true');
        icon.title = blocking ? 'Must fix' : 'Warning';
        td.appendChild(icon);
        td.appendChild(el('span', 'visually-hidden', blocking ? 'Must fix' : 'Warning'));

        return td;
    }

    // A clean value renders as text. A flagged one renders as its own editor, tinted by
    // severity, carrying the reason: the cell that is wrong is the cell you type into.
    function valueCell(person, field) {
        var values = person.values || {};
        var cell = (person.cells || {})[field];

        if (!cell) {
            return el('td', null, values[field] || '');
        }

        var td = el('td', cell.severity === 'blocking' ? 'bg-danger-subtle' : 'bg-warning-subtle');
        var options = fieldOptions[field];
        td.appendChild((options && options.length) ? buildCellSelect(cell, options) : buildCellInput(cell));

        if (cell.message) {
            var msg = el('div', 'form-text', cell.message);
            td.appendChild(msg);
        }

        return td;
    }

    // Shared data-* + a11y wiring for either control kind, plus the class the save handler
    // watches. data-original keeps a no-op edit from posting.
    function applyCellControlAttrs(control, cell) {
        control.classList.add('js-import-cell');
        control.dataset.row = cell.sheetRow;
        control.dataset.field = cell.field;
        control.dataset.original = cell.value || '';
        control.title = (cell.cell ? 'Cell ' + cell.cell + '. ' : '') + (cell.message || '');
        control.setAttribute('aria-label', cell.label + (cell.message ? ' - ' + cell.message : ''));
    }

    function buildCellInput(cell) {
        var input = el('input', 'form-control form-control-sm');
        input.type = 'text';
        input.value = cell.value || '';
        applyCellControlAttrs(input, cell);

        return input;
    }

    // A <select> mirroring the Excel column's dropdown. A blank first choice lets a
    // required-but-empty cell start unselected; an off-list current value is kept as its own
    // option so saving never silently drops what is already there.
    function buildCellSelect(cell, options) {
        var select = el('select', 'form-select form-select-sm');
        var current = cell.value || '';

        var blank = el('option', null, '- choose -');
        blank.value = '';
        select.appendChild(blank);

        var matched = current === '';
        options.forEach(function (opt) {
            var option = el('option', null, opt);
            option.value = opt;
            if (opt === current) {
                option.selected = true;
                matched = true;
            }
            select.appendChild(option);
        });

        if (!matched) {
            var keep = el('option', null, current + ' (current)');
            keep.value = current;
            keep.selected = true;
            select.appendChild(keep);
        }

        applyCellControlAttrs(select, cell);

        return select;
    }

    // The switch defaults to on: with a clean file the operator sees an empty table and
    // presses Confirm import. The footer always counts both, so an empty table is not
    // mistaken for an empty file.
    function applyProblemsOnly() {
        var only = !problemsOnlyBox || problemsOnlyBox.checked;
        var rows = tbody ? tbody.querySelectorAll('tr') : [];
        var shown = 0;

        Array.prototype.forEach.call(rows, function (tr) {
            var hide = only && tr.dataset.flagged === '0';
            tr.classList.toggle('d-none', hide);

            if (!hide) {
                shown += 1;
            }
        });

        if (countEl) {
            countEl.textContent = 'Showing ' + shown + ' of ' + rows.length + ' people'
                + (only ? ' (rows with problems only)' : '');
        }
    }

    // -- network ---------------------------------------------------------------

    function postForm(url, extra) {
        var body = new FormData();
        var field = csrfField();

        if (field) {
            body.append(field.name, field.value);
        }

        if (extra) {
            Object.keys(extra).forEach(function (key) {
                body.append(key, extra[key]);
            });
        }

        return window.fetch(url, {
            method: 'POST',
            body: body,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json().then(function (data) {
                return { ok: response.ok, code: response.status, data: data };
            }).catch(function () {
                return { ok: response.ok, code: response.status, data: {} };
            });
        });
    }

    function refreshCsrf(hash) {
        var field = csrfField();

        if (field && hash) {
            field.value = hash;
        }
    }

    // Applies a fresh report and repaints. Also called by manage-family-modal.js after an
    // edit saves, hence the global handle.
    function applyReview(freshReview, csrfHash) {
        if (freshReview) {
            review = freshReview;
        }

        refreshCsrf(csrfHash);
        render();
    }

    window.importReviewApply = applyReview;

    // -- actions ---------------------------------------------------------------

    // Persists one inline cell edit (only when its value actually changed), then repaints
    // from the fresh, re-validated report so the flag clears live.
    function saveCell(input) {
        if (!cellUrl) {
            return;
        }

        var value = input.value;
        var original = input.dataset.original != null ? input.dataset.original : '';

        if (value === original) {
            return;
        }

        input.disabled = true;
        setStatus('Saving ' + (input.dataset.field || 'cell') + '...');

        postForm(cellUrl, {
            import_row: input.dataset.row,
            field: input.dataset.field,
            value: value
        }).then(function (result) {
            var data = result.data || {};

            if (result.ok && data.review) {
                applyReview(data.review, data.csrf);
                setStatus(data.message || 'Saved.');

                return;
            }

            input.disabled = false;
            refreshCsrf(data.csrf);
            setStatus(data.message || 'Could not save. Please try again.');
        }).catch(function () {
            input.disabled = false;
            setStatus('A network error occurred. Please try again.');
        });
    }

    // Recap what the write job will do, then commit only on confirm: the write is not
    // reversible from this screen.
    function confirmImport() {
        var counts = review.counts || {};
        var newFamilies = Number(counts.newFamilies != null ? counts.newFamilies : (counts.families || 0));
        var appends = Number(counts.appends || 0);
        var skipped = Number(counts.existing || 0);
        var warnings = Number(counts.warnings || 0);

        var node = document.createElement('div');
        node.appendChild(el('p', 'mb-2', 'You are about to import:'));
        var list = el('ul', 'mb-2');
        list.appendChild(el('li', null, newFamilies + ' new famil' + (newFamilies === 1 ? 'y' : 'ies')));
        if (appends > 0) {
            list.appendChild(el('li', null, appends + ' member(s) added to existing families'));
        }
        if (skipped > 0) {
            list.appendChild(el('li', null, skipped + ' already in the system (skipped)'));
        }
        if (warnings > 0) {
            list.appendChild(el('li', null, warnings + ' warning(s) - imported as typed'));
        }
        node.appendChild(list);
        node.appendChild(el('p', 'mb-0 text-muted small', 'This cannot be undone from here.'));

        confirmAction({
            title: 'Confirm import',
            node: node,
            confirmLabel: 'Yes, import',
            confirmClass: 'btn-primary'
        }).then(function (ok) {
            if (ok) {
                doCommit();
            }
        });
    }

    function doCommit() {
        confirmBtn.disabled = true;
        cancelBtn.disabled = true;
        setStatus('Starting import...');

        postForm(commitUrl).then(function (result) {
            var data = result.data || {};
            refreshCsrf(data.csrf);

            if (result.ok && data.status === 'queued' && data.statusUrl) {
                rememberJob(data.statusUrl);
                window.location.href = data.redirect || redirectUrl;

                return;
            }

            // Refused: the file still has issues. Repaint from the fresh report.
            if (data.review) {
                review = data.review;
                render();
            }

            cancelBtn.disabled = false;
            setStatus(data.message || 'The import could not be started.');
        }).catch(function () {
            cancelBtn.disabled = false;
            confirmBtn.disabled = false;
            setStatus('A network error occurred. Please try again.');
        });
    }

    function cancelImport() {
        confirmAction({
            title: 'Discard import',
            message: 'Discard this import? Nothing will be saved.',
            confirmLabel: 'Discard',
            confirmClass: 'btn-danger'
        }).then(function (ok) {
            if (!ok) {
                return;
            }

            cancelBtn.disabled = true;
            setStatus('Cancelling...');

            postForm(cancelUrl).then(function (result) {
                var data = result.data || {};
                window.location.href = data.redirect || redirectUrl;
            }).catch(function () {
                cancelBtn.disabled = false;
                setStatus('A network error occurred. Please try again.');
            });
        });
    }

    // Hand the write job's status URL to family-import.js so its progress toast appears
    // on the records page after we redirect there.
    function rememberJob(statusUrl) {
        try {
            var raw = window.localStorage.getItem(IMPORT_TRACK_KEY);
            var list = raw ? JSON.parse(raw) : [];

            if (!Array.isArray(list)) {
                list = [];
            }

            if (list.indexOf(statusUrl) === -1) {
                list.push(statusUrl);
            }

            window.localStorage.setItem(IMPORT_TRACK_KEY, JSON.stringify(list));
        } catch (e) { /* private mode / quota - the import still runs, just no toast */ }
    }

    // -- wire up ---------------------------------------------------------------

    // Delegated: every row is repainted after a save, so per-control listeners would die.
    root.addEventListener('change', function (event) {
        var target = event.target;

        if (target && target.classList && target.classList.contains('js-import-cell')) {
            saveCell(target);
        }
    });

    // Enter commits an inline cell edit (blur fires the change handler above).
    root.addEventListener('keydown', function (event) {
        var target = event.target;

        if (event.key === 'Enter' && target && target.classList && target.classList.contains('js-import-cell')) {
            event.preventDefault();
            target.blur();
        }
    });

    if (problemsOnlyBox) {
        problemsOnlyBox.addEventListener('change', applyProblemsOnly);
    }

    confirmBtn.addEventListener('click', confirmImport);
    cancelBtn.addEventListener('click', cancelImport);

    render();
})(window, document);
