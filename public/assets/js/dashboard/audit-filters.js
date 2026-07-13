// Client-side "search loaded rows" filter for the audit trail table. The action
// filter moved into the Filters dropdown panel (records-filter-panel.js), so this
// file no longer wires an action select.
//
// Connected to:
//   - View   : Admin/layout.php audit-trails section, [data-audit-manual-search]
//   - Backend: none (filters only the rows already rendered)
(function (document) {
    function filterLoadedAuditRows(input) {
        const panel = input.closest('.audit-trails');

        if (!panel) {
            return;
        }

        const query = input.value.trim().toLowerCase();
        const rows = panel.querySelectorAll('[data-audit-row]');
        const emptyRow = panel.querySelector('[data-audit-manual-empty]');
        let visibleRows = 0;

        rows.forEach(function (row) {
            const matches = query === '' || row.textContent.toLowerCase().includes(query);

            row.classList.toggle('d-none', !matches);

            if (matches) {
                visibleRows += 1;
            }
        });

        if (emptyRow) {
            emptyRow.classList.toggle('d-none', query === '' || visibleRows > 0);
        }
    }

    document.addEventListener('input', function (event) {
        const input = event.target;

        if (!(input instanceof HTMLInputElement) || !input.matches('[data-audit-manual-search]')) {
            return;
        }

        filterLoadedAuditRows(input);
    });
})(document);
