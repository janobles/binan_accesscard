<?php
/**
 * Confirmation modal for enabling or disabling an account, included once by
 * Admin/layout.php for the Account Management page (Admin > Accounts).
 *
 * Populated and shown by view-interactions.js, which reads the clicked
 * .js-account-status-form's data-confirm-message and re-submits that form on
 * confirm. The message is per-form rather than fixed here, so one modal serves both
 * the enable and the disable wording.
 */
?>
<div class="modal fade" id="accountStatusModal" tabindex="-1" aria-labelledby="accountStatusModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="accountStatusModalLabel">Update Account Status</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<p class="mb-0 js-account-status-message">Update this account status?</p>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
				<button type="button" class="btn btn-primary js-account-status-confirm">Confirm</button>
			</div>
		</div>
	</div>
</div>
