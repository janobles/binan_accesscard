// Intercepts family record archive / restore forms and keeps row action menus
// usable inside scrollable table containers.
//
// Connected to:
//   - View   : Family/list.php - .js-family-record-action-form
//              (data-family-name, data-action-label, data-action-past, data-confirm-message)
//   - Backend: POST {admin|employee}/manage-family/archive|restore/:id
//              (Families\FamilyController::archive, ::restore)
(function () {
    function initFixedActionDropdowns(root) {
        if (!window.bootstrap || !window.bootstrap.Dropdown) return;
        (root || document)
            .querySelectorAll('.actions-menu [data-bs-toggle="dropdown"][data-bs-strategy="fixed"]')
            .forEach(function (toggle) {
                window.bootstrap.Dropdown.getOrCreateInstance(toggle, {
                    popperConfig: function (defaultConfig) {
                        return Object.assign({}, defaultConfig, { strategy: 'fixed' });
                    }
                });
            });
    }

    window.initFamilyListActionDropdowns = initFixedActionDropdowns;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { initFixedActionDropdowns(); });
    } else {
        initFixedActionDropdowns();
    }

    document.addEventListener('show.bs.dropdown', function (event) {
        var btn = event.target;
        if (!btn) return;
        var dropdown = btn.closest('.dropdown');
        if (!dropdown) return;
        var rect = btn.getBoundingClientRect();
        dropdown.classList.toggle('dropup', window.innerHeight - rect.bottom < 180);
    });

    document.addEventListener('hidden.bs.dropdown', function (event) {
        var btn = event.target;
        if (!btn) return;
        var dropdown = btn.closest('.dropdown');
        if (dropdown) dropdown.classList.remove('dropup');
    });

    document.addEventListener('submit', function (event) {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || !form.classList.contains('js-family-record-action-form')) {
            return;
        }

        const familyName = (form.dataset.familyName || 'this record').trim();
        const actionLabel = (form.dataset.actionLabel || 'Archive').trim();
        const actionPast = (form.dataset.actionPast || 'archived').trim();
        const fallbackMessage = actionLabel + ' ' + familyName + '? This keeps the record in the database, marks it as ' + actionPast + ', and hides it from active lists.';
        const message = (form.dataset.confirmMessage || '').trim() || fallbackMessage;

        if (!window.confirm(message)) {
            event.preventDefault();
        }
    });
})();
