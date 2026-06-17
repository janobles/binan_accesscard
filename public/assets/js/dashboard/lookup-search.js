// Client-side "local" quick filter for the Sector / Services / Categories lookup
// lists — the controls-row "Search:" box. It filters only the rows the server already
// rendered on this page by hiding non-matching rows (inline display:none); it does NOT
// hit the database. The top [method=get] database-search form and the status dropdown
// are server-driven (whole-table search + pagination).
//
// This file also wires the status dropdown ([data-lookup-status-select]): changing
// it reloads the page with ?status=active|archived|all (preserving the database `q`,
// resetting to page 1). That replaced the old client-side Active/Archived row toggle.
//
// Connected to: Lookups/sectors.php + services.php + categories.php
//   - [data-lookup-search]         the local-filter <form>
//   - [data-lookup-search-input]   the local-filter <input>
//   - [data-lookup-status-select]  the Active/Archive/All status <select>
//   - [data-lookup-search-all]     optional button that clears the keyword filter
//   - the nearest [data-sector-management-root] / [data-service-management-root] / [data-category-management-root]
// Exposes window.initLookupSearch(root) so AJAX-loaded fragments can re-bind.
(function (window, document) {
    function normalize(value) {
        return String(value || '').trim().toLowerCase();
    }

    function managementRoot(el) {
        return el.closest('[data-sector-management-root], [data-service-management-root], [data-category-management-root], [data-audit-management-root]') || document;
    }

    function dataRows(form) {
        const root = managementRoot(form);
        const table = root.querySelector('table');

        if (!table || !table.tBodies.length) {
            return [];
        }

        // Skip the "no records" empty-state row (it spans all columns).
        return Array.prototype.filter.call(table.tBodies[0].rows, function (row) {
            return !row.querySelector('.sector-empty-state, .service-empty-state, .audit-empty-state');
        });
    }

    // Status dropdown ([data-lookup-status-select]) is server-driven: reload with the
    // chosen status, preserving the database `q`, and reset to page 1.
    function navigateStatus(value) {
        const status = value === 'archived' || value === 'all' ? value : 'active';
        const params = new URLSearchParams(window.location.search);

        if (status === 'active') {
            params.delete('status');
        } else {
            params.set('status', status);
        }

        params.delete('page');

        const query = params.toString();
        window.location.href = window.location.pathname + (query ? '?' + query : '');
    }

    // Mirrors Manage Records' table filter (manage-family-modal.js filterTableRows):
    // split the keyword into tokens (every token must be present) and match against
    // row.textContent — which works regardless of the row's current visibility, so
    // deleting characters correctly re-shows rows. Show/hide via inline display.
    function applyFilter(form) {
        const input = form.querySelector('[data-lookup-search-input]');
        const tokens = normalize(input && input.value).split(/\s+/).filter(Boolean);

        dataRows(form).forEach(function (row) {
            const text = String(row.textContent || '').toLowerCase();
            const matches = tokens.every(function (token) {
                return text.indexOf(token) !== -1;
            });
            row.style.display = matches ? '' : 'none';
        });
    }

    function initLookupSearch(rootElement) {
        const root = rootElement instanceof HTMLElement ? rootElement : document;

        root.querySelectorAll('[data-lookup-search]').forEach(function (form) {
            if (form.dataset.lookupSearchBound === '1') {
                return;
            }

            form.dataset.lookupSearchBound = '1';

            const input = form.querySelector('[data-lookup-search-input]');

            form.addEventListener('submit', function (event) {
                event.preventDefault();
                applyFilter(form);
            });

            if (input) {
                input.addEventListener('input', function () {
                    applyFilter(form);
                });
            }

            form.querySelectorAll('[data-lookup-search-all]').forEach(function (button) {
                button.addEventListener('click', function () {
                    if (input) {
                        input.value = '';
                    }

                    applyFilter(form);
                });
            });
        });

        // Status dropdown lives in Bar 1; reload the page on change (server-driven).
        root.querySelectorAll('[data-lookup-status-select]').forEach(function (select) {
            if (select.dataset.lookupStatusBound === '1') {
                return;
            }

            select.dataset.lookupStatusBound = '1';
            select.addEventListener('change', function () {
                navigateStatus(select.value);
            });
        });
    }

    window.initLookupSearch = initLookupSearch;

    document.addEventListener('DOMContentLoaded', function () {
        initLookupSearch(document);
    });
})(window, document);
