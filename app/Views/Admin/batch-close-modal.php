<?php
/**
 * Confirmation modal for closing the active distribution batch. Populated and
 * shown by batch-close-modal.js, which intercepts the .js-batch-close-form
 * submit and re-submits it on confirm. Static single-purpose modal (one batch
 * can be open at a time), unlike the family/account action modals it mirrors.
 */
?>
<div class="modal fade" id="batchCloseModal" tabindex="-1" aria-labelledby="batchCloseModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="batchCloseModalLabel">Close Batch</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<p class="mb-0">Close this batch? Statistics reset for the next batch.</p>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
				<button type="button" class="btn btn-warning js-batch-close-confirm">Close batch</button>
			</div>
		</div>
	</div>
</div>
