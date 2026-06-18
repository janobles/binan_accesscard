// Account management page behaviour:
//   1. Registers the Account Management panel with the shared dashboard modal
//      loader so clicking .js-open-accounts-modal loads it via AJAX.
//      Account status confirmations are handled by view-interactions.js so they
//      work in both full-page and AJAX-loaded account views.
//
//   3. Registers the per-row Edit trigger (.js-open-account-edit-modal) so the
//      prefilled edit-account form loads via AJAX. Reset Password reuses the
//      .js-account-status-form confirm flow from view-interactions.js.
//
// Connected to:
//   - dashboard-modal-loader.js : window.registerDashboardModal()
//   - Views  : Admin/accounts.php — .js-account-status-form buttons,
//              .js-open-account-edit-modal buttons
//   - Backend: POST admin/accounts/disable|enable
//              (Accounts\AccountController::disableEmployee, ::enableEmployee)
//              GET accounts/edit/{id} (Accounts\AccountController::editForm)
//              POST accounts/update, accounts/reset-password
// Account management page behavior.
(function (window, document) {
    if (typeof window.registerDashboardModal === 'function') {
        window.registerDashboardModal({
            namespace: 'accounts',
            triggerSelector: '.js-open-accounts-modal',
            defaultTitle: 'Account Management',
            loadingMarkup: '<div class="family-modal-loading" role="status" aria-live="polite"><div class="spinner-border text-primary" aria-hidden="true"></div><span>Loading account management...</span></div>',
            errorMarkup: '<div class="alert alert-danger mb-0">Unable to load account management. Please try again.</div>'
        });

        window.registerDashboardModal({
            namespace: 'account-edit',
            triggerSelector: '.js-open-account-edit-modal',
            defaultTitle: 'Edit Account',
            loadingMarkup: '<div class="family-modal-loading" role="status" aria-live="polite"><div class="spinner-border text-primary" aria-hidden="true"></div><span>Loading account...</span></div>',
            errorMarkup: '<div class="alert alert-danger mb-0">Unable to load this account. Please try again.</div>'
        });

        window.registerDashboardModal({
            namespace: 'account-create',
            triggerSelector: '.js-open-account-create-modal',
            defaultTitle: 'Create Account',
            loadingMarkup: '<div class="family-modal-loading" role="status" aria-live="polite"><div class="spinner-border text-primary" aria-hidden="true"></div><span>Loading account form...</span></div>',
            errorMarkup: '<div class="alert alert-danger mb-0">Unable to load the account form. Please try again.</div>'
        });
    }

    // Copy-to-clipboard for the reset-password result callout. Delegated so it
    // works whether the callout is on a full page or an AJAX-loaded view.
    document.addEventListener('click', function (event) {
        var button = event.target.closest('.js-copy-password');

        if (!button) {
            return;
        }

        var target = document.querySelector(button.getAttribute('data-copy-target'));

        if (!target) {
            return;
        }

        var value = target.textContent.trim();
        var label = button.querySelector('span');

        var done = function () {
            if (label) {
                var original = label.textContent;
                label.textContent = 'Copied!';
                window.setTimeout(function () {
                    label.textContent = original;
                }, 1500);
            }
        };

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(value).then(done).catch(function () {});
        } else {
            var range = document.createRange();
            range.selectNodeContents(target);
            var selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);

            try {
                document.execCommand('copy');
                done();
            } catch (error) {
                /* clipboard unavailable; the value stays selected for manual copy */
            }

            selection.removeAllRanges();
        }
    });

    function filterAccountRows(root) {
        var panel = root || document;
        var searchInput = panel.querySelector('[data-account-search]');
        var levelFilter = panel.querySelector('[data-account-level-filter]');
        var statusFilter = panel.querySelector('[data-account-status-filter]');

        if (!searchInput || !levelFilter || !statusFilter) {
            return;
        }

        var keyword = searchInput.value.trim().toLowerCase();
        var level = levelFilter.value;
        var status = statusFilter.value;
        var rows = Array.from(panel.querySelectorAll('[data-account-row]'));
        var visibleCount = 0;

        rows.forEach(function (row) {
            var username = row.dataset.accountUsername || '';
            var role = row.dataset.accountRole || '';
            var rowStatus = row.dataset.accountStatus || '';
            var matchesKeyword = keyword === '' || username.indexOf(keyword) !== -1;
            var matchesLevel = level === '' || role === level;
            var matchesStatus = status === '' || rowStatus === status;
            var shouldShow = matchesKeyword && matchesLevel && matchesStatus;

            row.hidden = !shouldShow;

            if (shouldShow) {
                visibleCount += 1;
            }
        });

        var emptyRow = panel.querySelector('[data-account-filter-empty]');

        if (emptyRow) {
            emptyRow.hidden = visibleCount !== 0;
        }
    }

    function initAccountFilters(root) {
        var panel = root || document;

        if (!panel.querySelector('[data-account-management]')) {
            return;
        }

        filterAccountRows(panel);
    }

    document.addEventListener('input', function (event) {
        if (!event.target.matches('[data-account-search]')) {
            return;
        }

        filterAccountRows(event.target.closest('[data-account-management]') || document);
    });

    document.addEventListener('change', function (event) {
        if (!event.target.matches('[data-account-level-filter], [data-account-status-filter]')) {
            return;
        }

        filterAccountRows(event.target.closest('[data-account-management]') || document);
    });

    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-account-clear-filters]');

        if (!button) {
            return;
        }

        var panel = button.closest('[data-account-management]') || document;
        var searchInput = panel.querySelector('[data-account-search]');
        var levelFilter = panel.querySelector('[data-account-level-filter]');
        var statusFilter = panel.querySelector('[data-account-status-filter]');

        if (searchInput) {
            searchInput.value = '';
        }

        if (levelFilter) {
            levelFilter.value = '';
        }

        if (statusFilter) {
            statusFilter.value = '';
        }

        filterAccountRows(panel);
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initAccountFilters(document);
        });
    } else {
        initAccountFilters(document);
    }

})(window, document);
