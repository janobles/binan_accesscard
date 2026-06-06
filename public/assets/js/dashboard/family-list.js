// Intercepts submissions of .js-family-record-action-form and shows a confirm
// dialog before the form is actually submitted (archive / restore actions).
// If the user cancels, event.preventDefault() blocks the POST.
//
// Also handles client-side "Search" filtering for [data-dashboard-search-panel]
// (admin + employee dashboard overview panels).
//
// Connected to:
//   - View   : Family/list.php — .js-family-record-action-form
//              (data-family-name, data-action-label, data-action-past, data-confirm-message)
//   - Backend: POST {admin|employee}/manage-family/archive|restore/:id
//              (Families\FamilyController::archive, ::restore)
(function () {
    // Render the row action menus with Popper's "fixed" positioning strategy so
    // the menu escapes the `.table-responsive { overflow-x: auto }` scroll box.
    // Without this the menu is trapped inside that overflow container and gets
    // clipped when the table is short (e.g. a single search result), forcing the
    // user to scroll just to reach View / Edit / Archive. Bootstrap's dropdown
    // config has no `strategy` option, so the `data-bs-strategy="fixed"` markup
    // attribute is ignored on its own — we wire it through popperConfig here.
    function initFixedActionDropdowns(root) {
        if (!window.bootstrap || !window.bootstrap.Dropdown) return;
        (root || document)
            .querySelectorAll('.actions-menu [data-bs-toggle="dropdown"][data-bs-strategy="fixed"]')
            .forEach(function (toggle) {
                window.bootstrap.Dropdown.getOrCreateInstance(toggle, {
                    popperConfig: function (defaultConfig) {
                        return Object.assign({}, defaultConfig, { strategy: 'fixed' });
                    }
                });
            });
    }

    // Exposed so manage-family-modal.js can re-apply the fixed strategy after it
    // replaces the records panel in-place (Search All, pagination, AJAX filter).
    window.initFamilyListActionDropdowns = initFixedActionDropdowns;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { initFixedActionDropdowns(); });
    } else {
        initFixedActionDropdowns();
    }

    // Auto-flip action dropdowns upward when there isn't enough room below.
    document.addEventListener('show.bs.dropdown', function (event) {
        var btn = event.target;
        if (!btn) return;
        var dropdown = btn.closest('.dropdown');
        if (!dropdown) return;
        var rect = btn.getBoundingClientRect();
        dropdown.classList.toggle('dropup', window.innerHeight - rect.bottom < 180);
    });

    document.addEventListener('hidden.bs.dropdown', function (event) {
        var btn = event.target;
        if (!btn) return;
        var dropdown = btn.closest('.dropdown');
        if (dropdown) dropdown.classList.remove('dropup');
    });

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

    // Live search: filter dashboard overview rows on every keystroke.
    document.addEventListener('input', function (event) {
        var input = event.target;
        if (!input || input.name !== 'q') {
            return;
        }
        var panel = input.closest('[data-dashboard-search-panel]');
        if (!panel) {
            return;
        }
        var keyword  = input.value.toLowerCase().trim();
        var sel      = panel.querySelector('select[name="sectorID"]');
        var sectorId = sel ? parseInt(sel.value || '0', 10) : 0;
        panel.querySelectorAll('[data-record-row]').forEach(function (row) {
            var nameEl = row.querySelector('[data-record-name]');
            var name   = nameEl ? nameEl.textContent.toLowerCase().trim() : '';
            var rawIds = row.dataset.sectorIds || '[]';
            var ids    = [];
            try { ids = JSON.parse(rawIds); } catch (_) {}
            if (!Array.isArray(ids)) { ids = ids ? [ids] : []; }
            var nameOk = !keyword  || name.indexOf(keyword)             !== -1;
            var secOk  = !sectorId || ids.map(Number).indexOf(sectorId) !== -1;
            row.style.display = (nameOk && secOk) ? '' : 'none';
        });
    });

    // Client-side "Search" for the dashboard overview panels (Recent Records on admin +
    // employee dashboards). Filters [data-record-row] rows without a server round-trip.
    document.addEventListener('submit', function (event) {
        const submitter = event.submitter;
        if (!submitter || submitter.dataset.searchMode !== 'local') {
            return;
        }

        const form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        const panel = form.closest('[data-dashboard-search-panel]');
        if (!panel) {
            return;
        }

        event.preventDefault();

        const keywordInput = form.querySelector('input[name="q"]');
        const sectorSelect = form.querySelector('select[name="sectorID"]');
        const keyword  = keywordInput  ? keywordInput.value.toLowerCase().trim()  : '';
        const sectorId = sectorSelect ? parseInt(sectorSelect.value || '0', 10)   : 0;

        panel.querySelectorAll('[data-record-row]').forEach(function (row) {
            var nameEl = row.querySelector('[data-record-name]');
            var name   = nameEl ? nameEl.textContent.toLowerCase().trim() : '';
            var rawIds = row.dataset.sectorIds || '[]';
            var ids    = [];
            try { ids = JSON.parse(rawIds); } catch (_) {}
            if (!Array.isArray(ids)) { ids = ids ? [ids] : []; }

            var nameOk = !keyword  || name.indexOf(keyword)              !== -1;
            var secOk  = !sectorId || ids.map(Number).indexOf(sectorId)  !== -1;
            row.style.display = (nameOk && secOk) ? '' : 'none';
        });
    });
})();
