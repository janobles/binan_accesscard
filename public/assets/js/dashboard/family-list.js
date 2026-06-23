// Intercepts family record archive / restore forms and keeps row action menus
// usable inside scrollable table containers. The confirmation step uses the
// styled #familyActionModal (Family/action-confirm-modal.php) instead of the
// native window.confirm, falling back to window.confirm when Bootstrap or the
// modal markup is unavailable.
//
// Connected to:
//   - Rows   : FamilyController::dataTable() - .js-family-record-action-form
//              (data-family-name, data-action-label, data-action-past, data-confirm-message)
//              Family/action-confirm-modal.php - #familyActionModal
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

    // Form whose submission is paused while the confirmation modal is open.
    var pendingActionForm = null;

    function familyActionModalEl() {
        return document.getElementById('familyActionModal');
    }

    // Pulls the retained per-row wording + action flavour off the form's data-*.
    function actionDetails(form) {
        var familyName = (form.dataset.familyName || 'this record').trim();
        var actionLabel = (form.dataset.actionLabel || 'Archive').trim();
        var actionPast = (form.dataset.actionPast || 'archived').trim();
        var fallbackMessage = actionLabel + ' ' + familyName + '? This keeps the record in the database, marks it as ' + actionPast + ', and hides it from active lists.';
        var message = (form.dataset.confirmMessage || '').trim() || fallbackMessage;
        var isRestore = actionPast.toLowerCase() === 'restored' || actionLabel.toLowerCase() === 'restore';

        return { actionLabel: actionLabel, message: message, isRestore: isRestore };
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;

        if (!(form instanceof HTMLFormElement) || !form.classList.contains('js-family-record-action-form')) {
            return;
        }

        var details = actionDetails(form);
        var modalEl = familyActionModalEl();

        // Graceful fallback when the styled modal isn't available.
        if (!modalEl || !window.bootstrap || !window.bootstrap.Modal) {
            if (!window.confirm(details.message)) {
                event.preventDefault();
            }

            return;
        }

        event.preventDefault();
        pendingActionForm = form;

        var title = modalEl.querySelector('#familyActionModalLabel');
        var messageEl = modalEl.querySelector('.js-family-action-message');
        var confirmBtn = modalEl.querySelector('.js-family-action-confirm');

        if (title) {
            title.textContent = details.actionLabel + ' Record';
        }

        if (messageEl) {
            messageEl.textContent = details.message;
        }

        if (confirmBtn) {
            confirmBtn.textContent = details.actionLabel;
            confirmBtn.classList.toggle('btn-danger', !details.isRestore);
            confirmBtn.classList.toggle('btn-success', details.isRestore);
        }

        window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
    });

    document.addEventListener('click', function (event) {
        if (!event.target.closest('.js-family-action-confirm')) {
            return;
        }

        var form = pendingActionForm;
        var modalEl = familyActionModalEl();

        pendingActionForm = null;

        if (modalEl && window.bootstrap && window.bootstrap.Modal) {
            var instance = window.bootstrap.Modal.getInstance(modalEl);

            if (instance) {
                instance.hide();
            }
        }

        if (form) {
            // Native submit() does not re-fire the delegated submit listener, so
            // the modal does not reopen and no second dialog appears.
            form.submit();
        }
    });

    // Drop the stashed form if the user dismisses the modal (Cancel / backdrop / Esc).
    document.addEventListener('hidden.bs.modal', function (event) {
        if (event.target && event.target.id === 'familyActionModal') {
            pendingActionForm = null;
        }
    });
})();
