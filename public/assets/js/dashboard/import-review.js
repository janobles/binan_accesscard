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

    // Search + paging state for the other two lists (same feel as Families to fix).
    var needsQrFilter = { search: '' };
    var needsQrPage = 1;
    var needsQrPageSize = 25;
    var readyFilter = { search: '' };
    var readyPage = 1;
    var readyPageSize = 25;

    // Bulk-remove selection for "Families to fix" (QR string -> true). Reset on every full
    // render() because the list itself changes after an edit/remove.
    var selectedFno = {};
    var bulkRemoveBtn = null;  // "Remove selected" button (lives in the filter bar)
    var selectAllBox = null;   // header select-all checkbox

    function parseJson() {
        var node = document.getElementById('importReviewData');

        try {
            return JSON.parse(node ? node.textContent : '{}');
        } catch (e) {
            return { file: '', counts: {} };
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

    // Promise-based confirm reusing the page's #familyActionModal markup (Bootstrap modal),
    // so Remove / Cancel / Confirm match the app's dialog instead of a native window.confirm.
    // Resolves true on confirm, false on dismiss. Falls back to window.confirm if the modal
    // or Bootstrap is unavailable.
    function confirmAction(opts) {
        opts = opts || {};

        var modalEl = document.getElementById('familyActionModal');
        var bs = window.bootstrap;

        if (!modalEl || !bs || !bs.Modal) {
            return Promise.resolve(window.confirm(opts.message || 'Are you sure?'));
        }

        var titleEl = modalEl.querySelector('#familyActionModalLabel');
        var msgEl = modalEl.querySelector('.js-family-action-message');
        var confirmBtn = modalEl.querySelector('.js-family-action-confirm');

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
        if (confirmBtn) {
            confirmBtn.textContent = opts.confirmLabel || 'Confirm';
            confirmBtn.className = 'btn ' + (opts.confirmClass || 'btn-danger') + ' js-family-action-confirm';
        }

        var modal = bs.Modal.getOrCreateInstance(modalEl);

        return new Promise(function (resolve) {
            var settled = false;

            function cleanup() {
                confirmBtn.removeEventListener('click', onConfirm);
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

            confirmBtn.addEventListener('click', onConfirm);
            modalEl.addEventListener('hidden.bs.modal', onHidden);
            modal.show();
        });
    }

    // A search input group (Manage Records look). onInput gets the current value.
    function buildSearchBox(placeholder, value, onInput) {
        var group = el('div', 'input-group input-group-sm import-review-filter-search');
        var input = el('input', 'form-control');
        input.type = 'search';
        input.placeholder = placeholder;
        input.setAttribute('aria-label', placeholder);
        input.value = value || '';
        input.addEventListener('input', function () {
            onInput(input.value);
        });
        var icon = el('span', 'input-group-text');
        icon.appendChild(el('i', 'bi bi-search'));
        group.appendChild(input);
        group.appendChild(icon);

        return group;
    }

    // "Showing A–B of C" + Previous / Page N of M / Next. onGoto(n) navigates to page n.
    function paintPager(footer, state, onGoto) {
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
                    onGoto(state.page - 1);
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
                    onGoto(state.page + 1);
                }
            });
            next.appendChild(nextLink);
            ul.appendChild(next);

            right.appendChild(ul);
        }

        row.appendChild(right);
        footer.appendChild(row);
    }

    // -- rendering -------------------------------------------------------------

    function render() {
        var counts = review.counts || {};

        if (fileEl) {
            fileEl.textContent = review.file || 'import.xlsx';
        }

        // The list is rebuilt below, so any prior bulk selection no longer maps cleanly.
        selectedFno = {};
        bulkRemoveBtn = null;
        selectAllBox = null;

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

    // KPI tile matching the dashboard's house stat card (theme.css .stat-card*):
    // flat (no shadow), green uppercase label, big value, soft icon. The status
    // color stays on the value so the operator still spots red "Issues to fix".
    function statTile(label, value, colorClass, icon) {
        var col = el('div', 'col-6 col-xl');
        var card = el('article', 'stat-card card h-100 py-2');
        var body = el('div', 'card-body');
        var content = el('div', 'stat-card-content');

        var text = el('div');
        text.appendChild(el('p', null, label));
        text.appendChild(el('strong', colorClass || null, String(value)));
        content.appendChild(text);

        var i = el('i', 'bi bi-' + (icon || 'graph-up') + ' stat-card-icon');
        i.setAttribute('aria-hidden', 'true');
        content.appendChild(i);

        body.appendChild(content);
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

        statsEl.appendChild(statTile('People in file', people, '', 'people'));
        statsEl.appendChild(statTile('Family groups', groups, '', 'diagram-3'));
        statsEl.appendChild(statTile('Ready to import', ready, ready > 0 ? 'text-success' : 'text-muted', 'check2-circle'));
        statsEl.appendChild(statTile('Already in system', existing, existing > 0 ? 'text-warning' : 'text-muted', 'archive'));
        statsEl.appendChild(statTile('Issues to fix', blocking, blocking > 0 ? 'text-danger' : 'text-success', 'exclamation-triangle'));
        statsEl.appendChild(statTile('Warnings', warnings, warnings > 0 ? 'text-warning' : 'text-muted', 'exclamation-circle'));
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

        // Truly nothing to act on and nothing to save — show one clear empty state instead of
        // an empty "Needs a QR" / "Ready" scaffold.
        var hasProblems = notices.length || (review.unassigned || []).length || (review.families || []).length;
        var ready = Number(counts.ready || 0);
        var appends = Number(counts.appends || 0);

        if (!hasProblems && ready <= 0 && appends <= 0) {
            groupsEl.appendChild(renderEmptyState());

            return;
        }

        // Rows with no QR number can't be grouped into a family — surface them first so the
        // operator can give them a QR and fix them in place.
        var needsQr = renderNeedsQr();
        if (needsQr) {
            groupsEl.appendChild(needsQr);
        }

        // The flagged families, each with Edit (fix in place), Remove, and — once edited —
        // a per-family "View changes" button (history lives in a modal, not a global list).
        var families = renderFamiliesToFix();
        if (families) {
            groupsEl.appendChild(families);
        }

        // What is CORRECT and will be saved — behind its own Show button so the screen leads
        // with the problems.
        groupsEl.appendChild(renderReady(counts));
    }

    // Empty state — the file held nothing to fix and nothing new to save.
    function renderEmptyState() {
        var card = el('div', 'card mb-3');
        var body = el('div', 'card-body text-center py-5');
        var icon = el('i', 'bi bi-inbox text-muted');
        icon.setAttribute('aria-hidden', 'true');
        icon.style.fontSize = '2.5rem';
        body.appendChild(icon);
        body.appendChild(el('p', 'h5 mt-3 mb-1', 'Nothing to import'));
        body.appendChild(el('p', 'text-muted mb-0',
            'This file has no new families to save and no issues to fix. Upload a different file to import records.'));
        card.appendChild(body);

        return card;
    }

    // Search box (left) + "Show N entries" (right) toolbar for the simple lists.
    function buildSimpleToolbar(placeholder, filterObj, onSearch, size, onSize) {
        var bar = el('div', 'd-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 import-review-filterbar');
        bar.appendChild(buildSearchBox(placeholder, filterObj.search, function (value) {
            filterObj.search = value;
            onSearch();
        }));
        bar.appendChild(buildSizeSelect(size, onSize));

        return bar;
    }

    // -- families to fix (in-place edit / remove) -------------------------------

    // -- rows with no QR (edit to assign one) -----------------------------------

    // The blank-QR rows matching the current search (over person name / sheet row).
    function filteredNeedsQr() {
        var rows = review.unassigned || [];
        var query = needsQrFilter.search.trim().toLowerCase();

        if (!query) {
            return rows;
        }

        return rows.filter(function (row) {
            var hay = (String(row.person || '') + ' ' + String(row.sheetRow || '')).toLowerCase();

            return hay.indexOf(query) !== -1;
        });
    }

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
        header.appendChild(el('small', 'text-muted d-block mt-1',
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
        table.appendChild(tbody);
        wrap.appendChild(table);

        var footer = el('div', 'card-footer');

        var repaint = function () {
            tbody.textContent = '';
            var matches = filteredNeedsQr();
            var state = paginate(matches, needsQrPage, needsQrPageSize);
            needsQrPage = state.page;

            if (!matches.length) {
                var tr = el('tr');
                var td = el('td', 'text-center text-muted py-3', 'No rows match this search.');
                td.colSpan = 4;
                tr.appendChild(td);
                tbody.appendChild(tr);
            } else {
                state.rows.forEach(function (row) {
                    tbody.appendChild(renderNeedsQrRow(row));
                });
            }

            paintPager(footer, state, function (page) {
                needsQrPage = page;
                repaint();
            });
        };

        // Only surface the search/size toolbar when the list is long enough to need it.
        if (rows.length > needsQrPageSize) {
            body.appendChild(buildSimpleToolbar('Search person or row...', needsQrFilter,
                function () { needsQrPage = 1; repaint(); },
                needsQrPageSize,
                function (size) { needsQrPageSize = size; needsQrPage = 1; repaint(); }));
        }

        body.appendChild(wrap);
        card.appendChild(body);
        card.appendChild(footer);
        repaint();

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

        // Select-all for bulk remove: toggles every family in the CURRENT filter (all pages),
        // so the operator can e.g. filter by "Already in the system" then remove them all.
        var selectTh = el('th', 'import-review-select-col');
        selectAllBox = el('input', 'form-check-input');
        selectAllBox.type = 'checkbox';
        selectAllBox.setAttribute('aria-label', 'Select all families to fix');
        selectAllBox.addEventListener('change', function () {
            filteredFamilies().forEach(function (family) {
                if (selectAllBox.checked) {
                    selectedFno[String(family.qr)] = true;
                } else {
                    delete selectedFno[String(family.qr)];
                }
            });
            updateBulkButton();
            repaint();
        });
        selectTh.appendChild(selectAllBox);
        htr.appendChild(selectTh);

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
            td.colSpan = 7;
            tr.appendChild(td);
            tbody.appendChild(tr);
        } else {
            state.rows.forEach(function (family) {
                tbody.appendChild(renderFamilyToFixRow(family));
            });
        }

        refreshSelectAll();
        paintFamilyFooter(footer, state, repaint);
    }

    // Reflects how many of the currently-filtered families are selected on the header box.
    function refreshSelectAll() {
        if (!selectAllBox) {
            return;
        }

        var matches = filteredFamilies();
        var selected = 0;
        matches.forEach(function (family) {
            if (selectedFno[String(family.qr)]) {
                selected++;
            }
        });

        selectAllBox.checked = matches.length > 0 && selected === matches.length;
        selectAllBox.indeterminate = selected > 0 && selected < matches.length;
    }

    // Enables/labels the "Remove selected" button from the current selection size.
    function updateBulkButton() {
        if (!bulkRemoveBtn) {
            return;
        }

        var n = Object.keys(selectedFno).length;
        bulkRemoveBtn.disabled = n === 0;
        bulkRemoveBtn.textContent = '';
        var ic = el('i', 'bi bi-trash me-1');
        ic.setAttribute('aria-hidden', 'true');
        bulkRemoveBtn.appendChild(ic);
        bulkRemoveBtn.appendChild(document.createTextNode(n > 0 ? 'Remove selected (' + n + ')' : 'Remove selected'));
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

        // Bulk remove — disabled until at least one family is ticked.
        bulkRemoveBtn = el('button', 'btn btn-sm btn-outline-danger');
        bulkRemoveBtn.type = 'button';
        bulkRemoveBtn.addEventListener('click', bulkRemove);
        leftGroup.appendChild(bulkRemoveBtn);
        updateBulkButton();

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

        // Bulk-remove selector (delegated change handler on groupsEl).
        var selectTd = el('td', 'import-review-select-col');
        var box = el('input', 'form-check-input js-import-select');
        box.type = 'checkbox';
        box.dataset.fno = family.qr;
        box.checked = !!selectedFno[String(family.qr)];
        box.setAttribute('aria-label', 'Select family ' + (family.qr || ''));
        selectTd.appendChild(box);
        tr.appendChild(selectTd);

        tr.appendChild(el('td', 'text-nowrap', family.sheetRow != null ? family.sheetRow : '—'));
        tr.appendChild(el('td', 'text-nowrap fw-semibold', family.qr || '—'));

        // Head of family. "Already in system" is not shown here — the Issues column
        // already carries that badge, so repeating it beside the name is noise.
        tr.appendChild(el('td', null, family.head || '—'));

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

        // Per-family history (only after this family was edited/removed in review).
        var history = changesButton(family.qr);
        if (history) {
            actions.appendChild(history);
        }

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

    // -- changes made (per-family history, shown in a modal) --------------------

    // The change entries recorded for one QR group (oldest-first, as stored).
    function changesForQr(qr) {
        var all = review.changes || [];
        var key = String(qr == null ? '' : qr);

        return all.filter(function (change) {
            return String(change.qr || '') === key;
        });
    }

    // A "View changes (N)" button for a family row, or null when it has no history yet.
    function changesButton(qr) {
        var entries = changesForQr(qr);

        if (!entries.length) {
            return null;
        }

        var btn = el('button', 'btn btn-sm btn-outline-secondary me-1 js-import-view-changes');
        btn.type = 'button';
        var icon = el('i', 'bi bi-clock-history me-1');
        icon.setAttribute('aria-hidden', 'true');
        btn.appendChild(icon);
        btn.appendChild(document.createTextNode('View changes (' + entries.length + ')'));
        btn.addEventListener('click', function () {
            openChangesModal(qr, entries);
        });

        return btn;
    }

    // A lightweight, dependency-free modal listing one family's edit history (newest first).
    function openChangesModal(qr, entries) {
        var backdrop = el('div', 'import-changes-modal');
        var dialog = el('div', 'import-changes-dialog');

        var header = el('div', 'import-changes-header');
        header.appendChild(el('h5', 'mb-0', 'Changes to family ' + (qr || '—')));
        var close = el('button', 'btn-close');
        close.type = 'button';
        close.setAttribute('aria-label', 'Close');
        header.appendChild(close);
        dialog.appendChild(header);

        var body = el('div', 'import-changes-body');

        if (!entries.length) {
            body.appendChild(el('p', 'text-muted mb-0 px-3 py-2', 'No changes recorded for this family.'));
        } else {
            var list = el('ul', 'list-group list-group-flush');
            entries.slice().reverse().forEach(function (change) {
                var li = el('li', 'list-group-item');
                var top = el('div', 'd-flex justify-content-between align-items-baseline gap-2');
                top.appendChild(el('span', 'fw-semibold', (change.action || 'Changed') + (change.head ? ' · ' + change.head : '')));
                top.appendChild(el('span', 'text-muted small text-nowrap', change.at || ''));
                li.appendChild(top);

                var lines = change.lines || [];
                if (lines.length) {
                    var ul = el('ul', 'small text-muted mb-0 ps-3 mt-1');
                    lines.forEach(function (line) {
                        ul.appendChild(el('li', null, line));
                    });
                    li.appendChild(ul);
                }

                list.appendChild(li);
            });
            body.appendChild(list);
        }

        dialog.appendChild(body);
        backdrop.appendChild(dialog);
        document.body.appendChild(backdrop);

        function closeModal() {
            document.removeEventListener('keydown', onKey);
            if (backdrop.parentNode) {
                backdrop.parentNode.removeChild(backdrop);
            }
        }

        function onKey(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        }

        close.addEventListener('click', closeModal);
        backdrop.addEventListener('click', function (event) {
            if (event.target === backdrop) {
                closeModal();
            }
        });
        document.addEventListener('keydown', onKey);
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

        var body2 = el('div', 'card-body');

        if (ready.length > readyPageSize) {
            body2.appendChild(buildSimpleToolbar('Search QR, name or barangay...', readyFilter,
                function () { readyPage = 1; repaintReady(); },
                readyPageSize,
                function (size) { readyPageSize = size; readyPage = 1; repaintReady(); }));
        }

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
        table.appendChild(tbody);
        wrap.appendChild(table);
        body2.appendChild(wrap);
        content.appendChild(body2);

        if (appends > 0) {
            content.appendChild(el('div', 'px-3 pb-2 small text-muted',
                'Plus ' + appends + ' member(s) being added to families already in the system.'));
        }

        var readyFooter = el('div', 'card-footer');
        content.appendChild(readyFooter);

        var repaintReady = function () {
            tbody.textContent = '';
            var matches = filteredReady();
            var state = paginate(matches, readyPage, readyPageSize);
            readyPage = state.page;

            if (!matches.length) {
                var tr = el('tr');
                var td = el('td', 'text-center text-muted py-3', 'No families match this search.');
                td.colSpan = 7;
                tr.appendChild(td);
                tbody.appendChild(tr);
            } else {
                state.rows.forEach(function (family) {
                    tbody.appendChild(renderReadyRow(family));
                });
            }

            paintPager(readyFooter, state, function (page) {
                readyPage = page;
                repaintReady();
            });
        };

        repaintReady();

        content.hidden = readyCollapsed;
        card.appendChild(content);

        return card;
    }

    // The ready-to-import families matching the current search (over QR / head / barangay).
    function filteredReady() {
        var rows = review.ready || [];
        var query = readyFilter.search.trim().toLowerCase();

        if (!query) {
            return rows;
        }

        return rows.filter(function (family) {
            var hay = (String(family.qr || '') + ' ' + String(family.head || '') + ' ' + String(family.barangay || '')).toLowerCase();

            return hay.indexOf(query) !== -1;
        });
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

        // A family fixed into "ready" keeps its edit history reachable here.
        var readyHistory = changesButton(family.qr);
        if (readyHistory) {
            readyHistory.classList.remove('me-1');
            readyHistory.classList.add('ms-2');
            notes.appendChild(readyHistory);
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

    // Recap what the write job will do, then commit only on confirm — the write is not
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
            list.appendChild(el('li', null, warnings + ' warning(s) — imported as typed'));
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

        confirmAction({
            title: 'Remove from import',
            message: 'Remove ' + label + ' from this import? Its rows will be dropped.',
            confirmLabel: 'Remove',
            confirmClass: 'btn-danger'
        }).then(function (ok) {
            if (!ok) {
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
        });
    }

    // Removes every ticked family (QR groups) in one POST. Selection lives in selectedFno.
    function bulkRemove() {
        var qrs = Object.keys(selectedFno);

        if (!qrs.length || !familyBaseUrl) {
            return;
        }

        confirmAction({
            title: 'Remove selected',
            message: 'Remove ' + qrs.length + ' selected famil' + (qrs.length === 1 ? 'y' : 'ies')
                + ' from this import? Their rows will be dropped.',
            confirmLabel: 'Remove ' + qrs.length,
            confirmClass: 'btn-danger'
        }).then(function (ok) {
            if (!ok) {
                return;
            }

            setStatus('Removing ' + qrs.length + ' famil' + (qrs.length === 1 ? 'y' : 'ies') + '...');

            var extra = {};
            qrs.forEach(function (qr, index) {
                extra['import_family_nos[' + index + ']'] = qr;
            });

            postForm(familyBaseUrl + '/remove', extra).then(function (result) {
                var data = result.data || {};

                if (result.ok && data.review) {
                    applyReview(data.review, data.csrf);  // render() clears the selection
                    setStatus(data.message || 'Removed.');

                    return;
                }

                refreshCsrf(data.csrf);
                setStatus(data.message || 'Could not remove. Please try again.');
            }).catch(function () {
                setStatus('A network error occurred. Please try again.');
            });
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

    // Bulk-remove checkbox ticks (delegated — rows are re-rendered on every repaint).
    groupsEl.addEventListener('change', function (event) {
        var target = event.target;
        var box = target && target.closest ? target.closest('.js-import-select') : null;

        if (!box) {
            return;
        }

        var qr = String(box.dataset.fno);
        if (box.checked) {
            selectedFno[qr] = true;
        } else {
            delete selectedFno[qr];
        }

        updateBulkButton();
        refreshSelectAll();
    });

    confirmBtn.addEventListener('click', confirmImport);
    cancelBtn.addEventListener('click', cancelImport);

    render();
})(window, document);
