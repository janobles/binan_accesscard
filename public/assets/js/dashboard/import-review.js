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

    var commitUrl     = root.dataset.commitUrl;
    var cancelUrl     = root.dataset.cancelUrl;
    var familyBaseUrl = root.dataset.familyBaseUrl;
    var redirectUrl   = root.dataset.redirectUrl;

    var statsEl    = document.getElementById('importReviewStats');
    var groupsEl   = document.getElementById('importReviewGroups');
    var fileEl     = document.getElementById('reviewFileName');
    var statusEl   = document.getElementById('importReviewStatus');
    var confirmBtn = document.getElementById('importReviewConfirm');
    var cancelBtn  = document.getElementById('importReviewCancel');

    var review = parseJson();

    // Client-side filter over the "Families to fix" list. Persists across re-renders (Edit /
    // Remove / Save rebuild the card) so the operator's focus filter survives an action.
    var familyFilter = { search: '', severity: 'all', code: 'all' };
    var familyPage = 1;       // current page of the "Families to fix" list
    var familyPageSize = 25;  // rows per page (Manage Records default)
    var readyCollapsed = true; // Ready-to-import list starts hidden behind its Show button

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
        var ready = Number(counts.ready || 0);

        // These two describe the FILE — every person and every QR group in it, broken ones
        // included. They must never fall back to the families/members counts, which only
        // describe what the importer could BUILD: those omit head-less groups, two-head
        // groups and bad-QR rows, i.e. precisely the people the operator has to go fix.
        var people = Number(counts.rows || 0);
        var groups = Number(counts.groups || 0);

        statsEl.appendChild(statTile('People in file', people, ''));
        statsEl.appendChild(statTile('Family groups', groups, ''));
        statsEl.appendChild(statTile('Ready to import', ready, ready > 0 ? 'text-success' : 'text-muted'));
        statsEl.appendChild(statTile('Already in system', existing, existing > 0 ? 'text-warning' : 'text-muted'));
        statsEl.appendChild(statTile('Issues to fix', blocking, blocking > 0 ? 'text-danger' : 'text-success'));
        statsEl.appendChild(statTile('Warnings', warnings, warnings > 0 ? 'text-warning' : 'text-muted'));
    }

    function renderBody(counts) {
        groupsEl.textContent = '';

        // Whole-file problems (unreadable / empty) — nothing to edit; upload a corrected file.
        var notices = review.fileNotices || [];
        if (notices.length) {
            var alert = el('div', 'alert alert-danger');
            alert.appendChild(el('strong', null, 'This file could not be fully read. '));
            alert.appendChild(document.createTextNode(notices.join(' ') + ' Upload a corrected file.'));
            groupsEl.appendChild(alert);
        }

        // Rows with no QR number can't be grouped into a family — surface them first so the
        // operator can give them a QR and fix them in place.
        var needsQr = renderNeedsQr();
        if (needsQr) {
            groupsEl.appendChild(needsQr);
        }

        // The flagged families, each with Edit (fix in place) and Remove.
        var families = renderFamiliesToFix();
        if (families) {
            groupsEl.appendChild(families);
        }

        // What is CORRECT and will be saved — behind its own Show button so the screen leads
        // with the problems.
        groupsEl.appendChild(renderReady(counts));
    }

    // -- families to fix (in-place edit / remove) -------------------------------

    // -- rows with no QR (edit to assign one) -----------------------------------

    function renderNeedsQr() {
        var rows = review.unassigned || [];

        if (!rows.length || !familyBaseUrl) {
            return null;
        }

        var card = el('div', 'card mb-3 import-review-needsqr');

        var header = el('div', 'card-header');
        var title = el('span', 'fw-semibold');
        var icon = el('i', 'bi bi-question-circle me-2');
        icon.setAttribute('aria-hidden', 'true');
        title.appendChild(icon);
        title.appendChild(el('span', 'badge bg-danger me-2', rows.length));
        title.appendChild(document.createTextNode('Needs a QR number'));
        header.appendChild(title);
        header.appendChild(el('small', 'text-muted',
            'These rows have no QR, so they are not part of any family yet. Edit to give each a QR — rows with the same QR join one family — or Remove.'));
        card.appendChild(header);

        var body = el('div', 'card-body');
        var wrap = el('div', 'table-responsive');
        var table = el('table', 'table manage-record-table align-middle mb-0 import-review-table');

        var thead = el('thead');
        var htr = el('tr');
        ['Row', 'Person', 'Issues', 'Actions'].forEach(function (h) {
            htr.appendChild(el('th', null, h));
        });
        thead.appendChild(htr);
        table.appendChild(thead);

        var tbody = el('tbody');
        rows.forEach(function (row) {
            tbody.appendChild(renderNeedsQrRow(row));
        });
        table.appendChild(tbody);
        wrap.appendChild(table);
        body.appendChild(wrap);
        card.appendChild(body);

        return card;
    }

    function renderNeedsQrRow(row) {
        var tr = el('tr');

        tr.appendChild(el('td', 'text-nowrap', row.sheetRow != null ? row.sheetRow : '—'));
        tr.appendChild(el('td', null, row.person || '—'));

        var issues = el('td', 'small');
        var types = row.types || [];
        if (types.length) {
            types.forEach(function (type) {
                var cls = type.severity === 'blocking'
                    ? 'badge bg-danger me-1 mb-1'
                    : 'badge bg-warning text-dark me-1 mb-1';
                issues.appendChild(el('span', cls, type.label || type.code));
            });
        } else {
            issues.appendChild(el('span', 'badge bg-danger', 'No QR number'));
        }
        tr.appendChild(issues);

        var actions = el('td', 'text-nowrap');

        var edit = el('button', 'btn btn-sm btn-primary me-1 js-import-fix-edit', 'Edit');
        edit.type = 'button';
        edit.dataset.modalUrl = familyBaseUrl + '?row=' + encodeURIComponent(row.sheetRow);
        edit.dataset.modalTitle = 'Assign a QR — Row ' + (row.sheetRow || '');
        actions.appendChild(edit);

        var remove = el('button', 'btn btn-sm btn-outline-danger js-import-fix-remove', 'Remove');
        remove.type = 'button';
        remove.dataset.row = row.sheetRow;
        actions.appendChild(remove);

        tr.appendChild(actions);

        return tr;
    }

    // -- families to fix --------------------------------------------------------

    function renderFamiliesToFix() {
        var families = review.families || [];

        if (!families.length || !familyBaseUrl) {
            return null;
        }

        var card = el('div', 'card mb-3 import-review-families');

        // Header — Manage Records style: icon + title + total badge.
        var header = el('div', 'card-header');
        var title = el('span', 'fw-semibold');
        var icon = el('i', 'bi bi-tools me-2');
        icon.setAttribute('aria-hidden', 'true');
        title.appendChild(icon);
        title.appendChild(el('span', 'badge bg-primary me-2', families.length));
        title.appendChild(document.createTextNode('Families to fix'));
        header.appendChild(title);
        card.appendChild(header);

        var body = el('div', 'card-body');

        var wrap = el('div', 'table-responsive');
        var table = el('table', 'table manage-record-table align-middle mb-0 import-review-table');

        var thead = el('thead');
        var htr = el('tr');
        ['Row', 'QR', 'Head of family', 'Members', 'Issues', 'Actions'].forEach(function (h) {
            htr.appendChild(el('th', null, h));
        });
        thead.appendChild(htr);
        table.appendChild(thead);

        var tbody = el('tbody');
        table.appendChild(tbody);
        wrap.appendChild(table);

        var footer = el('div', 'card-footer');

        // Repaint the rows + footer only (never a full render()), so the search box keeps
        // focus and the current page/filter survive.
        var repaint = function () {
            paintFamilyRows(tbody, footer, repaint);
        };

        body.appendChild(buildFamilyFilterBar(families, repaint));
        body.appendChild(wrap);
        card.appendChild(body);
        card.appendChild(footer);

        repaint();

        return card;
    }

    // The families matching the current filter (search over QR/name, severity, issue type).
    function filteredFamilies() {
        var families = review.families || [];
        var query = familyFilter.search.trim().toLowerCase();

        return families.filter(function (family) {
            var blocking = Number(family.blocking || 0);
            var warnings = Number(family.warnings || 0);

            if (familyFilter.severity === 'blocking' && blocking <= 0) {
                return false;
            }

            if (familyFilter.severity === 'warning' && !(warnings > 0 && blocking === 0)) {
                return false;
            }

            if (familyFilter.code !== 'all') {
                var hasCode = (family.types || []).some(function (type) {
                    return type.code === familyFilter.code;
                });

                if (!hasCode) {
                    return false;
                }
            }

            if (query) {
                var hay = (String(family.qr || '') + ' ' + String(family.head || '')).toLowerCase();

                if (hay.indexOf(query) === -1) {
                    return false;
                }
            }

            return true;
        });
    }

    // Slices an array into the current page; page is clamped into 1..pages.
    function paginate(items, page, size) {
        var total = items.length;
        var pages = Math.max(1, Math.ceil(total / size));
        var current = Math.min(Math.max(1, page), pages);
        var start = (current - 1) * size;
        var rows = items.slice(start, start + size);

        return {
            rows: rows,
            page: current,
            pages: pages,
            total: total,
            from: total ? start + 1 : 0,
            to: total ? start + rows.length : 0
        };
    }

    function paintFamilyRows(tbody, footer, repaint) {
        tbody.textContent = '';

        var matches = filteredFamilies();
        var state = paginate(matches, familyPage, familyPageSize);
        familyPage = state.page; // keep the clamped page (the list may have shrunk)

        if (!matches.length) {
            var tr = el('tr');
            var td = el('td', 'text-center text-muted py-3', 'No families match this filter.');
            td.colSpan = 6;
            tr.appendChild(td);
            tbody.appendChild(tr);
        } else {
            state.rows.forEach(function (family) {
                tbody.appendChild(renderFamilyToFixRow(family));
            });
        }

        paintFamilyFooter(footer, state, repaint);
    }

    // "Showing A–B of C" + Previous / Page N of M / Next — Manage Records footer markup.
    function paintFamilyFooter(footer, state, repaint) {
        footer.textContent = '';

        var row = el('div', 'd-flex flex-wrap justify-content-between align-items-center gap-2 w-100');
        row.appendChild(el('div', 'table-footer-left', state.total
            ? 'Showing ' + state.from + '–' + state.to + ' of ' + state.total
            : 'Showing 0 of 0'));

        var right = el('div', 'table-footer-right');

        if (state.pages > 1) {
            var ul = el('ul', 'pagination pagination-sm m-0');

            var prev = el('li', 'page-item' + (state.page <= 1 ? ' disabled' : ''));
            var prevLink = el('a', 'page-link', 'Previous');
            prevLink.href = '#';
            prevLink.addEventListener('click', function (event) {
                event.preventDefault();
                if (state.page > 1) {
                    familyPage = state.page - 1;
                    repaint();
                }
            });
            prev.appendChild(prevLink);
            ul.appendChild(prev);

            var info = el('li', 'page-item disabled');
            info.appendChild(el('span', 'page-link', 'Page ' + state.page + ' of ' + state.pages));
            ul.appendChild(info);

            var next = el('li', 'page-item' + (state.page >= state.pages ? ' disabled' : ''));
            var nextLink = el('a', 'page-link', 'Next');
            nextLink.href = '#';
            nextLink.addEventListener('click', function (event) {
                event.preventDefault();
                if (state.page < state.pages) {
                    familyPage = state.page + 1;
                    repaint();
                }
            });
            next.appendChild(nextLink);
            ul.appendChild(next);

            right.appendChild(ul);
        }

        row.appendChild(right);
        footer.appendChild(row);
    }

    // "Show N entries" control (Manage Records look; client-side, no form).
    function buildSizeSelect(size, onChange) {
        var wrap = el('div', 'd-flex align-items-center gap-2 small text-muted import-review-size');
        wrap.appendChild(el('label', 'mb-0', 'Show'));

        var select = el('select', 'form-select form-select-sm w-auto');
        [10, 25, 50, 100].forEach(function (n) {
            var opt = el('option', null, String(n));
            opt.value = String(n);
            if (n === size) {
                opt.selected = true;
            }
            select.appendChild(opt);
        });
        select.addEventListener('change', function () {
            onChange(parseInt(select.value, 10) || 25);
        });
        wrap.appendChild(select);
        wrap.appendChild(el('span', null, 'entries'));

        return wrap;
    }

    // Toolbar: search + severity + issue-type filters on the left, "Show N entries" on the
    // right (Manage Records layout). Every control resets to page 1 and repaints.
    function buildFamilyFilterBar(families, repaint) {
        var bar = el('div', 'd-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 import-review-filterbar');

        var leftGroup = el('div', 'd-flex flex-wrap align-items-center gap-2');

        var searchGroup = el('div', 'input-group input-group-sm import-review-filter-search');
        var search = el('input', 'form-control');
        search.type = 'search';
        search.placeholder = 'Search QR or name...';
        search.setAttribute('aria-label', 'Search families to fix');
        search.value = familyFilter.search;
        search.addEventListener('input', function () {
            familyFilter.search = search.value;
            familyPage = 1;
            repaint();
        });
        var searchIcon = el('span', 'input-group-text');
        searchIcon.appendChild(el('i', 'bi bi-search'));
        searchGroup.appendChild(search);
        searchGroup.appendChild(searchIcon);
        leftGroup.appendChild(searchGroup);

        var group = el('div', 'btn-group btn-group-sm');
        [['all', 'All'], ['blocking', 'Must fix'], ['warning', 'Warnings']].forEach(function (pair) {
            var btn = el('button', 'btn ' + (familyFilter.severity === pair[0] ? 'btn-primary' : 'btn-outline-primary'), pair[1]);
            btn.type = 'button';
            btn.dataset.sev = pair[0];
            btn.addEventListener('click', function () {
                familyFilter.severity = pair[0];
                Array.prototype.forEach.call(group.children, function (sibling) {
                    sibling.className = 'btn ' + (sibling.dataset.sev === familyFilter.severity ? 'btn-primary' : 'btn-outline-primary');
                });
                familyPage = 1;
                repaint();
            });
            group.appendChild(btn);
        });
        leftGroup.appendChild(group);

        var select = el('select', 'form-select form-select-sm w-auto import-review-filter-type');
        var optAll = el('option', null, 'All issue types');
        optAll.value = 'all';
        select.appendChild(optAll);

        var seen = {};
        families.forEach(function (family) {
            (family.types || []).forEach(function (type) {
                if (type.code && !seen[type.code]) {
                    seen[type.code] = true;
                    var option = el('option', null, type.label || type.code);
                    option.value = type.code;
                    select.appendChild(option);
                }
            });
        });

        // A previously-picked issue may no longer exist after edits — fall back to "all".
        if (familyFilter.code !== 'all' && !seen[familyFilter.code]) {
            familyFilter.code = 'all';
        }

        select.value = familyFilter.code;
        select.addEventListener('change', function () {
            familyFilter.code = select.value;
            familyPage = 1;
            repaint();
        });
        leftGroup.appendChild(select);

        bar.appendChild(leftGroup);

        bar.appendChild(buildSizeSelect(familyPageSize, function (size) {
            familyPageSize = size;
            familyPage = 1;
            repaint();
        }));

        return bar;
    }

    function renderFamilyToFixRow(family) {
        var tr = el('tr');

        tr.appendChild(el('td', 'text-nowrap', family.sheetRow != null ? family.sheetRow : '—'));
        tr.appendChild(el('td', 'text-nowrap fw-semibold', family.qr || '—'));

        // Head of family, plus an "Already in system" emblem when the QR/family is on file.
        var headTd = el('td');
        headTd.appendChild(document.createTextNode(family.head || '—'));
        if (family.existing) {
            headTd.appendChild(document.createTextNode(' '));
            var emblem = el('span', 'badge bg-info text-dark import-review-existing', 'Already in system');
            emblem.title = 'This QR/family is already on file — the import skips it unless you change it.';
            headTd.appendChild(emblem);
        }
        tr.appendChild(headTd);

        tr.appendChild(el('td', 'text-nowrap', family.members));

        // Every distinct issue as its own badge (red = must fix, amber = warning), so the
        // worker sees all of them at a glance instead of just a count.
        var issues = el('td', 'small import-review-issue-cell');
        var types = family.types || [];
        if (types.length) {
            types.forEach(function (type) {
                var cls = type.severity === 'blocking'
                    ? 'badge bg-danger me-1 mb-1'
                    : 'badge bg-warning text-dark me-1 mb-1';
                issues.appendChild(el('span', cls, type.label || type.code));
            });
        } else {
            var blocking = Number(family.blocking || 0);
            var warnings = Number(family.warnings || 0);
            if (blocking > 0) {
                issues.appendChild(el('span', 'badge bg-danger me-1', blocking + ' to fix'));
            }
            if (warnings > 0) {
                issues.appendChild(el('span', 'badge bg-warning text-dark', warnings + ' warning' + (warnings === 1 ? '' : 's')));
            }
            if (blocking === 0 && warnings === 0) {
                issues.appendChild(el('span', 'text-success', 'No issues'));
            }
        }
        tr.appendChild(issues);

        var actions = el('td', 'text-nowrap');

        // Opens the shared family modal (registered under the 'importFix' namespace in
        // manage-family-modal.js) prefilled with this group's staged data.
        var edit = el('button', 'btn btn-sm btn-primary me-1 js-import-fix-edit', 'Edit');
        edit.type = 'button';
        // QR goes in a query param, not the path: a raw QR cell ("-1", "N/A", "5880.0", a
        // slash) is not URL-path-safe and would 404 against a numeric route segment.
        edit.dataset.modalUrl = familyBaseUrl + '?fno=' + encodeURIComponent(family.qr);
        edit.dataset.modalTitle = 'Fix Family ' + (family.qr || '');
        actions.appendChild(edit);

        var remove = el('button', 'btn btn-sm btn-outline-danger js-import-fix-remove', 'Remove');
        remove.type = 'button';
        remove.dataset.familyNo = family.qr;
        actions.appendChild(remove);

        tr.appendChild(actions);

        return tr;
    }

    // -- ready to import --------------------------------------------------------

    function renderReady(counts) {
        var ready = review.ready || [];
        var blocking = Number(counts.blocking || 0);
        var appends = Number(counts.appends || 0);

        var card = el('div', 'card mb-3 import-review-ready');

        var header = el('div', 'card-header d-flex justify-content-between align-items-center flex-wrap gap-2');
        var left = el('span', 'fw-semibold');
        left.appendChild(el('span', 'badge bg-success me-2', ready.length));
        left.appendChild(document.createTextNode('Ready to import'));
        header.appendChild(left);

        var right = el('div', 'd-flex align-items-center gap-2 flex-wrap');
        var hint;
        if (!ready.length && !appends) {
            hint = 'Nothing here is ready to save yet.';
        } else if (blocking > 0) {
            hint = 'These are correct. They will be saved once the issues above are fixed.';
        } else {
            hint = 'These will be saved when you press Confirm import.';
        }
        right.appendChild(el('small', 'text-muted', hint));

        // Everything below the header lives in one container so it can be collapsed to let
        // the operator focus on the problems above.
        var content = el('div', 'import-review-ready-content');

        if (ready.length) {
            var showLabel = 'Show ready to import (' + ready.length + ')';
            var toggle = el('button', 'btn btn-sm btn-outline-success', readyCollapsed ? showLabel : 'Hide ready to import');
            toggle.type = 'button';
            toggle.addEventListener('click', function () {
                readyCollapsed = !readyCollapsed;
                content.hidden = readyCollapsed;
                toggle.textContent = readyCollapsed ? showLabel : 'Hide ready to import';
            });
            right.appendChild(toggle);
        }

        header.appendChild(right);
        card.appendChild(header);

        if (!ready.length) {
            var body = el('div', 'card-body');
            body.appendChild(el('p', 'text-muted small mb-0', appends > 0
                ? 'No NEW families — but ' + appends + ' member(s) will be added to families already in the system (see the list above).'
                : 'No new families are ready. Every group either has an issue to fix, or is already in the system.'));
            content.appendChild(body);
            card.appendChild(content);

            return card;
        }

        var body2 = el('div', 'card-body p-0');
        var wrap = el('div', 'table-responsive');
        var table = el('table', 'table table-sm mb-0 align-middle import-review-table');

        var thead = el('thead');
        var htr = el('tr');
        ['Row', 'QR', 'Head of family', 'Members', 'Barangay', 'Address', 'Notes'].forEach(function (h) {
            htr.appendChild(el('th', null, h));
        });
        thead.appendChild(htr);
        table.appendChild(thead);

        var tbody = el('tbody');
        ready.forEach(function (family) {
            tbody.appendChild(renderReadyRow(family));
        });
        table.appendChild(tbody);
        wrap.appendChild(table);
        body2.appendChild(wrap);
        content.appendChild(body2);

        if (appends > 0) {
            var foot = el('div', 'card-footer small text-muted');
            foot.textContent = 'Plus ' + appends + ' member(s) being added to families already in the system.';
            content.appendChild(foot);
        }

        content.hidden = readyCollapsed;
        card.appendChild(content);

        return card;
    }

    function renderReadyRow(family) {
        var tr = el('tr');

        tr.appendChild(el('td', 'text-nowrap', family.sheetRow != null ? family.sheetRow : '—'));
        tr.appendChild(el('td', 'text-nowrap fw-semibold', family.qr || '—'));
        tr.appendChild(el('td', null, family.head || '—'));
        tr.appendChild(el('td', 'text-nowrap', family.members));
        tr.appendChild(el('td', 'small', family.barangay || '—'));
        tr.appendChild(el('td', 'small import-review-addr', family.address || '—'));

        // Warning-only families still import — say so plainly instead of leaving a blank.
        var notes = el('td', 'small');
        if (Number(family.warnings) > 0) {
            notes.appendChild(el('span', 'badge bg-warning text-dark',
                family.warnings + ' warning' + (Number(family.warnings) === 1 ? '' : 's')));
            notes.appendChild(document.createTextNode(' imports as typed'));
        } else {
            notes.appendChild(el('span', 'text-success', 'No issues'));
        }
        tr.appendChild(notes);

        return tr;
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

    // Applies a fresh review report (returned by a save/remove) and re-renders. Also called
    // by manage-family-modal.js after the Edit modal saves — hence the global handle.
    function applyReview(freshReview, csrfHash) {
        if (freshReview) {
            review = freshReview;
        }

        refreshCsrf(csrfHash);
        render();
    }

    window.importReviewApply = applyReview;

    function removeFamily(familyNo, row) {
        if (!familyBaseUrl) {
            return;
        }

        // A blank-QR row is keyed by its sheet row; a normal family by its QR.
        var isRow = row != null && row !== '';
        var label = isRow ? 'row ' + row : 'family ' + familyNo;
        var extra = isRow ? { import_row: row } : { import_family_no: familyNo };

        if (!isRow && !familyNo) {
            return;
        }

        if (!window.confirm('Remove ' + label + ' from this import? Its rows will be dropped.')) {
            return;
        }

        setStatus('Removing ' + label + '...');

        // Keys travel in the POST body, not the path (raw QR cells are not URL-path-safe).
        postForm(familyBaseUrl + '/remove', extra).then(function (result) {
            var data = result.data || {};

            if (result.ok && data.review) {
                applyReview(data.review, data.csrf);
                setStatus(data.message || 'Removed.');

                return;
            }

            refreshCsrf(data.csrf);
            setStatus(data.message || 'Could not remove. Please try again.');
        }).catch(function () {
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

        var removeBtn = target.closest('.js-import-fix-remove');
        if (removeBtn) {
            removeFamily(removeBtn.dataset.familyNo, removeBtn.dataset.row);
        }
    });

    confirmBtn.addEventListener('click', confirmImport);
    cancelBtn.addEventListener('click', cancelImport);

    render();
})(window, document);
