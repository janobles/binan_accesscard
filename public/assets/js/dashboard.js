(() => {
    const content = document.getElementById("dashboard-content");
    const pageTitle = document.getElementById("dashboard-page-title");
    const workspaceLinks = Array.from(document.querySelectorAll("[data-workspace-link]"));

    if (!content || !pageTitle || workspaceLinks.length === 0) {
        return;
    }

    const initialUrl = content.dataset.workspaceUrl;

    if (!initialUrl) {
        return;
    }

    const createUrl = (url) => new URL(url, window.location.origin);

    const partialUrl = (workspaceUrl) => {
        const url = createUrl(workspaceUrl);
        url.searchParams.set("partial", "1");

        return url;
    };

    const findWorkspaceLink = (workspaceUrl) => {
        const path = createUrl(workspaceUrl).pathname;

        return workspaceLinks.find((link) => createUrl(link.href).pathname === path);
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
            loadWorkspace(link.href, { title: link.dataset.pageTitle || "Page" });
        });
    });

    content.addEventListener("click", (event) => {
        const link = event.target.closest("[data-workspace-partial-link]");

        if (!link) {
            return;
        }

        event.preventDefault();
        loadPartialLink(link);
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
