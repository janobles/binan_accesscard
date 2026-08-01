<?php
/**
 * Confirmation modal for archiving or restoring a family record, included by the
 * Manage Records page and the import review page.
 *
 * Deliberately sits outside the records panel, because that panel is replaced
 * wholesale on every AJAX search or page change and a modal inside it would be
 * destroyed mid-interaction. Populated and shown by family-list.js, which reads the
 * clicked row form's data attributes for the title, message and action wording, then
 * re-submits that form on confirm.
 */
?>
<div class="modal fade" id="familyActionModal" tabindex="-1" aria-labelledby="familyActionModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="familyActionModalLabel">Archive Record</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<p class="mb-0 js-family-action-message">Are you sure?</p>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
				<button type="button" class="btn btn-danger js-family-action-confirm">Archive</button>
			</div>
		</div>
	</div>
</div>
