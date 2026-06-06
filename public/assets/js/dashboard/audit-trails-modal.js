// Registers the audit-trail detail panel with the shared dashboard modal loader.
// Clicking any .js-open-audit-modal element fetches the audit content via AJAX
// and displays it inside #familyModal without a full page reload.
//
// Connected to:
//   - dashboard-modal-loader.js : window.registerDashboardModal()
//   - Views : Admin/layout.php — audit-trails trigger buttons
//   - Backend: GET admin/audit-trails partial (Workspace\HomeController::adminAuditTrails)
// Registers the audit trail workspace with the shared dashboard modal loader.
(function (window) {
    if (typeof window.registerDashboardModal !== 'function') {
        return;
    }

    window.registerDashboardModal({
        namespace: 'audit',
        triggerSelector: '.js-open-audit-modal',
        defaultTitle: 'Audit Trails',
        loadingMarkup: '<div class="family-modal-loading" role="status" aria-live="polite"><div class="spinner-border text-primary" aria-hidden="true"></div><span>Loading audit trails...</span></div>',
        errorMarkup: '<div class="alert alert-danger mb-0">Unable to load audit trails. Please try again.</div>'
    });
})(window);
