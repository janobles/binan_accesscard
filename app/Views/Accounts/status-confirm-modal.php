<?php /* Confirmation modal for enabling/disabling an account on the Account
         Management page. Populated and shown by view-interactions.js, which reads
         the clicked .js-account-status-form's data-confirm-message (same wording as
         the old native dialog) and re-submits that form on confirm. */ ?>
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
				<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
				<button type="button" class="btn btn-primary js-account-status-confirm">Confirm</button>
			</div>
		</div>
	</div>
</div>
