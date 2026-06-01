(function (window, document) {
    function bindAuditFilters(root) {
        root.querySelectorAll('.js-audit-action-filter').forEach(function (select) {
            if (select.dataset.auditFilterBound === '1') {
                return;
            }

            select.dataset.auditFilterBound = '1';
            select.addEventListener('change', function () {
                const form = select.closest('.js-audit-filter-form');

                if (form) {
                    form.submit();
                }
            });
        });
    }

    function bindAccountStatusForms(root) {
        root.querySelectorAll('.js-account-status-form').forEach(function (form) {
            if (form.dataset.statusFormBound === '1') {
                return;
            }

            form.dataset.statusFormBound = '1';
            form.addEventListener('submit', function (event) {
                const message = form.dataset.confirmMessage || 'Update this account status?';

                if (!window.confirm(message)) {
                    event.preventDefault();
                }
            });
        });
    }

    function initViewInteractions(rootElement) {
        const root = rootElement instanceof HTMLElement ? rootElement : document;

        bindAuditFilters(root);
        bindAccountStatusForms(root);
    }

    window.initViewInteractions = initViewInteractions;

    document.addEventListener('DOMContentLoaded', function () {
        initViewInteractions(document);
    });
})(window, document);
