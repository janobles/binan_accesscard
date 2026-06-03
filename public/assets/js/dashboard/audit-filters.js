// Auto-submits the audit trail filter form when the action-type dropdown changes,
// giving instant filtering without needing a separate submit button click.
//
// Connected to:
//   - View   : Dashboard/Manage/admin.php audit-trails section
//              .js-audit-action-filter select inside .js-audit-filter-form
//   - Backend: GET admin/audit-trails (Workspace\HomeController::adminAuditTrails)
//   - view-interactions.js also binds the same behaviour for AJAX-loaded content
(function (document) {
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
})(document);
