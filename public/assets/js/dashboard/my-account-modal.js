// Self-service "My Account" modal.
//   Registers the topbar username trigger (.js-open-my-account-modal) with the
//   shared dashboard modal loader so it fetches the My Account form via AJAX.
//   The form submits as a normal POST (account/profile/update) and the page
//   reloads with a flash message, matching the lookup-modal pattern.
//
//   The Cancel / Save Account buttons live in the shared modal footer (so they
//   stay visible without scrolling): onLoaded swaps the footer's default "Close"
//   button for them, targeting the #myAccountForm via the HTML form attribute,
//   and the footer is restored when the modal is hidden.
//
// Connected to:
//   - dashboard-modal-loader.js : window.registerDashboardModal()
//   - Views  : Accounts/my-account-modal.php (loaded fragment)
//             Admin/layout.php + Employee/layout.php (topbar trigger + footer)
//   - Backend: GET account/profile (Accounts\ProfileController::myAccount)
//              POST account/profile/update (Accounts\ProfileController::update)
(function (window, document) {
    var $ = window.jQuery;

    function getFooter() {
        return document.querySelector('#familyModal .modal-footer');
    }

    function setMyAccountFooter() {
        var footer = getFooter();

        if (!footer) {
            return;
        }

        // Stash the original footer once so it can be restored for other modals.
        if (typeof footer.dataset.defaultHtml === 'undefined') {
            footer.dataset.defaultHtml = footer.innerHTML;
        }

        footer.innerHTML =
            '<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>' +
            '<button type="submit" form="myAccountForm" class="btn btn-success">Save Account</button>';
    }

    function restoreFooter() {
        var footer = getFooter();

        if (footer && typeof footer.dataset.defaultHtml !== 'undefined') {
            footer.innerHTML = footer.dataset.defaultHtml;
        }
    }

    if (typeof window.registerDashboardModal === 'function') {
        window.registerDashboardModal({
            namespace: 'my-account',
            triggerSelector: '.js-open-my-account-modal',
            defaultTitle: 'My Account',
            loadingMarkup: '<div class="family-modal-loading" role="status" aria-live="polite"><div class="spinner-border text-primary" aria-hidden="true"></div><span>Loading your account...</span></div>',
            errorMarkup: '<div class="alert alert-danger mb-0">Unable to load your account. Please try again.</div>',
            onLoaded: function (bodyEl) {
                if (bodyEl && bodyEl.querySelector('#myAccountForm')) {
                    setMyAccountFooter();
                } else {
                    restoreFooter();
                }
            }
        });
    }

    // Always return the footer to its default state once the modal closes, so the
    // injected My Account buttons never leak into other dashboard modals.
    if ($) {
        $(document).on('hidden.bs.modal', '#familyModal', restoreFooter);
    }
})(window, document);
