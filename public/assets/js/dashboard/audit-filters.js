// Auto-submits the audit trail filter form when the action-type dropdown changes,
// giving instant filtering without needing a separate submit button click.
//
// Connected to:
//   - View   : Admin/layout.php audit-trails section
//              .js-audit-action-filter select inside .js-audit-filter-form
//   - Backend: GET admin/audit-trails (Admin\DashboardController::auditTrails)
//   - view-interactions.js also binds the same behaviour for AJAX-loaded content
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

    document.addEventListener('change', function (event) {
        const select = event.target;

        if (!(select instanceof HTMLSelectElement) || !select.classList.contains('js-audit-action-filter')) {
            return;
        }

        const form = select.closest('.js-audit-filter-form');

        if (form instanceof HTMLFormElement) {
            form.submit();
        }
    });

    document.addEventListener('input', function (event) {
        const input = event.target;

        if (!(input instanceof HTMLInputElement) || !input.matches('[data-audit-manual-search]')) {
            return;
        }

        filterLoadedAuditRows(input);
    });
})(document);
