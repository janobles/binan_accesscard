// Intercepts submissions of .js-family-record-action-form and shows a confirm
// dialog before the form is actually submitted (archive / restore actions).
// If the user cancels, event.preventDefault() blocks the POST.
//
// Connected to:
//   - View   : Dashboard/familyform/family-list.php — .js-family-record-action-form
//              (data-family-name, data-action-label, data-action-past, data-confirm-message)
//   - Backend: POST {admin|employee}/manage-family/archive|restore/:id
//              (Families\FamilyController::archive, ::restore)
(function () {
    document.addEventListener('submit', function (event) {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || !form.classList.contains('js-family-record-action-form')) {
            return;
        }

        const familyName = (form.dataset.familyName || 'this record').trim();
        const actionLabel = (form.dataset.actionLabel || 'Archive').trim();
        const actionPast = (form.dataset.actionPast || 'archived').trim();
        const fallbackMessage = actionLabel + ' ' + familyName + '? This keeps the record in the database, marks it as ' + actionPast + ', and hides it from active lists.';
        const message = (form.dataset.confirmMessage || '').trim() || fallbackMessage;

        if (!window.confirm(message)) {
            event.preventDefault();
        }
    });
})();
