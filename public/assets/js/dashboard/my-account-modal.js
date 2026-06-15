// Self-service "My Account" modal.
//   Registers the topbar username trigger (.js-open-my-account-modal) with the
//   shared dashboard modal loader so it fetches the My Account form via AJAX.
//   The form submits as a normal POST (account/profile/update) and the page
//   reloads with a flash message, matching the lookup-modal pattern.
//
// Connected to:
//   - dashboard-modal-loader.js : window.registerDashboardModal()
//   - Views  : Accounts/my-account-modal.php (loaded fragment)
//             Admin/layout.php + Employee/layout.php (topbar trigger)
//   - Backend: GET account/profile (Accounts\ProfileController::myAccount)
//              POST account/profile/update (Accounts\ProfileController::update)
(function (window, document) {
    if (typeof window.registerDashboardModal === 'function') {
        window.registerDashboardModal({
            namespace: 'my-account',
            triggerSelector: '.js-open-my-account-modal',
            defaultTitle: 'My Account',
            loadingMarkup: '<div class="family-modal-loading" role="status" aria-live="polite"><div class="spinner-border text-primary" aria-hidden="true"></div><span>Loading your account...</span></div>',
            errorMarkup: '<div class="alert alert-danger mb-0">Unable to load your account. Please try again.</div>'
        });
    }
})(window, document);
