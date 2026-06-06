// Two responsibilities:
//   1. Registers Add / View / Edit family record modals with dashboard-modal-loader.js.
//      Clicks on .js-open-family-modal, .js-open-family-view-modal, and
//      .js-open-family-edit-modal fetch the correct partial via AJAX into #familyModal.
//      After loading, calls window.initFamilyForm() to wire up the multi-step wizard.
//   2. Makes the records list panel (data-family-list-panel) update in-place via fetch:
//      search/filter form submits, pagination link clicks, and browser back/forward all
//      replace only the panel HTML without a full page reload. Also handles the
//      archive/restore confirmation dialog for .js-family-record-action-form.
//
// Connected to:
//   - dashboard-modal-loader.js : window.registerDashboardModal()
//   - family-form.js            : window.initFamilyForm() (initialises the wizard)
//   - Backend : GET  {admin|employee}/manage-family/view/:id  (FamilyController::viewFamily)
//               GET  {admin|employee}/manage-family/edit/:id  (FamilyController::editFamily)
//               GET  {admin|employee}/manage-records?partial=1 (list fragment)
//               POST {admin|employee}/manage-family/archive|restore/:id
//   - Views : Family/list.php, form.php, view.php
//   - Both admin (admin/manage-records) and employee (employee/manage-records) pages use this
// Registers record management screens with the shared dashboard modal loader.
(function (window) {
    if (typeof window.registerDashboardModal !== 'function') {
        return;
    }

    window.registerDashboardModal({
        namespace: 'family',
        triggerSelector: '.js-open-family-modal, .js-open-family-view-modal, .js-open-family-edit-modal',
        defaultTitle: 'Manage Record',
        loadingMarkup: '<div class="family-modal-loading" role="status" aria-live="polite"><div class="spinner-border text-primary" aria-hidden="true"></div><span>Loading record form...</span></div>',
        errorMarkup: '<div class="alert alert-danger mb-0">Unable to load the record form. Please try again.</div>',
        onLoaded: function (container) {
            if (typeof window.initFamilyForm === 'function') {
                window.initFamilyForm(container);
            }
        }
    });
})(window);

(function (window, document) {
    function panelFor(target) {
        return target ? target.closest('[data-family-list-panel]') : null;
    }

    function urlWithPartial(url) {
        const nextUrl = new URL(url, window.location.href);

        nextUrl.searchParams.set('partial', '1');

        return nextUrl;
    }

    function setPanelLoading(panel, loading) {
        panel.classList.toggle('is-loading', loading);
        panel.querySelectorAll('button, input, select, a').forEach(function (control) {
            if (control.tagName === 'A') {
                control.classList.toggle('disabled', loading);
                control.setAttribute('aria-disabled', loading ? 'true' : 'false');
                return;
            }

            control.disabled = loading;
        });
    }

    function replacePanel(panel, html) {
        const template = document.createElement('template');
        template.innerHTML = html.trim();
        const replacement = template.content.querySelector('[data-family-list-panel]');

        if (!replacement) {
            window.location.reload();
            return null;
        }

        panel.replaceWith(replacement);

        // Re-apply Popper's fixed positioning to the freshly injected row action
        // menus, otherwise they revert to absolute positioning and get clipped by
        // the .table-responsive overflow when the new panel has few rows.
        if (typeof window.initFamilyListActionDropdowns === 'function') {
            window.initFamilyListActionDropdowns(replacement);
        }

        document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) {
            backdrop.remove();
        });
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');

        return replacement;
    }

    function scrollPanelIntoView(panel) {
        if (!panel) {
            return;
        }

        const top = Math.max(0, window.scrollY + panel.getBoundingClientRect().top - 16);

        window.scrollTo({
            top: top,
            behavior: 'auto'
        });
    }

    function filterTableRows(panel, keyword, sectorId) {
        panel.querySelectorAll('[data-record-row]').forEach(function (row) {
            var name = (row.querySelector('[data-record-name]') ? row.querySelector('[data-record-name]').textContent : '').toLowerCase().trim();
            var rawIds = row.dataset.sectorIds || '[]';
            var ids = [];
            try { ids = JSON.parse(rawIds); } catch (_) {}
            if (!Array.isArray(ids)) { ids = ids ? [ids] : []; }
            var nameOk = !keyword || name.indexOf(keyword) !== -1;
            var secOk  = !sectorId || ids.map(Number).indexOf(sectorId) !== -1;
            row.style.display = (nameOk && secOk) ? '' : 'none';
        });
    }

    function loadFamilyList(panel, fullUrl, pushHistory) {
        const partialUrl = urlWithPartial(fullUrl);

        setPanelLoading(panel, true);

        return window.fetch(partialUrl.toString(), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Unable to load records.');
                }

                return response.text();
            })
            .then(function (html) {
                const nextPanel = replacePanel(panel, html);

                if (pushHistory && nextPanel) {
                    window.history.pushState({ familyList: true }, '', fullUrl);
                }

                scrollPanelIntoView(nextPanel);

                return nextPanel;
            })
            .catch(function () {
                window.location.href = fullUrl;
            });
    }

    function closeModalFor(element) {
        const modal = element.closest('.modal');

        if (modal && window.bootstrap) {
            const instance = window.bootstrap.Modal.getInstance(modal);

            if (instance) {
                instance.hide();
            }
        }
    }

    document.addEventListener('submit', function (event) {
        const form = event.target.closest('[data-family-list-panel] form[method="get"]');

        if (!form) {
            return;
        }

        const panel = panelFor(form);

        if (!panel) {
            return;
        }

        event.preventDefault();

        // "Search" button — filter current rows in-browser without a server round-trip.
        if (event.submitter && event.submitter.dataset.searchMode === 'local') {
            const keyword  = (form.querySelector('input[name="q"]') ? form.querySelector('input[name="q"]').value : '').toLowerCase().trim();
            const sectorId = parseInt((form.querySelector('select[name="sectorID"]') ? form.querySelector('select[name="sectorID"]').value : '0') || '0', 10);
            filterTableRows(panel, keyword, sectorId);
            return;
        }

        if (!window.fetch || !window.history) {
            form.submit();
            return;
        }

        closeModalFor(form);

        const actionUrl = (event.submitter && event.submitter.getAttribute('formaction')) || form.action;
        const fullUrl = new URL(actionUrl, window.location.href);
        const formData = new FormData(form);

        fullUrl.search = '';
        formData.forEach(function (value, key) {
            if (String(value).trim() !== '') {
                fullUrl.searchParams.append(key, value);
            }
        });

        loadFamilyList(panel, fullUrl.toString(), true);
    });

    // Live search: filter rows on every keystroke in the search text field.
    document.addEventListener('input', function (event) {
        const input = event.target;
        if (!input || input.name !== 'q') {
            return;
        }
        const panel = input.closest('[data-family-list-panel]');
        if (!panel) {
            return;
        }
        const keyword  = input.value.toLowerCase().trim();

        // Clearing the search box exits "Search All" (whole-database) mode and
        // reloads the normal records list. The deep-results panel is server state,
        // so client-side row filtering alone can't dismiss it — we reuse the
        // Exit link's URL (present only while deep results are shown).
        const exitLink = panel.querySelector('.js-exit-deep-search');
        if (keyword === '' && exitLink && window.fetch && window.history) {
            const exitUrl = exitLink.getAttribute('href') || '';
            if (exitUrl !== '') {
                loadFamilyList(panel, new URL(exitUrl, window.location.href).toString(), true)
                    .then(function (nextPanel) {
                        const nextInput = nextPanel && nextPanel.querySelector('input[name="q"]');
                        if (nextInput) {
                            nextInput.focus();
                        }
                    });
                return;
            }
        }

        const sel      = panel.querySelector('select[name="sectorID"]');
        const sectorId = sel ? parseInt(sel.value || '0', 10) : 0;
        filterTableRows(panel, keyword, sectorId);
    });

    document.addEventListener('click', function (event) {
        const link = event.target.closest('[data-family-list-panel] a[href]');

        if (!link || link.classList.contains('disabled')) {
            return;
        }

        const panel = panelFor(link);

        if (!panel || !window.fetch || !window.history) {
            return;
        }

        const href = link.getAttribute('href') || '';

        if (href === '' || href.startsWith('#')) {
            return;
        }

        event.preventDefault();
        loadFamilyList(panel, new URL(href, window.location.href).toString(), true);
    });

    window.addEventListener('popstate', function () {
        const panel = document.querySelector('[data-family-list-panel]');

        if (!panel || !window.fetch) {
            return;
        }

        loadFamilyList(panel, window.location.href, false);
    });

    document.addEventListener('submit', function (event) {
        const form = event.target.closest('.js-family-record-action-form');

        if (!form) {
            return;
        }

        const familyName = (form.dataset.familyName || 'this family record').trim();
        const actionLabel = (form.dataset.actionLabel || 'Archive').trim();
        const actionPast = (form.dataset.actionPast || 'archived').trim();
        const fallback = actionLabel + ' ' + familyName + '? This keeps the record in the database, marks it as ' + actionPast + ', and hides it from active lists.';
        const message = (form.dataset.confirmMessage || '').trim() || fallback;

        if (!window.confirm(message)) {
            event.preventDefault();
        }
    });
})(window, document);
