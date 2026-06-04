(() => {
    const isFamilyRecordWindowUrl = (url) => {
        try {
            const pathname = new URL(url, window.location.origin).pathname;

            return /\/admin\/family-record\/new\/?$/.test(pathname)
                || /\/admin\/family-record\/\d+\/edit\/?$/.test(pathname);
        } catch {
            return false;
        }
    };

    const partialUrl = (url) => {
        const requestUrl = new URL(url, window.location.origin);

        requestUrl.searchParams.set("partial", "1");

        return requestUrl;
    };

    const closeFamilyWindow = (windowElement) => {
        const host = windowElement.closest(".family-window-host");

        if (host) {
            host.remove();
            return;
        }

        windowElement.remove();
    };

    const removeFamilyWindowHosts = () => {
        document.querySelectorAll(".family-window-host").forEach((host) => {
            host.remove();
        });
    };

    const openFamilyWindow = async (url, trigger) => {
        removeFamilyWindowHosts();

        if (trigger) {
            trigger.setAttribute("aria-busy", "true");
        }

        try {
            const response = await fetch(partialUrl(url), {
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                },
            });

            if (response.redirected) {
                window.location.assign(response.url);
                return;
            }

            if (!response.ok) {
                throw new Error("Family record request failed.");
            }

            const host = document.createElement("div");

            host.className = "family-window-host";
            host.innerHTML = await response.text();
            document.body.appendChild(host);

            const closeButton = host.querySelector("[data-family-window-close]");
            const windowPanel = host.querySelector("[data-family-window]");

            if (closeButton) {
                closeButton.focus();
            } else if (windowPanel) {
                windowPanel.focus();
            }
        } catch {
            window.location.assign(url);
        } finally {
            if (trigger) {
                trigger.removeAttribute("aria-busy");
            }
        }
    };

    const activateFamilyStep = (wizard, step) => {
        const stepButtons = wizard.querySelectorAll("[data-step]");
        const panels = wizard.querySelectorAll("[data-step-panel]");

        stepButtons.forEach((button) => {
            const isActive = button.dataset.step === step;

            button.classList.toggle("is-active", isActive);

            if (isActive) {
                button.setAttribute("aria-current", "step");
            } else {
                button.removeAttribute("aria-current");
            }
        });

        panels.forEach((panel) => {
            const isActive = panel.dataset.stepPanel === step;

            panel.hidden = !isActive;
            panel.classList.toggle("family-form-panel-hidden", !isActive);
        });
    };

    const openSearchPicker = (input) => {
        if (!input) {
            return;
        }

        input.focus();

        if (typeof input.select === "function") {
            input.select();
        }

        if (typeof input.showPicker !== "function") {
            return;
        }

        try {
            input.showPicker();
        } catch {
            // Some browsers only allow showPicker during direct user actions.
        }
    };

    document.addEventListener("pointerdown", (event) => {
        const searchPicker = event.target.closest("[data-family-search-picker]");

        if (searchPicker) {
            openSearchPicker(searchPicker);
        }
    });

    document.addEventListener("click", (event) => {
        const newRecordLink = event.target.closest("a[href]");

        if (!event.defaultPrevented && newRecordLink && isFamilyRecordWindowUrl(newRecordLink.href)) {
            event.preventDefault();
            openFamilyWindow(newRecordLink.href, newRecordLink);
            return;
        }

        const closeButton = event.target.closest("[data-family-window-close]");

        if (closeButton) {
            const windowElement = closeButton.closest("[data-family-window-backdrop]");

            if (windowElement) {
                closeFamilyWindow(windowElement);
            }

            return;
        }

        const backdrop = event.target.closest("[data-family-window-backdrop]");

        if (backdrop && event.target === backdrop) {
            closeFamilyWindow(backdrop);
            return;
        }

        const searchPicker = event.target.closest("[data-family-search-picker]");

        if (searchPicker) {
            openSearchPicker(searchPicker);
            return;
        }

        const button = event.target.closest(".family-wizard-step[data-step]");

        if (button) {
            const wizard = button.closest(".family-record-wizard");

            if (!wizard) {
                return;
            }

            activateFamilyStep(wizard, button.dataset.step);
            return;
        }

        const removeMemberButton = event.target.closest("[data-remove-member]");

        if (removeMemberButton) {
            const memberCard = removeMemberButton.closest(".family-member-card");

            if (memberCard) {
                memberCard.remove();
            }

            return;
        }

        const addMemberButton = event.target.closest("[data-add-member]");

        if (!addMemberButton) {
            return;
        }

        const wizard = addMemberButton.closest(".family-record-wizard");

        if (!wizard) {
            return;
        }

        const template = wizard.querySelector("#family-member-template");
        const membersList = wizard.querySelector("[data-members-list]");

        if (!template || !membersList) {
            return;
        }

        const index = membersList.children.length;
        const wrapper = document.createElement("div");

        wrapper.appendChild(template.content.cloneNode(true));
        wrapper.innerHTML = wrapper.innerHTML.replaceAll("__INDEX__", String(index));

        membersList.appendChild(wrapper.firstElementChild);
    });

    document.addEventListener("focusin", (event) => {
        const searchPicker = event.target.closest("[data-family-search-picker]");

        if (searchPicker) {
            openSearchPicker(searchPicker);
        }
    });

    document.addEventListener("keydown", (event) => {
        if (event.key !== "Escape") {
            return;
        }

        const windowElement = document.querySelector("[data-family-window-backdrop]");

        if (windowElement) {
            closeFamilyWindow(windowElement);
        }
    });
})();
