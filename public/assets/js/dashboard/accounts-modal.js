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

})(window, document);
