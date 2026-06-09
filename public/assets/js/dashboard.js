(() => {
    const content = document.getElementById("dashboard-content");
    const pageTitle = document.getElementById("dashboard-page-title");
    const workspaceLinks = Array.from(document.querySelectorAll("[data-workspace-link]"));
    const sidebar = document.getElementById("dashboard-sidebar");
    const sidebarToggle = document.querySelector("[data-sidebar-toggle]");

    if (!content || !pageTitle) {
        return;
    }

    const initialUrl = content.dataset.workspaceUrl;

    if (!initialUrl) {
        return;
    }

    const createUrl = (url) => new URL(url, window.location.origin);

    const setSidebarOpen = (isOpen) => {
        document.body.classList.toggle("sidebar-open", isOpen);

        if (sidebarToggle) {
            sidebarToggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
        }
    };

    if (sidebar && sidebarToggle) {
        sidebarToggle.addEventListener("click", (event) => {
            event.stopPropagation();
            setSidebarOpen(!document.body.classList.contains("sidebar-open"));
        });
    }

    const partialUrl = (workspaceUrl) => {
        const url = createUrl(workspaceUrl);
        url.searchParams.set("partial", "1");

        return url;
    };

    const findWorkspaceLink = (workspaceUrl) => {
        const path = createUrl(workspaceUrl).pathname;

        return workspaceLinks.find((link) => createUrl(link.href).pathname === path);
    };

    const isNewFamilyRecordWindowUrl = (workspaceUrl) => (
        /\/(?:admin|employee)\/family-record\/new\/?$/.test(createUrl(workspaceUrl).pathname)
    );

    const isEditFamilyRecordWindowUrl = (workspaceUrl) => (
        /\/(?:admin|employee)\/family-record\/\d+\/edit\/?$/.test(createUrl(workspaceUrl).pathname)
    );

    const isFamilyRecordWindowUrl = (workspaceUrl) => (
        isNewFamilyRecordWindowUrl(workspaceUrl) || isEditFamilyRecordWindowUrl(workspaceUrl)
    );

    let familyRecordWindowHtml = null;
    let familyRecordWindowRequest = null;

    const fetchFamilyRecordWindow = (workspaceUrl) => {
        if (!isNewFamilyRecordWindowUrl(workspaceUrl)) {
            return fetch(partialUrl(workspaceUrl), {
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                },
            }).then(async (response) => {
                if (response.redirected) {
                    window.location.assign(response.url);
                    throw new Error("Family record request redirected.");
                }

                if (!response.ok) {
                    throw new Error("Family record request failed.");
                }

                return response.text();
            });
        }

        if (familyRecordWindowHtml !== null) {
            return Promise.resolve(familyRecordWindowHtml);
        }

        if (familyRecordWindowRequest) {
            return familyRecordWindowRequest;
        }

        familyRecordWindowRequest = fetch(partialUrl(workspaceUrl), {
            headers: {
                "X-Requested-With": "XMLHttpRequest",
            },
        }).then(async (response) => {
            if (response.redirected) {
                window.location.assign(response.url);
                throw new Error("Family record request redirected.");
            }

            if (!response.ok) {
                throw new Error("Family record request failed.");
            }

            familyRecordWindowHtml = await response.text();

            return familyRecordWindowHtml;
        }).finally(() => {
            familyRecordWindowRequest = null;
        });

        return familyRecordWindowRequest;
    };

    const prefetchFamilyRecordWindow = () => {
        const familyRecordLink = Array.from(content.querySelectorAll("a[href]"))
            .find((link) => isNewFamilyRecordWindowUrl(link.href));

        if (familyRecordLink) {
            fetchFamilyRecordWindow(familyRecordLink.href).catch(() => {});
        }
    };

    const removeFamilyRecordWindow = () => {
        document.querySelectorAll(".family-window-host").forEach((host) => {
            host.remove();
        });
    };

    const closeFamilyRecordWindow = (element) => {
        const host = element.closest(".family-window-host");

        if (host) {
            host.remove();
            return;
        }

        element.remove();
    };

    const openFamilyRecordWindow = async (workspaceUrl, trigger) => {
        removeFamilyRecordWindow();

        if (trigger) {
            trigger.setAttribute("aria-busy", "true");
        }

        try {
            const host = document.createElement("div");

            host.className = "family-window-host";
            host.innerHTML = await fetchFamilyRecordWindow(workspaceUrl);
            document.body.appendChild(host);

            const closeButton = host.querySelector("[data-family-window-close]");
            const windowPanel = host.querySelector("[data-family-window]");

            if (closeButton) {
                closeButton.focus();
            } else if (windowPanel) {
                windowPanel.focus();
            }
        } catch {
            content.insertAdjacentHTML("afterbegin", `
                <div class="alert alert-danger" role="alert">
                    New Record form could not be loaded.
                </div>
            `);
        } finally {
            if (trigger) {
                trigger.removeAttribute("aria-busy");
            }
        }
    };

    const setLoading = (title) => {
        content.setAttribute("aria-busy", "true");
        content.innerHTML = `
            <div class="d-flex align-items-center gap-2 text-secondary" role="status">
                <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                <span>Loading ${title}...</span>
            </div>
        `;
    };

    const setActivePage = (workspaceUrl, title) => {
        document.querySelectorAll(".dashboard-sidebar .nav-link.active").forEach((link) => {
            link.classList.remove("active");
            link.removeAttribute("aria-current");
        });

        const activeLink = findWorkspaceLink(workspaceUrl);

        if (activeLink) {
            activeLink.classList.add("active");
            activeLink.setAttribute("aria-current", "page");
        }

        pageTitle.textContent = title;
        document.title = `${title} | Binan Access Card Portal`;
    };

    const loadWorkspace = async (workspaceUrl, { updateHistory = true, title = "Page" } = {}) => {
        removeFamilyRecordWindow();
        setLoading(title);

        try {
            const response = await fetch(partialUrl(workspaceUrl), {
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                },
            });

            if (response.redirected) {
                window.location.assign(response.url);
                return;
            }

            if (!response.ok) {
                throw new Error("Workspace request failed.");
            }

            content.innerHTML = await response.text();
            content.removeAttribute("aria-busy");
            setActivePage(workspaceUrl, title);
            prefetchFamilyRecordWindow();

            if (updateHistory) {
                window.history.pushState({ workspaceUrl, title }, "", workspaceUrl);
            }
        } catch {
            content.removeAttribute("aria-busy");
            content.innerHTML = `
                <div class="alert alert-danger" role="alert">
                    ${title} content could not be loaded.
                </div>
            `;
        }
    };

    const loadPartialLink = (link) => {
        loadWorkspace(link.href, { title: pageTitle.textContent || "Page" });
    };

    workspaceLinks.forEach((link) => {
        link.addEventListener("click", (event) => {
            event.preventDefault();
            setSidebarOpen(false);
            loadWorkspace(link.href, { title: link.dataset.pageTitle || "Page" });
        });
    });

    content.addEventListener("click", (event) => {
        const link = event.target.closest("[data-workspace-partial-link]");

        if (!link) {
            return;
        }

        event.preventDefault();

        if (isFamilyRecordWindowUrl(link.href)) {
            openFamilyRecordWindow(link.href, link);
            return;
        }

        loadPartialLink(link);
    });

    document.addEventListener("click", (event) => {
        if (
            document.body.classList.contains("sidebar-open")
            && sidebar
            && sidebarToggle
            && !sidebar.contains(event.target)
            && !sidebarToggle.contains(event.target)
        ) {
            setSidebarOpen(false);
        }

        const closeButton = event.target.closest("[data-family-window-close]");

        if (closeButton) {
            const windowElement = closeButton.closest("[data-family-window-backdrop]");

            if (windowElement) {
                closeFamilyRecordWindow(windowElement);
            }

            return;
        }

        const backdrop = event.target.closest("[data-family-window-backdrop]");

        if (backdrop && event.target === backdrop) {
            closeFamilyRecordWindow(backdrop);
        }
    });

    document.addEventListener("keydown", (event) => {
        if (event.key !== "Escape") {
            return;
        }

        setSidebarOpen(false);

        const windowElement = document.querySelector("[data-family-window-backdrop]");

        if (windowElement) {
            closeFamilyRecordWindow(windowElement);
        }
    });

    content.addEventListener("workspace:load", (event) => {
        loadWorkspace(event.detail.url, {
            title: event.detail.title || pageTitle.textContent || "Page",
        });
    });

    window.addEventListener("popstate", () => {
        const activeLink = findWorkspaceLink(window.location.href);

        if (activeLink) {
            loadWorkspace(window.location.href, {
                updateHistory: false,
                title: activeLink.dataset.pageTitle || "Page",
            });
        }
    });

    const activeLink = findWorkspaceLink(initialUrl);

    if (activeLink) {
        loadWorkspace(initialUrl, {
            updateHistory: false,
            title: activeLink.dataset.pageTitle || "Page",
        });
    }
})();
