// Import Review — a READ-ONLY report.
//
// Nothing is edited here. The spreadsheet is the single source of truth: if fixes were
// applied in the browser the file would still hold the mistakes, and the next person to
// re-use it would import them again. So this screen's only job is to make fixing the FILE
// effortless — every issue names the exact Excel cell (e.g. "H42"), the column, what is
// there now, and what to do. Fix the file, upload it again.
//
// Backend: POST {role}/manage-family/import/review/:id/commit|cancel
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
    var redirectUrl = root.dataset.redirectUrl;

    var statsEl    = document.getElementById('importReviewStats');
    var groupsEl   = document.getElementById('importReviewGroups');
    var fileEl     = document.getElementById('reviewFileName');
    var statusEl   = document.getElementById('importReviewStatus');
    var confirmBtn = document.getElementById('importReviewConfirm');
    var cancelBtn  = document.getElementById('importReviewCancel');

    var review = parseJson();
    var view = 'type'; // 'type' = grouped by problem, 'row' = straight down the sheet

    function parseJson() {
        var node = document.getElementById('importReviewData');

        try {
            return JSON.parse(node ? node.textContent : '{}');
        } catch (e) {
            return { file: '', counts: {}, groups: [], byRow: [] };
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

    // A click-to-copy Excel cell reference, e.g. "H42". Paste into Excel's Go To (Ctrl+G).
    function cellRef(cell) {
        if (!cell) {
            return el('span', 'text-muted', '—');
        }

        var btn = el('button', 'btn btn-sm btn-outline-secondary import-review-cell', cell);
        btn.type = 'button';
        btn.title = 'Copy "' + cell + '" — paste into Excel with Ctrl+G to jump to it';
        btn.dataset.cell = cell;

        return btn;
    }

    // -- rendering -------------------------------------------------------------

    function render() {
        var counts = review.counts || {};

        if (fileEl) {
            fileEl.textContent = review.file || 'import.xlsx';
        }

        renderStats(counts);
        renderBody(counts);

        var blocking = Number(counts.blocking || 0);
        var appends = Number(counts.appends || 0);
        var newFamilies = Number(counts.newFamilies != null ? counts.newFamilies : (counts.families || 0));
        var nothingNew = (newFamilies + appends) <= 0;

        confirmBtn.disabled = blocking > 0 || nothingNew;
        confirmBtn.textContent = '';
        var icon = el('i', 'bi bi-check2-circle me-1');
        icon.setAttribute('aria-hidden', 'true');
        confirmBtn.appendChild(icon);

        var label;
        if (blocking > 0) {
            label = 'Fix ' + blocking + ' issue(s) in the file first';
        } else if (nothingNew) {
            label = 'Nothing new to import';
        } else {
            label = 'Confirm import (' + newFamilies + ' new'
                + (appends > 0 ? ', ' + appends + ' added to existing' : '') + ')';
        }
        confirmBtn.appendChild(document.createTextNode(label));
    }

    function statTile(label, value, colorClass) {
        var col = el('div', 'col-6 col-xl');
        var card = el('div', 'card h-100 shadow-sm');
        var body = el('div', 'card-body');
        body.appendChild(el('div', 'small text-muted text-uppercase', label));
        body.appendChild(el('div', 'h3 mb-0 ' + (colorClass || ''), String(value)));
        card.appendChild(body);
        col.appendChild(card);

        return col;
    }

    function renderStats(counts) {
        statsEl.textContent = '';
        var blocking = Number(counts.blocking || 0);
        var warnings = Number(counts.warnings || 0);
        var existing = Number(counts.existing || 0);
        var people = Number(counts.people != null ? counts.people : (counts.families || 0) + (counts.members || 0));
        statsEl.appendChild(statTile('Family groups', counts.families || 0, ''));
        statsEl.appendChild(statTile('Total members', people, ''));
        statsEl.appendChild(statTile('Already in system', existing, existing > 0 ? 'text-warning' : 'text-muted'));
        statsEl.appendChild(statTile('Issues to fix', blocking, blocking > 0 ? 'text-danger' : 'text-success'));
        statsEl.appendChild(statTile('Warnings', warnings, warnings > 0 ? 'text-warning' : 'text-muted'));
    }

    function renderBody(counts) {
        groupsEl.textContent = '';

        var groups = review.groups || [];

        if (!groups.length) {
            var ok = el('div', 'alert alert-success mb-0');
            ok.appendChild(el('strong', null, 'No issues found. '));
            ok.appendChild(document.createTextNode(
                (counts.families || 0) + ' family group(s) are ready. Press Confirm import to save them.'
            ));
            groupsEl.appendChild(ok);

            return;
        }

        groupsEl.appendChild(renderToolbar());

        if (view === 'row') {
            renderByRow();
        } else {
            groups.forEach(function (group) {
                groupsEl.appendChild(renderGroup(group));
            });
        }
    }

    // How to read the report + the view switch.
    function renderToolbar() {
        var bar = el('div', 'd-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 import-review-toolbar');

        var help = el('div', 'small text-muted');
        help.appendChild(el('strong', null, 'Fix these in your Excel file, then upload it again. '));
        help.appendChild(document.createTextNode(
            'Each issue shows the exact cell — click it to copy, then press Ctrl+G in Excel and paste to jump straight there.'
        ));
        bar.appendChild(help);

        var switcher = el('div', 'btn-group btn-group-sm');
        [['type', 'By problem'], ['row', 'By row']].forEach(function (pair) {
            var btn = el('button', 'btn ' + (view === pair[0] ? 'btn-primary' : 'btn-outline-primary') + ' js-view', pair[1]);
            btn.type = 'button';
            btn.dataset.view = pair[0];
            switcher.appendChild(btn);
        });
        bar.appendChild(switcher);

        return bar;
    }

    // -- grouped by problem type -----------------------------------------------

    function renderGroup(group) {
        var card = el('div', 'card mb-3 import-review-group');

        var header = el('div', 'card-header d-flex justify-content-between align-items-center flex-wrap');
        var left = el('span', 'fw-semibold');
        var badgeClass = group.severity === 'warning' ? 'bg-warning text-dark' : 'bg-danger';
        left.appendChild(el('span', 'badge me-2 ' + badgeClass, group.count));
        left.appendChild(document.createTextNode(group.label || group.code));
        header.appendChild(left);
        header.appendChild(el('small', 'text-muted', group.hint || ''));
        card.appendChild(header);

        var body = el('div', isFamilyCode(group.code) ? 'card-body' : 'card-body p-0');
        body.appendChild(isFamilyCode(group.code) ? renderFamilyPanels(group) : renderIssueTable(group.items));
        card.appendChild(body);

        return card;
    }

    function isFamilyCode(code) {
        return code === 'FP-ADDR' || code === 'HEAD-NONE' || code === 'HEAD-MULTI';
    }

    // Flat table of issues: the cell to fix, what's in it now, and the problem.
    function renderIssueTable(items) {
        var wrap = el('div', 'table-responsive');
        var table = el('table', 'table table-sm mb-0 align-middle import-review-table');

        var thead = el('thead');
        var htr = el('tr');
        ['Cell', 'Row', 'QR', 'Person', 'Column', 'Value now', 'What to do'].forEach(function (h) {
            htr.appendChild(el('th', null, h));
        });
        thead.appendChild(htr);
        table.appendChild(thead);

        var tbody = el('tbody');
        items.forEach(function (item) {
            tbody.appendChild(renderIssueRow(item));
        });
        table.appendChild(tbody);
        wrap.appendChild(table);

        return wrap;
    }

    function renderIssueRow(item) {
        var tr = el('tr');

        var cellTd = el('td');
        cellTd.appendChild(cellRef(item.cell));
        tr.appendChild(cellTd);

        tr.appendChild(el('td', 'text-nowrap', item.sheetRow != null ? item.sheetRow : '—'));
        tr.appendChild(el('td', 'text-nowrap', item.familyNo || '—'));
        tr.appendChild(el('td', null, item.name || '—'));
        tr.appendChild(el('td', 'text-nowrap small', item.column || '—'));

        var valueTd = el('td', 'small');
        // NB: "0" is a real value (a zero QR) — don't let it fall into the blank branch.
        if (item.value !== '' && item.value != null) {
            valueTd.appendChild(el('code', 'import-review-value', item.value));
        } else {
            valueTd.appendChild(el('span', 'text-muted', '(blank)'));
        }
        tr.appendChild(valueTd);

        tr.appendChild(el('td', 'small', item.message));

        return tr;
    }

    // -- family-structure problems: show the whole family for context -----------

    function renderFamilyPanels(group) {
        var wrap = el('div', 'd-flex flex-column gap-3');

        group.items.forEach(function (item) {
            wrap.appendChild(renderFamilyPanel(item));
        });

        return wrap;
    }

    function renderFamilyPanel(item) {
        var panel = el('div', 'import-review-family border rounded p-2');
        panel.appendChild(el('div', 'fw-semibold', 'Family ' + (item.familyNo || '')));

        var sev = item.severity === 'warning' ? 'warning' : 'danger';
        panel.appendChild(el('div', 'small fw-semibold mb-2 text-' + sev, item.message));

        var wrap = el('div', 'table-responsive');
        var table = el('table', 'table table-sm mb-0 align-middle import-review-table');

        var thead = el('thead');
        var htr = el('tr');
        ['Row', 'Person', 'Relationship', 'Barangay', 'Address', 'QR', 'Cells'].forEach(function (h) {
            htr.appendChild(el('th', null, h));
        });
        thead.appendChild(htr);
        table.appendChild(thead);

        var tbody = el('tbody');
        (item.familyRows || []).forEach(function (r) {
            var flagged = item.sheetRow != null && Number(item.sheetRow) === Number(r.sheetRow);
            tbody.appendChild(renderFamilyContextRow(r, flagged, item.code));
        });
        table.appendChild(tbody);
        wrap.appendChild(table);
        panel.appendChild(wrap);

        return panel;
    }

    function renderFamilyContextRow(r, flagged, code) {
        var tr = el('tr');

        if (flagged) {
            tr.className = 'import-review-flagged';
        }

        tr.appendChild(el('td', 'text-nowrap', r.sheetRow));

        var personCell = el('td');
        personCell.appendChild(document.createTextNode(r.name || '—'));

        // Only the Head fills the address, so in a head-less family the row carrying one
        // is the likely Head — say so instead of making the operator guess.
        if (flagged && code === 'HEAD-NONE') {
            personCell.appendChild(document.createTextNode(' '));
            personCell.appendChild(el('span', 'badge bg-primary', 'Likely Head'));
        } else if (flagged && code === 'HEAD-MULTI') {
            personCell.appendChild(document.createTextNode(' '));
            personCell.appendChild(el('span', 'badge bg-danger', 'Extra Head'));
        }
        tr.appendChild(personCell);

        tr.appendChild(el('td', 'small', r.relationship || '—'));
        tr.appendChild(el('td', 'small', r.barangay || '—'));
        tr.appendChild(el('td', 'small import-review-addr', r.address || '—'));
        tr.appendChild(el('td', 'small', r.qr || '—'));

        // The two cells you'd normally change to fix a family problem.
        var cells = el('td', 'text-nowrap');
        cells.appendChild(cellRef(r.qrCell));
        cells.appendChild(document.createTextNode(' '));
        cells.appendChild(cellRef(r.relCell));
        tr.appendChild(cells);

        return tr;
    }

    // -- ordered straight down the sheet ---------------------------------------

    function renderByRow() {
        var card = el('div', 'card mb-3 import-review-group');
        var header = el('div', 'card-header fw-semibold');
        header.textContent = 'Every issue, in sheet order';
        card.appendChild(header);

        var body = el('div', 'card-body p-0');
        var wrap = el('div', 'table-responsive');
        var table = el('table', 'table table-sm mb-0 align-middle import-review-table');

        var thead = el('thead');
        var htr = el('tr');
        ['Row', 'QR', 'Person', 'Cell', 'Column', 'Value now', 'What to do'].forEach(function (h) {
            htr.appendChild(el('th', null, h));
        });
        thead.appendChild(htr);
        table.appendChild(thead);

        var tbody = el('tbody');
        (review.byRow || []).forEach(function (entry) {
            entry.issues.forEach(function (item, i) {
                var tr = el('tr');

                if (i === 0) {
                    tr.className = 'import-review-rowstart';
                }

                tr.appendChild(el('td', 'text-nowrap fw-semibold', i === 0 ? (entry.sheetRow != null ? entry.sheetRow : '—') : ''));
                tr.appendChild(el('td', 'text-nowrap', i === 0 ? (entry.familyNo || '—') : ''));
                tr.appendChild(el('td', null, i === 0 ? (entry.name || '—') : ''));

                var cellTd = el('td');
                cellTd.appendChild(cellRef(item.cell));
                tr.appendChild(cellTd);

                tr.appendChild(el('td', 'text-nowrap small', item.column || '—'));

                var valueTd = el('td', 'small');
                if (item.value) {
                    valueTd.appendChild(el('code', 'import-review-value', item.value));
                } else {
                    valueTd.appendChild(el('span', 'text-muted', '(blank)'));
                }
                tr.appendChild(valueTd);

                var what = el('td', 'small');
                var badge = el('span', 'badge me-1 ' + (item.severity === 'warning' ? 'bg-warning text-dark' : 'bg-danger'), item.severity === 'warning' ? 'warn' : 'fix');
                what.appendChild(badge);
                what.appendChild(document.createTextNode(item.message));
                tr.appendChild(what);

                tbody.appendChild(tr);
            });
        });
        table.appendChild(tbody);
        wrap.appendChild(table);
        body.appendChild(wrap);
        card.appendChild(body);

        groupsEl.appendChild(card);
    }

    // -- network ---------------------------------------------------------------

    function postForm(url) {
        var body = new FormData();
        var field = csrfField();

        if (field) {
            body.append(field.name, field.value);
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

    // -- actions ---------------------------------------------------------------

    function confirmImport() {
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

            // Refused: the file still has issues. Re-render the fresh report.
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
        if (!window.confirm('Discard this import? Nothing will be saved.')) {
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
        } catch (e) { /* private mode / quota — the import still runs, just no toast */ }
    }

    // -- wire up ---------------------------------------------------------------

    groupsEl.addEventListener('click', function (event) {
        var target = event.target;

        if (!target || !target.closest) {
            return;
        }

        var cellBtn = target.closest('.import-review-cell');
        if (cellBtn) {
            copyCell(cellBtn);

            return;
        }

        var viewBtn = target.closest('.js-view');
        if (viewBtn) {
            view = viewBtn.dataset.view;
            render();
        }
    });

    function copyCell(button) {
        var cell = button.dataset.cell || '';

        var done = function () {
            var was = button.textContent;
            button.textContent = 'Copied ' + cell;
            button.classList.add('btn-success');
            button.classList.remove('btn-outline-secondary');
            setStatus('Copied "' + cell + '" — press Ctrl+G in Excel and paste to jump to it.');
            window.setTimeout(function () {
                button.textContent = was;
                button.classList.remove('btn-success');
                button.classList.add('btn-outline-secondary');
            }, 1200);
        };

        if (window.navigator.clipboard && window.navigator.clipboard.writeText) {
            window.navigator.clipboard.writeText(cell).then(done).catch(function () { done(); });

            return;
        }

        // Fallback for non-secure contexts.
        var tmp = document.createElement('input');
        tmp.value = cell;
        document.body.appendChild(tmp);
        tmp.select();
        try { document.execCommand('copy'); } catch (e) { /* nothing to do */ }
        document.body.removeChild(tmp);
        done();
    }

    confirmBtn.addEventListener('click', confirmImport);
    cancelBtn.addEventListener('click', cancelImport);

    render();
})(window, document);
