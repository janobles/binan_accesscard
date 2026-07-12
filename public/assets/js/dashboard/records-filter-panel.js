// Filter panel + pills for the retrofit tabs (lookups, audit trails, employee
// activity, accounts). The panel's radios live inside the page's search form;
// changing one applies immediately (live-apply, no Apply/Reset buttons — see
// docs/knowledge/binan-conventions/ui-design-system.md). Manage Records has
// its own AJAX version of this in family-datatable.js.
//
// Two modes:
//   - server (default): change submits the GET form; pills render once from
//     the server-checked state.
//   - client (form has [data-records-client]): nothing is submitted — pills
//     re-render in place, and the page's own filter JS reacts to the radios'
//     bubbling change events (see accounts-modal.js).
//
// Connected to:
//   - form[data-records-filter-form]        the search/filter form
//   - form[data-records-pills="<id>"]       id of the pill container
//     (components/filter_pills.php; pill markup contract documented there)
//   - .records-filter-panel inputs          panel radios
//   - [data-records-filter] + data-records-group-label   filter group wrapper
//   - [data-records-pill-label]             pill text; options without it
//                                           (Active/All "no filter" choices) never pill
//   - [data-records-default]                pill-x fallback choice for the group
//   - [data-records-narrow]                 type-to-narrow input for long option lists
// Exposes window.initRecordsFilterPanel(root) so AJAX-loaded fragments can re-bind.
(function (window, document) {
    function panelInputs(form) {
        return form.querySelectorAll('.records-filter-panel input[type="radio"], .records-filter-panel input[type="checkbox"]');
    }

    function applyChange(form) {
        if (form.hasAttribute('data-records-client')) {
            renderPills(form);
            return;
        }
        form.submit();
    }

    function renderPills(form) {
        const pillsContainer = document.getElementById(form.dataset.recordsPills || '');
        if (!pillsContainer) {
            return;
        }

        pillsContainer.textContent = '';

        panelInputs(form).forEach(function (input) {
            if (!input.checked || !input.dataset.recordsPillLabel) {
                return;
            }

            const group = input.closest('[data-records-filter]');
            const prefix = group ? (group.dataset.recordsGroupLabel || group.dataset.recordsFilter) : 'Filter';

            const pill = document.createElement('span');
            pill.className = 'badge text-bg-light border d-inline-flex align-items-center gap-1';
            pill.appendChild(document.createTextNode(prefix + ': ' + input.dataset.recordsPillLabel));

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'btn-close';
            remove.setAttribute('aria-label', 'Remove filter ' + prefix);
            remove.addEventListener('click', function () {
                if (input.type === 'radio') {
                    const fallback = form.querySelector('input[name="' + input.name + '"][data-records-default]');
                    if (fallback) {
                        fallback.checked = true;
                        // Bubbling change lets client-mode pages re-filter their rows.
                        fallback.dispatchEvent(new Event('change', { bubbles: true }));
                        return;
                    }
                } else {
                    input.checked = false;
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                    return;
                }
                applyChange(form);
            });
            pill.appendChild(remove);

            pillsContainer.appendChild(pill);
        });
    }

    function initRecordsFilterPanel(rootElement) {
        const root = rootElement instanceof HTMLElement ? rootElement : document;

        root.querySelectorAll('form[data-records-filter-form]').forEach(function (form) {
            if (form.dataset.recordsFilterBound === '1') {
                return;
            }
            form.dataset.recordsFilterBound = '1';

            // Client-mode forms filter in place; Enter must not navigate.
            if (form.hasAttribute('data-records-client')) {
                form.addEventListener('submit', function (event) {
                    event.preventDefault();
                });
            }

            panelInputs(form).forEach(function (input) {
                input.addEventListener('change', function () {
                    applyChange(form);
                });
            });

            // Long option lists narrow as you type (matching the Manage Records
            // panel); the checked option stays visible so it can't get "lost".
            form.querySelectorAll('[data-records-narrow]').forEach(function (narrow) {
                narrow.addEventListener('input', function () {
                    const needle = narrow.value.trim().toLowerCase();
                    const panel = narrow.closest('.records-filter-panel');

                    panel.querySelectorAll('[data-records-option]').forEach(function (option) {
                        const optionInput = option.querySelector('input');
                        const matches = needle === ''
                            || option.textContent.toLowerCase().indexOf(needle) !== -1
                            || (optionInput && optionInput.checked);
                        option.classList.toggle('d-none', !matches);
                    });
                });
            });

            renderPills(form);
        });
    }

    window.initRecordsFilterPanel = initRecordsFilterPanel;

    document.addEventListener('DOMContentLoaded', function () {
        initRecordsFilterPanel(document);
    });
})(window, document);
