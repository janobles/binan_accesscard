(() => {
    const content = document.getElementById("dashboard-content");

    if (!content) {
        return;
    }

    const escapeHtml = (value) => String(value)
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");

    const showAccountCreateAlert = (container, message, type) => {
        const alertHost = container.querySelector("[data-account-create-alert]");

        if (!alertHost) {
            return;
        }

        alertHost.innerHTML = `
            <div class="alert alert-${type}" role="alert">
                ${escapeHtml(message)}
            </div>
        `;
    };

    const setCreateButtonLoading = (button, isLoading) => {
        if (!button) {
            return;
        }

        button.disabled = isLoading;
        button.setAttribute("aria-busy", isLoading ? "true" : "false");

        const label = button.querySelector("span");

        if (label) {
            label.textContent = isLoading ? "Creating..." : "Create";
        }
    };

    const createAccount = async (form) => {
        const button = form.querySelector("[data-account-create-button]");

        setCreateButtonLoading(button, true);

        try {
            const response = await fetch(form.action, {
                method: "POST",
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                },
                body: new FormData(form),
            });
            const result = await response.json();

            if (!response.ok || !result.success) {
                showAccountCreateAlert(content, result.message || "Account could not be created.", "danger");
                return;
            }

            content.innerHTML = result.html;
            showAccountCreateAlert(content, result.message || "Account created successfully.", "success");
        } catch {
            showAccountCreateAlert(content, "Account could not be created.", "danger");
        } finally {
            setCreateButtonLoading(button, false);
        }
    };

    content.addEventListener("submit", (event) => {
        const form = event.target.closest("[data-account-create-form]");

        if (!form) {
            return;
        }

        event.preventDefault();
        createAccount(form);
    });
})();
