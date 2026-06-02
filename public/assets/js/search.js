(() => {
    const content = document.getElementById("dashboard-content");

    if (!content) {
        return;
    }

    content.addEventListener("submit", (event) => {
        const form = event.target.closest("[data-workspace-search-form]");

        if (!form) {
            return;
        }

        event.preventDefault();

        const url = new URL(form.action, window.location.origin);
        const formData = new FormData(form);
        const submitter = event.submitter;

        if (submitter?.name) {
            formData.set(submitter.name, submitter.value);
        }

        url.search = new URLSearchParams(formData).toString();
        content.dispatchEvent(new CustomEvent("workspace:load", {
            detail: {
                url,
                title: form.dataset.pageTitle || "Manage Records",
            },
        }));
    });
})();
