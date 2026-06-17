<?php /* Confirmation modal for family record archive/restore. Lives outside the
         AJAX-replaced records panel so it survives panel re-renders. Populated and
         shown by family-list.js, which reads the clicked row form's data-* (title,
         message, action flavour) and re-submits that form on confirm. */ ?>
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
				<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
				<button type="button" class="btn btn-danger js-family-action-confirm">Archive</button>
			</div>
		</div>
	</div>
</div>
