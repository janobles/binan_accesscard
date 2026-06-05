// Client-side live filter for the Sector Management and Services & Programs lists.
//
// These lookup tables render all rows server-side and toggle Active/Archived
// client-side (sectors-modal.js / services-modal.js add `.d-none` to rows). This
// search adds a separate `.lookup-hidden` class based on the keyword, so a row is
// visible only when it is in the current Active/Archived view (not `.d-none`) AND
// matches the keyword (not `.lookup-hidden`). Switching the Active/Archived toggle
// keeps the keyword applied, so archived matches appear when you open the Archive
// view — no separate "search all" needed.
//
// Connected to: Dashboard/sectors-services/sector.php + services.php
//   - [data-lookup-search]        the search <form>
//   - [data-lookup-search-input]  the search <input>
//   - the nearest [data-sector-management-root] / [data-service-management-root]
// Exposes window.initLookupSearch(root) so AJAX-loaded fragments can re-bind.
(function (window, document) {
    function normalize(value) {
        return String(value || '').trim().toLowerCase();
    }

    function dataRows(form) {
        const root = form.closest('[data-sector-management-root], [data-service-management-root]') || document;
        const table = root.querySelector('table');

        if (!table || !table.tBodies.length) {
            return [];
        }

        // Skip the "no records" empty-state row (it spans all columns).
        return Array.prototype.filter.call(table.tBodies[0].rows, function (row) {
            return !row.querySelector('.sector-empty-state, .service-empty-state');
        });
    }

    function applyFilter(form) {
        const input = form.querySelector('[data-lookup-search-input]');
        const keyword = normalize(input && input.value);

        dataRows(form).forEach(function (row) {
            const matches = keyword === '' || normalize(row.innerText).indexOf(keyword) !== -1;
            row.classList.toggle('lookup-hidden', !matches);
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
        });
    }

    window.initLookupSearch = initLookupSearch;

    document.addEventListener('DOMContentLoaded', function () {
        initLookupSearch(document);
    });
})(window, document);
