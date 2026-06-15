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
        defaultTitle: 'Record',
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
        updateAllFilterDropdowns(replacement);
        updateSearchAllState(replacement);

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

    function selectedValues(container) {
        if (!container) {
            return [];
        }

        return Array.from(container.querySelectorAll('input[type="checkbox"]:checked') || [])
            .map(function (input) { return input.value; })
            .filter(function (value) { return value !== '' && value !== '__all'; });
    }

    function updateFilterDropdown(dropdown) {
        if (!dropdown) {
            return;
        }

        const label = dropdown.querySelector('[data-records-filter-label]');
        dropdown.querySelectorAll('.records-check-option').forEach(function (option) {
            const input = option.querySelector('input[type="checkbox"]');

            option.classList.toggle('is-selected', !!(input && input.checked));
        });

        const checked = Array.from(dropdown.querySelectorAll('input[type="checkbox"]:checked'));
        const selected = checked.filter(function (input) { return input.value !== '__all'; });
        const allChecked = checked.some(function (input) { return input.value === '__all'; });
        const isBarangay = dropdown.dataset.recordsFilter === 'barangay';

        if (!label) {
            return;
        }

        if (allChecked) {
            label.textContent = isBarangay ? 'All barangays' : 'All sectors';
        } else if (selected.length === 0) {
            label.textContent = isBarangay ? '-Select barangay-' : '-Select sector-';
        } else if (selected.length === 1) {
            const optionLabel = selected[0].closest('label');
            label.textContent = optionLabel ? optionLabel.textContent.trim() : selected[0].value;
        } else {
            label.textContent = selected.length + ' selected';
        }
    }

    function updateAllFilterDropdowns(root) {
        (root || document).querySelectorAll('[data-records-filter]').forEach(updateFilterDropdown);
    }

    function hasDatabaseSearchCriteria(panel) {
        if (!panel) {
            return false;
        }

        const keywordInput = panel.querySelector('[data-records-database-keyword]');
        const keyword = keywordInput ? keywordInput.value.trim() : '';
        const hasSector = selectedValues(panel.querySelector('[data-records-filter="sector"]')).length > 0;
        const hasBarangay = selectedValues(panel.querySelector('[data-records-filter="barangay"]')).length > 0;

        return keyword !== '' || hasSector || hasBarangay;
    }

    function updateSearchAllState(panel) {
        if (!panel) {
            return;
        }

        const button = panel.querySelector('[data-search-mode="all"]');

        if (!button) {
            return;
        }

        const enabled = hasDatabaseSearchCriteria(panel);
        button.disabled = false;
        button.setAttribute('aria-disabled', 'false');
        button.classList.toggle('has-search-criteria', enabled);
    }

    function hideTableRows(panel) {
        if (!panel) {
            return;
        }

        panel.querySelectorAll('[data-record-row]').forEach(function (row) {
            row.style.display = 'none';
        });
    }

    function filterTableRows(panel, keyword, sectorIds) {
        // Split into tokens so a full name ("Juan Cruz") matches even though the
        // tokens live in different parts of the name; every token must be present.
        var tokens = keyword ? keyword.split(/\s+/).filter(Boolean) : [];
        var selectedIds = (Array.isArray(sectorIds) ? sectorIds : [sectorIds])
            .map(Number)
            .filter(function (id) { return id > 0; });
        panel.querySelectorAll('[data-record-row]').forEach(function (row) {
            // Prefer the full name (incl. middle name) for matching; fall back to the
            // visible name cell when the attribute isn't present.
            var name = (row.dataset.recordFullname
                || (row.querySelector('[data-record-name]') ? row.querySelector('[data-record-name]').textContent : '')
            ).toLowerCase().trim();
            var rowText = row.textContent ? row.textContent.toLowerCase().trim() : '';
            var searchableText = (name + ' ' + rowText).trim();
            var rawIds = row.dataset.sectorIds || '[]';
            var ids = [];
            try { ids = JSON.parse(rawIds); } catch (_) {}
            if (!Array.isArray(ids)) { ids = ids ? [ids] : []; }
            var nameOk = tokens.every(function (token) { return searchableText.indexOf(token) !== -1; });
            var numericIds = ids.map(Number);
            var secOk  = selectedIds.length === 0 || selectedIds.some(function (sectorId) {
                return numericIds.indexOf(sectorId) !== -1;
            });
            row.style.display = (nameOk && secOk) ? '' : 'none';
        });
    }

    function keywordInputFor(panel) {
        return panel ? panel.querySelector('[data-records-table-keyword]') : null;
    }

    function keywordValueFor(panel) {
        const input = keywordInputFor(panel);

        return input ? input.value : '';
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

        if (form.dataset.recordsSearch === 'table') {
            const keyword = keywordValueFor(panel).toLowerCase().trim();
            const sectorSelect = panel.querySelector('[data-records-filter="sector"]');

            filterTableRows(panel, keyword, selectedValues(sectorSelect));
            return;
        }

        // "Search" button — filter current rows in-browser without a server round-trip.
        if (!window.fetch || !window.history) {
            form.submit();
            return;
        }

        closeModalFor(form);

        if (event.submitter && event.submitter.dataset.searchMode === 'all' && !hasDatabaseSearchCriteria(panel)) {
            updateSearchAllState(panel);
            hideTableRows(panel);
            return;
        }

        const actionUrl = (event.submitter && event.submitter.getAttribute('formaction')) || form.action;
        const fullUrl = new URL(actionUrl, window.location.href);
        const formData = new FormData(form);

        fullUrl.search = '';
        formData.forEach(function (value, key) {
            if (key === 'status') {
                return;
            }

            if (String(value).trim() !== '') {
                fullUrl.searchParams.append(key, value);
            }
        });

        // "Search All" runs the whole-database (deep) search, including non-head
        // family members. The submitter's name/value isn't in FormData, so flag the
        // deep scope here; DashboardPageBuilder reads search_scope to build the panel.
        if (event.submitter && event.submitter.dataset.searchMode === 'all') {
            fullUrl.searchParams.set('search_scope', 'all');
        }

        loadFamilyList(panel, fullUrl.toString(), true);
    });

    // Live search: filter rows on every keystroke in the manual keyword field.
    document.addEventListener('input', function (event) {
        const input = event.target;
        if (input && input.matches('[data-records-database-keyword]')) {
            updateSearchAllState(input.closest('[data-family-list-panel]'));
            return;
        }

        if (!input || !input.matches('[data-records-table-keyword]')) {
            return;
        }
        const panel = input.closest('[data-family-list-panel]');
        if (!panel) {
            return;
        }
        const keyword  = input.value.toLowerCase().trim();

        const sel = panel.querySelector('[data-records-filter="sector"]');
        filterTableRows(panel, keyword, selectedValues(sel));
    });

    document.addEventListener('change', function (event) {
        const select = event.target;
        if (!select || !['status', 'per_page', 'sectorID[]', 'barangay[]'].includes(select.name)) {
            return;
        }

        const panel = select.closest('[data-family-list-panel]');
        const form = select.closest('form[method="get"]');

        if (!panel || !form) {
            return;
        }

        if (select.type === 'checkbox') {
            const dropdown = select.closest('[data-records-filter]');
            const allInput = dropdown ? dropdown.querySelector('[data-filter-all]') : null;

            if (select.dataset.filterAll !== undefined && select.checked && dropdown) {
                dropdown.querySelectorAll('input[type="checkbox"]').forEach(function (input) {
                    input.checked = input === select;
                });
            } else if (select.checked && allInput) {
                allInput.checked = false;
            }

            updateFilterDropdown(dropdown);
            updateSearchAllState(panel);

            if (select.name === 'sectorID[]' || select.name === 'barangay[]') {
                return;
            }
        }

        if (select.name === 'status') {
            updateSearchAllState(panel);

            if (!window.fetch || !window.history) {
                form.submit();
                return;
            }

            const statusUrl = new URL(form.action, window.location.href);
            const perPageInput = form.querySelector('input[name="per_page"]');

            statusUrl.search = '';

            if (select.value !== '') {
                statusUrl.searchParams.set('status', select.value);
            }

            if (perPageInput && perPageInput.value !== '') {
                statusUrl.searchParams.set('per_page', perPageInput.value);
            }

            loadFamilyList(panel, statusUrl.toString(), true);
            return;
        }

        if (!window.fetch || !window.history) {
            form.submit();
            return;
        }

        const fullUrl = new URL(form.action, window.location.href);
        const formData = new FormData(form);
        fullUrl.search = '';

        formData.forEach(function (value, key) {
            if (key === 'status') {
                return;
            }

            if (String(value).trim() !== '') {
                fullUrl.searchParams.append(key, value);
            }
        });

        loadFamilyList(panel, fullUrl.toString(), true);
    });

    document.addEventListener('click', function (event) {
        const clearButton = event.target.closest('[data-records-clear]');
        if (!clearButton) {
            return;
        }

        const panel = panelFor(clearButton);
        const form = clearButton.closest('[data-records-search="database"]');
        const quickInput = keywordInputFor(panel);
        const databaseInput = panel ? panel.querySelector('[data-records-database-keyword]') : null;
        const sectorSelect = form && form.querySelector('[data-records-filter="sector"]');
        const barangaySelect = form && form.querySelector('[data-records-filter="barangay"]');

        if (!panel || !form) {
            return;
        }

        if (quickInput) {
            quickInput.value = '';
        }
        if (databaseInput) {
            databaseInput.value = '';
        }
        if (sectorSelect) {
            sectorSelect.querySelectorAll('input[type="checkbox"]').forEach(function (input) {
                input.checked = false;
            });
            updateFilterDropdown(sectorSelect);
        }
        if (barangaySelect) {
            barangaySelect.querySelectorAll('input[type="checkbox"]').forEach(function (input) {
                input.checked = false;
            });
            updateFilterDropdown(barangaySelect);
        }
        updateSearchAllState(panel);

        if (window.fetch && window.history) {
            const clearUrl = new URL(form.action, window.location.href);
            const statusSelect = form.querySelector('select[name="status"]');
            const perPageInput = form.querySelector('input[name="per_page"]');

            clearUrl.search = '';

            if (statusSelect && statusSelect.value !== '') {
                clearUrl.searchParams.set('status', statusSelect.value);
            }

            if (perPageInput && perPageInput.value !== '') {
                clearUrl.searchParams.set('per_page', perPageInput.value);
            }

            loadFamilyList(panel, clearUrl.toString(), true);
            return;
        }

        filterTableRows(panel, '', []);
        if (databaseInput) {
            databaseInput.focus();
        }
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

    updateAllFilterDropdowns(document);
    document.querySelectorAll('[data-family-list-panel]').forEach(updateSearchAllState);
})(window, document);
