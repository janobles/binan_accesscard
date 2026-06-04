(() => {
    const getDefaults = (modal) => {
        const title = modal.querySelector("[data-sector-service-modal-title]");
        const submit = modal.querySelector("[data-sector-service-modal-submit]");

        if (title && !title.dataset.defaultText) {
            title.dataset.defaultText = title.textContent.trim();
        }

        if (submit && !submit.dataset.defaultText) {
            submit.dataset.defaultText = submit.textContent.trim();
        }

        return { title, submit };
    };

    const setModalMode = (modal, openButton) => {
        const { title, submit } = getDefaults(modal);
        const modalTitle = openButton.getAttribute("data-sector-service-modal-title");
        const saveLabel = openButton.getAttribute("data-sector-service-modal-save-label");

        if (title) {
            title.textContent = modalTitle || title.dataset.defaultText || title.textContent;
        }

        if (submit) {
            submit.textContent = saveLabel || submit.dataset.defaultText || submit.textContent;
        }
    };

    const fillModal = (modal, openButton) => {
        modal.querySelectorAll("[data-sector-service-modal-field]").forEach((field) => {
            const fieldId = field.getAttribute("data-sector-service-modal-field-id");
            const value = fieldId
                ? openButton.getAttribute(`data-sector-service-modal-field-${fieldId}`)
                : null;

            field.value = value ?? "";
        });
    };

    const openModal = (modal) => {
        modal.hidden = false;

        const firstField = modal.querySelector("[data-sector-service-modal-field]");
        const windowPanel = modal.querySelector(".sector-service-modal-window");

        if (firstField) {
            firstField.focus();
        } else if (windowPanel) {
            windowPanel.focus();
        }
    };

    const closeModal = (modal) => {
        modal.hidden = true;
    };

    const clearModal = (modal) => {
        modal.querySelectorAll("[data-sector-service-modal-field]").forEach((field) => {
            field.value = "";
        });

        const firstField = modal.querySelector("[data-sector-service-modal-field]");

        if (firstField) {
            firstField.focus();
        }
    };

    document.addEventListener("click", (event) => {
        const openButton = event.target.closest("[data-sector-service-modal-open]");

        if (openButton) {
            const modalId = openButton.getAttribute("data-sector-service-modal-open");
            const modal = modalId
                ? document.getElementById(modalId)
                : document.querySelector("[data-sector-service-modal]");

            if (modal) {
                setModalMode(modal, openButton);
                fillModal(modal, openButton);
                openModal(modal);
            }

            return;
        }

        const modal = event.target.closest("[data-sector-service-modal]");

        if (!modal) {
            return;
        }

        if (event.target === modal || event.target.closest("[data-sector-service-modal-close]")) {
            closeModal(modal);
            return;
        }

        if (event.target.closest("[data-sector-service-modal-clear]")) {
            clearModal(modal);
        }
    });

    document.addEventListener("keydown", (event) => {
        if (event.key !== "Escape") {
            return;
        }

        const modal = document.querySelector("[data-sector-service-modal]:not([hidden])");

        if (modal) {
            closeModal(modal);
        }
    });
})();
