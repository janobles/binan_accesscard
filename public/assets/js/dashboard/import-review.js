// Import Review screen. Renders the staged family-import errors (grouped by type) from
// the JSON island written by Views/Family/import-review.php, lets the operator fix each
// value inline, re-validates server-side on every save, and — once no blocking issues
// remain — confirms the import (queues the write job and hands its progress toast to
// family-import.js on the records page via localStorage).
//
// Backend: POST {role}/manage-family/import/review/:id/row|commit|cancel
(function (window, document) {
    'use strict';

    // Must match STORAGE_KEY in family-import.js so a handed-off write job resumes there.
    var IMPORT_TRACK_KEY = 'binanFamilyImport';

    var root = document.getElementById('importReview');

    if (!root) {
        return;
    }

    var rowUrl      = root.dataset.rowUrl;
    var commitUrl   = root.dataset.commitUrl;
    var cancelUrl   = root.dataset.cancelUrl;
    var redirectUrl = root.dataset.redirectUrl;

    var statsEl   = document.getElementById('importReviewStats');
    var groupsEl  = document.getElementById('importReviewGroups');
    var fileEl    = document.getElementById('reviewFileName');
    var statusEl  = document.getElementById('importReviewStatus');
    var confirmBtn = document.getElementById('importReviewConfirm');
    var cancelBtn = document.getElementById('importReviewCancel');

    var state = { review: parseJson() };
    var relationshipOptions = [];
    // QR numbers the operator has touched — kept visible/editable even once fixed.
    var pinned = {};
    // The family being edited, so the view stays anchored on it across re-renders.
    var activeFamily = '';

    function pin(qr) {
        qr = String(qr == null ? '' : qr).trim();
        if (qr !== '') {
            pinned[qr] = true;
        }
    }

    function pinnedList() {
        return Object.keys(pinned).join(',');
    }

    function parseJson() {
        var node = document.getElementById('importReviewData');

        try {
            return JSON.parse(node ? node.textContent : '{}');
        } catch (e) {
            return { file: '', counts: {}, groups: [] };
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

    function withCsrf(body) {
        var field = csrfField();

        if (field) {
            body.append(field.name, field.value);
        }

        return body;
    }

    function refreshCsrf(hash) {
        var field = csrfField();

        if (field && hash) {
            field.value = hash;
        }
    }

    function setStatus(message) {
        if (statusEl) {
            statusEl.textContent = message || '';
        }
    }

    // -- rendering -------------------------------------------------------------

    function render() {
        var review = state.review || {};
        var counts = review.counts || {};
        var groups = review.groups || [];
        relationshipOptions = review.relationshipOptions || [];

        if (fileEl) {
            fileEl.textContent = review.file || 'import.xlsx';
        }

        renderStats(counts);
        renderGroups(groups, counts);

        var blocking = Number(counts.blocking || 0);
        var pending = Number(counts.appendsPending || 0);
        var toImport = Number(counts.appendsToImport || 0);
        var newFamilies = Number(counts.newFamilies != null ? counts.newFamilies : (counts.families || 0));
        var nothingNew = (newFamilies + toImport) <= 0;

        confirmBtn.disabled = blocking > 0 || pending > 0 || nothingNew;
        confirmBtn.textContent = '';
        var icon = el('i', 'bi bi-check2-circle me-1');
        icon.setAttribute('aria-hidden', 'true');
        confirmBtn.appendChild(icon);

        var label;
        if (blocking > 0) {
            label = 'Fix ' + blocking + ' issue(s) to import';
        } else if (pending > 0) {
            label = 'Decide ' + pending + ' member(s) to import';
        } else if (nothingNew) {
            label = 'Nothing new to import';
        } else {
            label = 'Confirm import (' + newFamilies + ' new' + (toImport > 0 ? ', ' + toImport + ' added' : '') + ')';
        }
        confirmBtn.appendChild(document.createTextNode(label));

        anchorActiveFamily();
    }

    // Keep the panel you just edited in view — no scrolling to chase it.
    function anchorActiveFamily() {
        if (!activeFamily) {
            return;
        }

        try {
            var panel = groupsEl.querySelector('.import-review-family[data-family="' + activeFamily.replace(/"/g, '') + '"]');
            if (panel && panel.scrollIntoView) {
                panel.scrollIntoView({ block: 'nearest' });
            }
        } catch (e) { /* invalid selector — leave the scroll where it is */ }
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
        // "Total members" = every individual (heads + members), not just the non-heads.
        var people = Number(counts.people != null ? counts.people : (counts.families || 0) + (counts.members || 0));
        statsEl.appendChild(statTile('Family groups', counts.families || 0, ''));
        statsEl.appendChild(statTile('Total members', people, ''));
        statsEl.appendChild(statTile('Already in system', existing, existing > 0 ? 'text-warning' : 'text-muted'));
        statsEl.appendChild(statTile('Issues to fix', blocking, blocking > 0 ? 'text-danger' : 'text-success'));
        statsEl.appendChild(statTile('Warnings', warnings, warnings > 0 ? 'text-warning' : 'text-muted'));
    }

    function renderGroups(groups, counts) {
        groupsEl.textContent = '';

        if (!groups.length) {
            var ok = el('div', 'alert alert-success mb-0');
            ok.appendChild(el('strong', null, 'No issues found. '));
            ok.appendChild(document.createTextNode(
                (counts.families || 0) + ' family group(s) are ready. Press Confirm import to save them.'
            ));
            groupsEl.appendChild(ok);

            return;
        }

        groups.forEach(function (group) {
            groupsEl.appendChild(renderGroup(group));
        });
    }

    function renderGroup(group) {
        var card = el('div', 'card mb-3 import-review-group');

        var header = el('div', 'card-header d-flex justify-content-between align-items-center flex-wrap');
        var left = el('span', 'fw-semibold');
        var badgeClass = group.severity === 'warning' ? 'bg-warning text-dark'
            : (group.severity === 'ok' ? 'bg-success' : 'bg-danger');
        var badge = el('span', 'badge me-2 ' + badgeClass, group.count);
        left.appendChild(badge);
        left.appendChild(document.createTextNode(group.label || group.code));
        header.appendChild(left);
        header.appendChild(el('small', 'text-muted', group.hint || ''));
        card.appendChild(header);

        // Family-structure problems (missing/extra head, split address) are fixed from a
        // panel of the whole family's rows. Other editable groups use the flat table;
        // note-only groups render as a plain list.
        var body;
        if (isFamilyCode(group.code)) {
            body = el('div', 'card-body');
            body.appendChild(renderFamilyPanels(group));
        } else if (groupHasEditable(group)) {
            body = el('div', 'card-body p-0');
            body.appendChild(renderTable(group));
        } else {
            body = el('div', 'card-body');
            body.appendChild(renderNoteList(group));
        }
        card.appendChild(body);

        return card;
    }

    function isFamilyCode(code) {
        return code === 'FP-ADDR' || code === 'HEAD-NONE' || code === 'HEAD-MULTI' || code === 'WORKING';
    }

    function familyGuidance(code) {
        if (code === 'FP-ADDR') {
            return 'Two different addresses share this QR. Either fix a mistyped QR below, or give one household its own QR by changing its rows to a new QR number.';
        }
        if (code === 'HEAD-NONE') {
            return 'No one is marked Head. Set exactly one person’s relationship to Head.';
        }
        if (code === 'HEAD-MULTI') {
            return 'More than one person is Head. Change the extra one to their real relationship.';
        }

        return '';
    }

    function renderFamilyPanels(group) {
        var wrap = el('div', 'd-flex flex-column gap-3');

        group.items.forEach(function (item) {
            wrap.appendChild(renderFamilyPanel(group.code, item));
        });

        return wrap;
    }

    function renderFamilyPanel(code, item) {
        var panel = el('div', 'import-review-family border rounded p-2');
        panel.dataset.family = item.familyNo || '';
        panel.appendChild(el('div', 'fw-semibold', 'Family ' + (item.familyNo || '')));

        var issues = item.fieldIssues || [];

        // Status line — updates in place as you edit, so the panel never has to move.
        if (item.message) {
            var sev = item.severity === 'warning' ? 'warning' : (item.severity === 'ok' ? 'success' : 'danger');
            panel.appendChild(el('div', 'small fw-semibold mb-1 text-' + sev, item.message));
        } else if (issues.length === 0) {
            panel.appendChild(el('div', 'small fw-semibold mb-1 text-success', 'Ready — no remaining issues for this family.'));
        } else {
            panel.appendChild(el('div', 'small fw-semibold mb-1 text-danger', issues.length + ' field issue(s) to fix below.'));
        }

        var g = familyGuidance(item.statusCode || code);
        if (g) {
            panel.appendChild(el('div', 'text-muted small mb-2', g));
        }

        var wrap = el('div', 'table-responsive');
        var table = el('table', 'table table-sm mb-0 align-middle import-review-table');

        var thead = el('thead');
        var htr = el('tr');
        ['Row', 'Person', 'Relationship', 'Barangay', 'Address', 'QR'].forEach(function (h) {
            htr.appendChild(el('th', null, h));
        });
        thead.appendChild(htr);
        table.appendChild(thead);

        var tbody = el('tbody');
        (item.familyRows || []).forEach(function (r) {
            tbody.appendChild(renderFamilyRow(r, item.familyNo || ''));
        });
        table.appendChild(tbody);
        wrap.appendChild(table);
        panel.appendChild(wrap);

        // Per-field fixes for this family, inline — so everything is in one place.
        if (issues.length) {
            panel.appendChild(el('div', 'small text-muted mt-2 mb-1', 'Fix these fields:'));
            var fwrap = el('div', 'table-responsive');
            var ftable = el('table', 'table table-sm mb-0 align-middle import-review-table');
            var fthead = el('thead');
            var fhtr = el('tr');
            ['Row', 'QR', 'Person', 'Problem', 'Fix'].forEach(function (h) {
                fhtr.appendChild(el('th', null, h));
            });
            fthead.appendChild(fhtr);
            ftable.appendChild(fthead);
            var ftbody = el('tbody');
            issues.forEach(function (fi) {
                ftbody.appendChild(renderItemRow(fi));
            });
            ftable.appendChild(ftbody);
            fwrap.appendChild(ftable);
            panel.appendChild(fwrap);
        }

        return panel;
    }

    function renderFamilyRow(r, familyNo) {
        var tr = el('tr');
        tr.appendChild(el('td', 'text-nowrap', 'Row ' + r.sheetRow));
        tr.appendChild(el('td', null, r.name || '-'));

        var relCell = el('td');
        relCell.appendChild(buildRelationshipSelect(r, familyNo));
        tr.appendChild(relCell);

        tr.appendChild(el('td', 'small', r.barangay || '-'));
        tr.appendChild(el('td', 'small import-review-addr', r.address || '-'));

        var qrCell = el('td');
        var group = el('div', 'input-group input-group-sm import-review-fix');
        var input = el('input', 'form-control');
        input.type = 'text';
        input.value = r.qr || '';
        input.setAttribute('aria-label', 'QR number for row ' + r.sheetRow);

        var save = el('button', 'btn btn-outline-primary js-save', 'Save');
        save.type = 'button';
        save.dataset.sheetRow = r.sheetRow;
        save.dataset.field = 'familyno';
        save.dataset.family = familyNo || r.qr || '';

        group.appendChild(input);
        group.appendChild(save);
        qrCell.appendChild(group);
        tr.appendChild(qrCell);

        return tr;
    }

    function buildRelationshipSelect(r, familyNo) {
        var select = el('select', 'form-select form-select-sm js-rel');
        select.dataset.sheetRow = r.sheetRow;
        select.dataset.family = familyNo || r.qr || '';
        select.setAttribute('aria-label', 'Relationship for row ' + r.sheetRow);

        var current = String(r.relationship || '');
        var list = (relationshipOptions.length ? relationshipOptions : ['Head', 'Spouse', 'Child', 'Parent', 'Other']).slice();

        // Keep an unusual typed relationship selectable rather than silently dropping it.
        if (current !== '' && list.map(String).map(lower).indexOf(lower(current)) === -1) {
            list.unshift(current);
        }

        list.forEach(function (option) {
            var opt = el('option', null, option);
            opt.value = option;

            if (lower(current) === lower(option)) {
                opt.selected = true;
            }

            select.appendChild(opt);
        });

        return select;
    }

    function lower(value) {
        return String(value == null ? '' : value).toLowerCase();
    }

    function groupHasEditable(group) {
        for (var i = 0; i < group.items.length; i++) {
            if (group.items[i].editable) {
                return true;
            }
        }

        return false;
    }

    function renderNoteList(group) {
        var list = el('ul', 'mb-0 import-review-notes');

        group.items.forEach(function (item) {
            list.appendChild(el('li', null, item.message));
        });

        return list;
    }

    function renderTable(group) {
        var wrap = el('div', 'table-responsive');
        var table = el('table', 'table table-sm mb-0 align-middle import-review-table');

        var thead = el('thead');
        var htr = el('tr');
        ['Row', 'QR', 'Person', 'Problem', 'Fix'].forEach(function (h) {
            htr.appendChild(el('th', null, h));
        });
        thead.appendChild(htr);
        table.appendChild(thead);

        var tbody = el('tbody');
        group.items.forEach(function (item) {
            tbody.appendChild(renderItemRow(item));
        });
        table.appendChild(tbody);
        wrap.appendChild(table);

        return wrap;
    }

    function renderItemRow(item) {
        var tr = el('tr');

        if (!item.editable) {
            // File-level problem (e.g. merged cells) — a note, not an editable cell.
            var td = el('td', 'text-muted');
            td.colSpan = 5;
            td.textContent = item.message;
            tr.appendChild(td);

            return tr;
        }

        tr.appendChild(el('td', 'text-nowrap', item.sheetRow != null ? 'Row ' + item.sheetRow : '-'));
        tr.appendChild(el('td', 'text-nowrap', item.familyNo || '-'));
        tr.appendChild(el('td', null, item.name || '-'));
        tr.appendChild(el('td', 'small', item.message));

        var fixCell = el('td');

        // "Add to existing family" rows offer a choice, not a text fix.
        if (item.field === '_decision') {
            fixCell.appendChild(buildDecision(item));
        } else {
            var group = el('div', 'input-group input-group-sm import-review-fix');
            var input = el('input', 'form-control');
            input.type = 'text';
            input.value = item.value || '';
            input.setAttribute('aria-label', 'New value');

            var save = el('button', 'btn btn-outline-primary js-save', 'Save');
            save.type = 'button';
            save.dataset.sheetRow = item.sheetRow;
            save.dataset.field = item.field;
            save.dataset.family = item.familyNo || '';

            group.appendChild(input);
            group.appendChild(save);
            fixCell.appendChild(group);
        }

        tr.appendChild(fixCell);

        return tr;
    }

    function buildDecision(item) {
        var select = el('select', 'form-select form-select-sm js-decision import-review-fix');
        select.dataset.sheetRow = item.sheetRow;
        select.dataset.family = item.familyNo || '';
        select.setAttribute('aria-label', 'Add or remove this member');

        var current = String(item.value || '').toLowerCase();

        [['', '— choose —'], ['append', 'Add to family'], ['remove', 'Remove']].forEach(function (pair) {
            var option = el('option', null, pair[1]);
            option.value = pair[0];

            if (current === pair[0] && pair[0] !== '') {
                option.selected = true;
            }

            select.appendChild(option);
        });

        return select;
    }

    // -- network ---------------------------------------------------------------

    function postForm(url, body) {
        body.append('pinned', pinnedList());

        return window.fetch(url, {
            method: 'POST',
            body: withCsrf(body),
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

    // -- actions ---------------------------------------------------------------

    // Patches one staged cell (sheetRow + field + value), re-validates, and re-renders.
    function saveField(sheetRow, field, value, control) {
        // Keep the touched family (and, on a QR change, the new family) on screen, and
        // remember it so the view stays anchored on it after the re-render.
        if (control && control.dataset && control.dataset.family) {
            pin(control.dataset.family);
            activeFamily = control.dataset.family;
        }
        if (field === 'familyno') {
            pin(value);
        }

        var body = new FormData();
        body.append('sheetRow', sheetRow);
        body.append('field', field);
        body.append('value', value);

        if (control) {
            control.disabled = true;
        }

        setStatus('Saving...');

        postForm(rowUrl, body).then(function (result) {
            var data = result.data || {};
            refreshCsrf(data.csrf);

            if (result.ok && data.status === 'ok' && data.review) {
                state.review = data.review;
                render();
                setStatus('Saved.');

                return;
            }

            if (control) {
                control.disabled = false;
            }
            setStatus(data.message || 'That change could not be saved.');
        }).catch(function () {
            if (control) {
                control.disabled = false;
            }
            setStatus('A network error occurred. Please try again.');
        });
    }

    function saveRow(button) {
        var input = button.parentNode.querySelector('input');

        if (input) {
            saveField(button.dataset.sheetRow, button.dataset.field, input.value, button);
        }
    }

    function confirmImport() {
        confirmBtn.disabled = true;
        cancelBtn.disabled = true;
        setStatus('Starting import...');

        postForm(commitUrl, new FormData()).then(function (result) {
            var data = result.data || {};
            refreshCsrf(data.csrf);

            if (result.ok && data.status === 'queued' && data.statusUrl) {
                rememberJob(data.statusUrl);
                window.location.href = data.redirect || redirectUrl;

                return;
            }

            // Blocked: still has issues. Re-render with the fresh error set.
            if (data.review) {
                state.review = data.review;
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

        postForm(cancelUrl, new FormData()).then(function (result) {
            var data = result.data || {};
            window.location.href = data.redirect || redirectUrl;
        }).catch(function () {
            cancelBtn.disabled = false;
            setStatus('A network error occurred. Please try again.');
        });
    }

    // Add the write job's status URL to the list family-import.js resumes from, so its
    // progress toast appears on the records page after we redirect there.
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
        var button = event.target.closest ? event.target.closest('.js-save') : null;

        if (button) {
            saveRow(button);
        }
    });

    groupsEl.addEventListener('change', function (event) {
        var select = event.target;

        if (!select || !select.classList) {
            return;
        }

        if (select.classList.contains('js-decision')) {
            saveField(select.dataset.sheetRow, '_decision', select.value, select);
        } else if (select.classList.contains('js-rel')) {
            saveField(select.dataset.sheetRow, 'relationship', select.value, select);
        }
    });

    groupsEl.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter' || event.target.tagName !== 'INPUT') {
            return;
        }

        event.preventDefault();
        var button = event.target.parentNode.querySelector('.js-save');

        if (button && !button.disabled) {
            saveRow(button);
        }
    });

    confirmBtn.addEventListener('click', confirmImport);
    cancelBtn.addEventListener('click', cancelImport);

    render();
})(window, document);
