(function (document) {
    document.addEventListener('change', function (event) {
        const select = event.target;

        if (!(select instanceof HTMLSelectElement) || !select.classList.contains('js-audit-action-filter')) {
            return;
        }

        const form = select.closest('.js-audit-filter-form');

        if (form instanceof HTMLFormElement) {
            form.submit();
        }
    });
})(document);
